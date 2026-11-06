<?php

namespace Tests\Unit;

use App\Http\Middleware\VendorOrderBoundary;
use App\Models\Order;
use App\Services\OrderVendorPricingService;
use App\Services\OrderWorkflowService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendorOrderPhaseTwoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('seller_order_insurances', fn (Blueprint $t) => [$t->id(), $t->integer('order_id'), $t->timestamps()]);
        config(['vendor_product_workspace.enabled' => false]);
        Schema::create('business_settings', fn (Blueprint $t) => [$t->id(), $t->string('type'), $t->text('value'), $t->timestamps()]);
        DB::table('business_settings')->insert(['type'=>'language','value'=>'[{"code":"en","name":"English","default":true,"direction":"ltr"}]']);
        Schema::create('storages', fn (Blueprint $t) => [$t->id(), $t->integer('data_id'), $t->string('data_type'), $t->string('key')->nullable(), $t->string('value')->nullable(), $t->timestamps()]);
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->string('seller_is')->default('seller');
            $t->string('order_status')->default('confirmed'); $t->string('payment_status')->default('paid');
            $t->string('commerce_flow_status')->default('pending_admin_review'); $t->string('commerce_flow_version')->nullable();
            $t->string('admin_order_review_status')->nullable(); $t->decimal('order_amount',24,8)->default(240);
            $t->boolean('is_guest')->default(false); $t->unsignedBigInteger('customer_id')->nullable();
            $t->text('shipping_address_data')->nullable(); $t->text('billing_address_data')->nullable();
            $t->decimal('shipping_cost',24,8)->default(20); $t->decimal('discount_amount',24,8)->default(10);
            $t->decimal('refer_and_earn_discount',24,8)->default(0); $t->timestamps();
        });
        Schema::create('order_details', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('seller_id'); $t->integer('product_id'); $t->integer('qty');
            $t->decimal('price',24,8); $t->decimal('discount',24,8)->default(0); $t->decimal('tax',24,8)->default(0);
            $t->string('tax_model')->default('exclude'); $t->decimal('vendor_unit_price_snapshot',24,8)->nullable();
            $t->integer('vendor_content_revision')->nullable(); $t->text('product_details')->nullable(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_23_010000_create_order_vendor_pricings.php'))->up();
        DB::table('orders')->insert(['id'=>1,'seller_id'=>7,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('order_details')->insert(['order_id'=>1,'seller_id'=>7,'product_id'=>11,'qty'=>2,
            'price'=>120,'discount'=>10,'tax'=>0,'vendor_unit_price_snapshot'=>70,'product_details'=>'{"name":"Customer editorial name"}']);
    }

    protected function tearDown(): void
    {
        foreach (['order_vendor_pricings','order_details','orders','storages','business_settings'] as $table) Schema::dropIfExists($table);
        parent::tearDown();
    }

    public function test_approval_is_idempotent_and_never_changes_customer_prices(): void
    {
        $service = app(OrderVendorPricingService::class);
        $before = DB::table('orders')->find(1);
        $one = $service->approve(Order::findOrFail(1), 99);
        $two = $service->approve(Order::findOrFail(1), 98);
        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, DB::table('order_vendor_pricings')->count());
        $this->assertEquals(140, $one->procurement_amount);
        $this->assertEquals(220, $one->merchandise_sale);
        $this->assertEquals(80, $one->snapshot['margin']);
        $this->assertEquals($before, DB::table('orders')->find(1));
        $this->assertEquals(120, DB::table('order_details')->value('price'));
        $this->assertEquals(99, $two->admin_id);
    }

    public function test_missing_vendor_snapshot_is_not_inferred_from_current_product(): void
    {
        DB::table('order_details')->update(['vendor_unit_price_snapshot'=>null]);
        $this->expectException(\DomainException::class);
        app(OrderVendorPricingService::class)->approve(Order::findOrFail(1), 99);
    }

    public function test_admin_may_set_the_vendor_total_for_this_order_without_repricing_customer(): void
    {
        $service = app(OrderVendorPricingService::class);
        $order = Order::findOrFail(1);
        $pricing = $service->approve($order, 99, 155.0);
        $this->assertEquals(155, $pricing->procurement_amount);
        $this->assertEquals(140, $pricing->snapshot['previous_procurement_amount']);
        $this->assertSame('admin_adjusted_vendor_amount', $pricing->snapshot['action']);
        $this->assertEquals(240, $order->fresh()->order_amount);
        $this->assertEquals(120, DB::table('order_details')->value('price'));
        $this->assertEquals($pricing->snapshot, $service->summary($order->fresh(), 155.0));
        $this->expectException(\DomainException::class);
        $service->approve($order, 99, 160.0);
    }

    public function test_vendor_shipping_and_goods_are_assigned_one_due_date(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->timestamp('sales_settlement_due_at')->nullable();
            $t->timestamp('shipping_settlement_due_at')->nullable();
            $t->boolean('shipping_due_separated')->default(false);
            $t->text('shipping_due_override_reason')->nullable();
        });
        $due = \Carbon\Carbon::parse('2026-12-20 12:00:00');
        app(\App\Services\ShippingPromiseService::class)->updateSettlementDueDate(Order::findOrFail(1), $due, 'موعد موحد', 99);
        $row = DB::table('orders')->where('id', 1)->first();
        $this->assertSame($row->sales_settlement_due_at, $row->shipping_settlement_due_at);
        $this->assertSame(0, (int) $row->shipping_due_separated);
        $this->assertSame('2026-12-20 12:00:00', $row->sales_settlement_due_at);
    }

    public function test_unpaid_order_cannot_approve_procurement(): void
    {
        DB::table('orders')->update(['payment_status'=>'unpaid']);
        $this->expectException(\DomainException::class);
        app(OrderVendorPricingService::class)->approve(Order::findOrFail(1), 99);
    }

    public function test_discount_is_allocated_across_lines_without_shipping_or_tax(): void
    {
        DB::table('order_details')->insert(['order_id'=>1,'seller_id'=>7,'product_id'=>12,'qty'=>1,
            'price'=>100,'discount'=>0,'tax'=>10,'tax_model'=>'include','vendor_unit_price_snapshot'=>50]);
        $summary = app(OrderVendorPricingService::class)->summary(Order::findOrFail(1));
        $this->assertEquals(310, $summary['merchandise_sale']);
        $this->assertEquals(190, $summary['procurement_amount']);
        $this->assertEqualsWithDelta(10, array_sum(array_column($summary['lines'], 'allocated_order_discount')), 0.00001);
    }

    public function test_only_successive_seller_transitions_are_supported(): void
    {
        $workflow = app(OrderWorkflowService::class);
        foreach (['confirmed'=>'processing','processing'=>'out_for_delivery','out_for_delivery'=>'delivered'] as $from=>$to) $workflow->assertTransition($from,$to,'seller');
        $this->expectException(\DomainException::class);
        $workflow->assertTransition('confirmed','delivered','seller');
    }

    public function test_seller_cannot_cancel_even_with_override_reason(): void
    {
        $this->expectException(\DomainException::class);
        app(OrderWorkflowService::class)->assertTransition('confirmed','canceled','seller',true,'bypass');
    }

    public function test_admin_retains_recorded_override_permission(): void
    {
        app(OrderWorkflowService::class)->assertTransition('confirmed','delivered','admin',true,'delivery confirmed by support');
        $this->assertTrue(true);
    }

    public function test_legacy_vendor_payment_mutation_is_denied_before_controller(): void
    {
        $request = Request::create('/api/v2/seller/orders/update-payment-status','POST',['order_id'=>1]);
        $route = new Route('POST','api/v2/seller/orders/update-payment-status',['controller'=>'App\\Http\\Controllers\\RestAPI\\v2\\seller\\OrderController@update_payment_status']);
        $route->bind($request);
        $request->setRouteResolver(fn ()=>$route);
        $response = app(VendorOrderBoundary::class)->handle($request, fn()=>throw new \RuntimeException('controller must not execute'),7);
        $this->assertSame(422,$response->getStatusCode());
        $this->assertEquals(240,DB::table('orders')->value('order_amount'));
    }

    public function test_foreign_vendor_cannot_mutate_order(): void
    {
        $request = Request::create('/api/v3/seller/orders/update-payment-status','POST',['order_id'=>1]);
        $route = new Route('POST','api/v3/seller/orders/update-payment-status',['controller'=>'App\\Http\\Controllers\\RestAPI\\v3\\seller\\OrderController@update_payment_status']);
        $route->bind($request);
        $request->setRouteResolver(fn ()=>$route);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(VendorOrderBoundary::class)->handle($request, fn()=>throw new \RuntimeException('controller must not execute'),8);
    }

    public function test_public_phone_tracking_never_returns_customer_prices_or_editorial_data(): void
    {
        DB::table('orders')->where('id',1)->update(['shipping_address_data'=>'{"phone":"01000000000"}']);
        $request=Request::create('/api/v1/order/track-order','POST',['order_id'=>1,'phone_number'=>'01000000000']);
        $route=new Route('POST','api/v1/order/track-order',['controller'=>'App\\Http\\Controllers\\RestAPI\\v1\\OrderController@track_order']);
        $route->bind($request);$request->setRouteResolver(fn()=>$route);
        $response=app(\App\Http\Middleware\CustomerOrderTrackingBoundary::class)->handle($request,fn()=>throw new \RuntimeException('full customer details must not execute'));
        $this->assertSame(200,$response->getStatusCode());
        $this->assertArrayNotHasKey('order_amount',$response->getData(true)[0]['order']);
        $this->assertNull($response->getData(true)[0]['product_details']);
    }

    public function test_order_number_alone_does_not_authorize_customer_tracking(): void
    {
        $request=Request::create('/api/v1/order/track-order-details','GET',['order_id'=>1]);
        $route=new Route('GET','api/v1/order/track-order-details',['controller'=>'App\\Http\\Controllers\\RestAPI\\v1\\OrderController@track_order_details_history']);
        $route->bind($request);$request->setRouteResolver(fn()=>$route);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Http\Middleware\CustomerOrderTrackingBoundary::class)->handle($request,fn()=>throw new \RuntimeException('history must not execute'));
    }
}
