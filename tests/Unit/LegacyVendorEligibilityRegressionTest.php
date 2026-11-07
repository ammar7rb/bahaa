<?php

namespace Tests\Unit;

use App\Http\Controllers\RestAPI\v2\seller\OrderController;
use App\Http\Controllers\RestAPI\v2\delivery_man\DeliveryManController;
use App\Models\{Order,Seller};
use App\Services\{VendorFulfillmentEligibilityService,VendorFulfillmentDecisionService,OrderWorkflowService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema,Event};
use Tests\Support\VendorFulfillmentFixture;
use Tests\TestCase;

class LegacyVendorEligibilityRegressionTest extends TestCase
{
    use VendorFulfillmentFixture;
    private const TOKEN='legacy-vendor-regression-token-with-valid-length';
    protected function setUp():void
    {
        parent::setUp();$this->createFulfillmentFixture();
        Schema::table('sellers',fn($t)=>$t->string('auth_token')->nullable());
        Schema::table('orders',function($t){$t->integer('delivery_man_id')->nullable();$t->json('billing_address_data')->nullable();});
        DB::table('sellers')->where('id',7)->update(['auth_token'=>self::TOKEN]);
        DB::table('orders')->where('id',1)->update(['delivery_man_id'=>5,'verification_code'=>'SECRET-OTP',
            'shipping_address_data'=>'{"address":"SECRET-ADDRESS","phone":"SECRET-PHONE"}','billing_address_data'=>'{"address":"SECRET-BILLING"}']);
        Event::fake([\App\Events\OrderStatusEvent::class]);
    }
    private function request(string $path,string $method='GET',array $data=[]):Request
    {
        $request=Request::create($path,$method,$data,server:['HTTP_AUTHORIZATION'=>'Bearer '.self::TOKEN,'HTTP_ACCEPT'=>'application/json']);
        return $request;
    }
    private function forward():\App\Models\VendorFulfillmentRequest
    {
        return app(VendorFulfillmentEligibilityService::class)->forward(Order::find(1))->first();
    }
    private function mixedCanceled():void
    {
        $row=$this->forward();$row->update(['status'=>'available','unlocked_at'=>now()]);
        DB::table('order_details')->insert(['id'=>2,'order_id'=>1,'seller_id'=>7,'product_id'=>22,'qty'=>1,'price'=>999,'vendor_unit_price_snapshot'=>30,'product_details'=>'{"name":"CANCELED-SECRET"}','is_stock_decreased'=>0,'delivery_status'=>'canceled']);
        $id=DB::table('vendor_fulfillment_requests')->insertGetId(['seller_id'=>7,'order_id'=>1,'portion'=>'advertising','status'=>'canceled','vendor_amount'=>30,'shipping_amount'=>0,'forwarded_at'=>now(),'canceled_at'=>now(),'accepted_at'=>now(),'decision_reason'=>'canceled fixture']);
        DB::table('vendor_fulfillment_request_items')->insert(['request_id'=>$id,'order_detail_id'=>2,'product_id'=>22,'created_at'=>now()]);
    }

    public function test_actual_v2_list_http_request_uses_safe_locked_dto():void
    {
        $this->forward();
        $response=$this->getJson('/api/v2/seller/orders/list',['Authorization'=>'Bearer '.self::TOKEN]);
        $response->assertOk()->assertJsonPath('0.details_locked',true)->assertJsonPath('0.order_amount',160);
        foreach(['SECRET-ADDRESS','SECRET-PHONE','SECRET-BILLING','SECRET-OTP','SECRET-EDITORIAL','9999','777'] as $secret)$this->assertStringNotContainsString($secret,$response->getContent());
        $response->assertJsonMissingPath('0.customer')->assertJsonMissingPath('0.verification_code');
    }

    public function test_direct_legacy_list_is_also_allowlisted_and_locked_details_are_denied():void
    {
        $this->forward();$controller=app(OrderController::class);
        $data=$controller->list($this->request('/api/v2/seller/orders/list'))->getData(true);
        $this->assertTrue($data[0]['details_locked']);$this->assertSame([],$data[0]['order_details']);
        try{$controller->details($this->request('/api/v2/seller/orders/1'),1);$this->fail('Locked details returned.');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(423,$e->getStatusCode());}
    }

    public function test_v2_mixed_details_only_return_eligible_vendor_lines():void
    {
        $row=$this->forward();$row->update(['portion'=>'advertising','status'=>'available']);
        DB::table('order_details')->insert(['id'=>2,'order_id'=>1,'seller_id'=>7,'product_id'=>22,'qty'=>1,'price'=>999,'vendor_unit_price_snapshot'=>30,'product_details'=>'{"name":"HIDDEN-MIXED"}']);
        $id=DB::table('vendor_fulfillment_requests')->insertGetId(['seller_id'=>7,'order_id'=>1,'portion'=>'outside_slot','status'=>'locked','vendor_amount'=>30,'shipping_amount'=>0,'forwarded_at'=>now()]);
        DB::table('vendor_fulfillment_request_items')->insert(['request_id'=>$id,'order_detail_id'=>2,'product_id'=>22,'created_at'=>now()]);
        $response=$this->getJson('/api/v2/seller/orders/1',['Authorization'=>'Bearer '.self::TOKEN]);
        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.price',70);
        foreach(['HIDDEN-MIXED','SECRET-EDITORIAL','SECRET-OTP','777','999'] as $secret)$this->assertStringNotContainsString($secret,$response->getContent());
    }

    public function test_old_vendor_status_cannot_reconsume_canceled_stock_or_cancel_whole_managed_order():void
    {
        $this->mixedCanceled();
        foreach(['processing','delivered','canceled'] as $to){
            $response=app(OrderController::class)->order_detail_status($this->request('/','PUT',['id'=>1,'order_status'=>$to]));
            $this->assertSame(422,$response->getStatusCode());
        }
        $this->assertEquals(10,DB::table('products')->where('id',22)->value('current_stock'));
        $this->assertSame(0,DB::table('order_details')->where('id',2)->value('is_stock_decreased'));
        $this->assertSame('canceled',DB::table('order_details')->where('id',2)->value('delivery_status'));
        $this->assertSame('confirmed',Order::find(1)->order_status);
        $this->assertSame(0,DB::table('seller_settlements')->count());$this->assertSame(0,DB::table('seller_ledger_entries')->count());
    }

    public function test_old_delivery_endpoint_cannot_deliver_or_post_money_for_a_partially_canceled_order():void
    {
        $this->mixedCanceled();
        $response=app(DeliveryManController::class)->update_order_status($this->request('/','PUT',['order_id'=>1,'status'=>'delivered','delivery_man'=>['id'=>5]]));
        $this->assertSame(422,$response->getStatusCode());$this->assertSame('confirmed',Order::find(1)->order_status);
        $this->assertSame('canceled',DB::table('order_details')->where('id',2)->value('delivery_status'));
        $this->assertSame(0,DB::table('seller_settlements')->count());$this->assertSame(0,DB::table('seller_ledger_entries')->count());
        Event::assertNotDispatched(\App\Events\OrderStatusEvent::class);
    }

    public function test_stock_and_financial_helpers_fail_closed_if_a_managed_order_reaches_them():void
    {
        $this->mixedCanceled();
        foreach([
            fn()=>\App\Utils\OrderManager::getStockUpdateOnOrderStatusChange(Order::find(1),'delivered'),
            fn()=>\App\Utils\OrderManager::getWalletManageOnOrderStatusChange(Order::find(1),'delivery man'),
            fn()=>app(\App\Services\SellerDeliveredEarningService::class)->record(Order::find(1),fn()=>$this->fail('Legacy financial calculator executed')),
        ] as $operation){try{$operation();$this->fail('Legacy effect executed.');}catch(\DomainException){$this->assertTrue(true);}}
        $this->assertEquals(10,DB::table('products')->where('id',22)->value('current_stock'));
    }

    public function test_failed_legacy_nonmanaged_update_rolls_back_stock_before_returning_an_error():void
    {
        DB::table('order_details')->where('id',1)->update(['is_stock_decreased'=>0]);
        Order::updating(function($order){if($order->order_status==='processing')throw new \DomainException('Injected failure after stock work.');});
        $response=app(OrderController::class)->order_detail_status($this->request('/','PUT',['id'=>1,'order_status'=>'processing']));
        $this->assertSame(422,$response->getStatusCode());
        $this->assertEquals(10,DB::table('products')->where('id',11)->value('current_stock'));
        $this->assertSame(0,DB::table('order_details')->where('id',1)->value('is_stock_decreased'));
        $this->assertSame('confirmed',Order::find(1)->order_status);
        Event::assertNotDispatched(\App\Events\OrderStatusEvent::class);
    }

    public function test_legacy_workflow_cannot_bypass_portion_cancellation_even_with_override_or_same_status():void
    {
        foreach(['confirmed','canceled'] as $from){try{app(OrderWorkflowService::class)->assertTransition($from,'canceled','seller',true,'override');$this->fail('Whole-order seller cancellation allowed.');}catch(\DomainException){$this->assertTrue(true);}}
        app(OrderWorkflowService::class)->assertTransition('confirmed','canceled','admin');$this->assertTrue(true);
    }

    public static function proofFiles():array {return [['proof.pdf','application/pdf'],['proof.mp4','video/mp4']];}

    public static function penaltyModes():array{return [['fixed'],['percentage']];}

    #[\PHPUnit\Framework\Attributes\DataProvider('penaltyModes')]
    public function test_website_cancellation_displays_the_selected_calculation_mode(string $mode):void
    {
        Schema::create('currencies',function($t){$t->id();$t->string('code');$t->string('symbol');$t->decimal('exchange_rate',12,4);});
        DB::table('currencies')->insert(['id'=>1,'code'=>'EGP','symbol'=>'ج.م','exchange_rate'=>1]);
        DB::table('business_settings')->insert([['type'=>'system_default_currency','value'=>'1'],['type'=>'currency_model','value'=>'single_currency'],['type'=>'decimal_point_settings','value'=>'2']]);
        $fulfillment=$this->forward();$fulfillment->update(['status'=>'accepted','accepted_at'=>now(),'unlocked_at'=>now()]);
        app(\App\Services\VendorCancellationPenaltyService::class)->save(['enabled'=>true,'mode'=>$mode,'fixed_amount'=>10,'percentage'=>10],1);
        $row=app(\App\Services\VendorOrderPresentationService::class)->order(Order::find(1));
        $html=view('vendor-views.order._fulfillment-portions',compact('row'))->render();
        $this->assertStringContainsString($mode==='fixed'?'مبلغ ثابت بالجنيه':'النسبة 10',$html);
        if($mode==='fixed')$this->assertStringNotContainsString('أساس النسبة',$html);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('proofFiles')]
    public function test_portion_proof_accepts_pdf_and_video_privately_once(string $name,string $mime):void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $row=$this->forward();$row->update(['status'=>'shipping','accepted_at'=>now(),'unlocked_at'=>now()]);
        $file=\Illuminate\Http\UploadedFile::fake()->create($name,50,$mime);
        $request=Request::create('/api/v3/seller/fulfillment-requests/'.$row->id.'/delivery-proof','POST',['seller'=>Seller::find(7)],files:['proof'=>$file],server:['HTTP_ACCEPT'=>'application/json']);
        $controller=app(\App\Http\Controllers\Vendor\VendorFulfillmentController::class);
        $response=$controller->deliveryProof($request,$row->id);
        $this->assertSame(200,$response->getStatusCode());
        $this->assertSame('delivered',$row->fresh()->status);
        $controller->deliveryProof($request,$row->id);
        $this->assertSame(1,DB::table('order_shipping_proofs')->count());$this->assertSame(1,DB::table('seller_settlements')->count());
        $this->assertSame('local',DB::table('order_shipping_proofs')->value('disk'));
        $this->assertSame($mime,DB::table('order_shipping_proofs')->value('mime_type'));
        $this->assertStringNotContainsString('shipping-proofs/fulfillment/',$response->getContent());
    }
}
