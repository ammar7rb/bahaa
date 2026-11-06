<?php

namespace Tests\Unit;

use App\Models\{Order, Seller, SellerPackageSubscription, VendorAdvertisingSlot, VendorFulfillmentRequest};
use App\Services\{SellerBalanceDepositService, SellerLedgerService, VendorCancellationPenaltyService, VendorDebtService, VendorFulfillmentDecisionService, VendorFulfillmentEligibilityService, VendorFulfillmentUnlockService};
use Illuminate\Support\Facades\DB;
use Tests\Support\VendorFulfillmentFixture;
use Tests\TestCase;

class VendorFulfillmentPhase56Test extends TestCase
{
    use VendorFulfillmentFixture;
    protected function setUp(): void { parent::setUp(); $this->createFulfillmentFixture(); }
    private function seller(): Seller {return Seller::findOrFail(7);}
    private function forward(): VendorFulfillmentRequest{return app(VendorFulfillmentEligibilityService::class)->forward(Order::findOrFail(1))->first();}
    private function package(bool $full=false): SellerPackageSubscription
    {
        $id=DB::table('seller_package_subscriptions')->insertGetId(['seller_id'=>7,'payment_status'=>'paid','status'=>'active','starts_at'=>now()->subDay(),'expires_at'=>now()->addDays(29)]);
        if($full)VendorAdvertisingSlot::create(['seller_id'=>7,'seller_package_subscription_id'=>$id,'product_id'=>33,'position'=>1,'starts_at'=>now()->subDay(),'expires_at'=>now()->addDays(29)]);
        return SellerPackageSubscription::findOrFail($id);
    }
    private function unlock(VendorFulfillmentRequest $row): VendorFulfillmentRequest
    {
        $quote=app(VendorFulfillmentUnlockService::class)->quote($this->seller(),$row->id);
        return app(VendorFulfillmentUnlockService::class)->unlock($this->seller(),$row->id,$quote['token']);
    }
    private function fund(float $amount): void
    {
        $service=app(SellerBalanceDepositService::class);$deposit=$service->createDigital($this->seller(),$amount,'EGP','test');$service->markPaid($deposit);
    }

    public function test_paid_package_alone_does_not_unlock_or_accept(): void
    {
        $row=$this->forward();$sub=$this->package();
        app(VendorFulfillmentUnlockService::class)->notifyPackageConfirmed($sub);
        app(VendorFulfillmentUnlockService::class)->notifyPackageConfirmed($sub);
        $this->assertSame('locked',$row->fresh()->status);$this->assertNull($row->fresh()->accepted_at);
        $this->assertSame(1,DB::table('vendor_fulfillment_events')->where('event_type','package_confirmed')->count());
    }

    public function test_free_slot_assignment_unlocks_without_charge_or_acceptance(): void
    {
        $row=$this->forward();$this->package();$row=$this->unlock($row);
        $this->assertSame('available',$row->status);$this->assertNotNull($row->unlocked_at);$this->assertNull($row->accepted_at);
        $this->assertSame(11,VendorAdvertisingSlot::first()->product_id);$this->assertSame(0,DB::table('vendor_debt_charges')->count());
    }

    public function test_full_capacity_charges_wallet_and_debt_once_without_replacing_product(): void
    {
        $row=$this->forward();$this->package(true);$this->fund(30);
        $service=app(VendorFulfillmentUnlockService::class);$quote=$service->quote($this->seller(),$row->id);
        $this->assertEquals(100,$quote['total']);$this->assertEquals(30,$quote['wallet_debit']);$this->assertEquals(70,$quote['debt']);
        $service->unlock($this->seller(),$row->id,$quote['token']);$service->unlock($this->seller(),$row->id,$quote['token']);
        $this->assertSame(2,VendorAdvertisingSlot::count());$this->assertSame(33,VendorAdvertisingSlot::orderBy('id')->first()->product_id);
        $this->assertEquals(70,app(VendorDebtService::class)->outstanding(7));$this->assertSame(1,DB::table('vendor_debt_charges')->count());
        $this->fund(50);$this->assertEquals(20,app(VendorDebtService::class)->outstanding(7));
        $this->fund(50);$this->assertEquals(0,app(VendorDebtService::class)->outstanding(7));
        $this->assertEquals(30,app(SellerLedgerService::class)->summary(7)['operating']);
    }

    public function test_unpaid_package_and_missing_price_do_not_allocate_or_charge(): void
    {
        $row=$this->forward();$sub=$this->package(true);$sub->update(['payment_status'=>'unpaid']);
        try{app(VendorFulfillmentUnlockService::class)->quote($this->seller(),$row->id);$this->fail();}catch(\DomainException){}
        $sub->update(['payment_status'=>'paid']);DB::table('vendor_extra_ad_settings')->update(['unit_price'=>null]);
        $this->expectException(\DomainException::class);
        try{app(VendorFulfillmentUnlockService::class)->quote($this->seller(),$row->id);}finally{$this->assertSame(0,DB::table('vendor_debt_charges')->count());$this->assertSame('locked',$row->fresh()->status);}
    }

    public function test_stale_charge_quote_is_rejected_when_wallet_or_price_changes(): void
    {
        $row=$this->forward();$this->package(true);$service=app(VendorFulfillmentUnlockService::class);$quote=$service->quote($this->seller(),$row->id);
        $this->fund(50);$this->expectException(\DomainException::class);
        try{$service->unlock($this->seller(),$row->id,$quote['token']);}finally{$this->assertSame(0,DB::table('vendor_debt_charges')->count());$this->assertSame('locked',$row->fresh()->status);}
    }

    public function test_reject_before_acceptance_is_free_even_after_paying_for_slot(): void
    {
        $row=$this->forward();$this->package(true);$row=$this->unlock($row);
        $service=app(VendorFulfillmentDecisionService::class);$service->decide($this->seller(),$row->id,'reject','غير مناسب');
        $service->decide($this->seller(),$row->id,'reject','غير مناسب');
        $this->assertSame('rejected',$row->fresh()->status);$this->assertNull($row->fresh()->accepted_at);
        $this->assertSame(0,DB::table('vendor_debt_charges')->where('kind','cancellation_penalty')->count());
        $this->assertSame(1,DB::table('vendor_fulfillment_financial_reviews')->count());
    }

    public function test_percentage_penalty_uses_vendor_goods_not_customer_price_or_shipping_and_snapshots_rule(): void
    {
        $row=$this->forward();$this->package();$row=$this->unlock($row);
        $penalties=app(VendorCancellationPenaltyService::class);$penalties->save(['enabled'=>true,'mode'=>'percentage','fixed_amount'=>999,'percentage'=>10],9);
        $this->fund(5);$service=app(VendorFulfillmentDecisionService::class);$service->decide($this->seller(),$row->id,'accept');
        $accepted=$row->fresh()->accepted_at;$service->decide($this->seller(),$row->id,'cancel','توقف التوريد');
        $snapshot=$row->fresh()->penalty_rule_snapshot;
        $this->assertNotNull($accepted);$this->assertEquals(140,$snapshot['base_amount']);$this->assertEquals(14,$snapshot['assessed_amount']);
        $this->assertEquals(5,$snapshot['wallet_collected']);$this->assertEquals(9,$snapshot['debt_assessed']);
        $penalties->save(['enabled'=>true,'mode'=>'fixed','fixed_amount'=>200,'percentage'=>90],9);
        $this->assertSame($snapshot,$row->fresh()->penalty_rule_snapshot);
        $service->decide($this->seller(),$row->id,'cancel','توقف التوريد');
        $this->assertSame(1,DB::table('vendor_debt_charges')->where('kind','cancellation_penalty')->count());
    }

    public function test_fixed_mode_never_adds_percentage_and_invalid_settings_are_rejected(): void
    {
        $service=app(VendorCancellationPenaltyService::class);$service->save(['enabled'=>true,'mode'=>'fixed','fixed_amount'=>25,'percentage'=>50],9);
        $this->assertEquals(25,$service->quote(140)['assessed_amount']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);$service->save(['enabled'=>true,'mode'=>'percentage','percentage'=>101],9);
    }

    public function test_progress_cannot_skip_explicit_acceptance_and_rejection_cannot_follow_acceptance(): void
    {
        $row=$this->forward();$this->package();$row=$this->unlock($row);$service=app(VendorFulfillmentDecisionService::class);
        try{$service->decide($this->seller(),$row->id,'preparing');$this->fail();}catch(\DomainException){}
        $service->decide($this->seller(),$row->id,'accept');$service->decide($this->seller(),$row->id,'preparing');$service->decide($this->seller(),$row->id,'ready');$service->decide($this->seller(),$row->id,'shipping');
        $this->assertSame('out_for_delivery',Order::find(1)->order_status);
        $this->expectException(\DomainException::class);$service->decide($this->seller(),$row->id,'reject','بعد القبول');
    }

    public function test_expiry_does_not_relock_a_revealed_request(): void
    {
        $row=$this->forward();$sub=$this->package();$this->unlock($row);$sub->update(['status'=>'expired','expires_at'=>now()->subMinute()]);
        $this->assertFalse(app(VendorFulfillmentEligibilityService::class)->hasRestrictions(Order::find(1)));
        app(VendorFulfillmentDecisionService::class)->decide($this->seller(),$row->id,'accept');$this->assertNotNull($row->fresh()->accepted_at);
    }

    public function test_mixed_request_decisions_only_change_their_own_lines_and_penalty_base(): void
    {
        $sub=$this->package();VendorAdvertisingSlot::create(['seller_id'=>7,'seller_package_subscription_id'=>$sub->id,'product_id'=>11,'position'=>1,'starts_at'=>$sub->starts_at,'expires_at'=>$sub->expires_at]);
        DB::table('order_details')->insert(['id'=>2,'order_id'=>1,'seller_id'=>7,'product_id'=>22,'qty'=>1,'price'=>999,'vendor_unit_price_snapshot'=>30,'product_details'=>'{"name":"HIDDEN-MIXED"}']);
        DB::table('order_vendor_pricings')->update(['procurement_amount'=>170]);
        $rows=app(VendorFulfillmentEligibilityService::class)->forward(Order::find(1),['accessible_procurement_amount'=>140,'accessible_shipping_amount'=>8]);
        $available=$rows->firstWhere('portion','advertising');$restricted=$rows->firstWhere('portion','outside_slot');
        app(VendorCancellationPenaltyService::class)->save(['enabled'=>true,'mode'=>'percentage','percentage'=>10],9);
        $service=app(VendorFulfillmentDecisionService::class);$service->decide($this->seller(),$available->id,'accept');
        $this->assertSame('locked',$restricted->fresh()->status);$this->assertSame('confirmed',Order::find(1)->order_status);
        $service->decide($this->seller(),$available->id,'cancel','إلغاء هذا الجزء فقط');
        $this->assertSame('locked',$restricted->fresh()->status);$this->assertSame('confirmed',Order::find(1)->order_status);
        $this->assertSame('canceled',DB::table('order_details')->where('id',1)->value('delivery_status'));
        $this->assertSame('confirmed',DB::table('order_details')->where('id',2)->value('delivery_status'));
        $this->assertEquals(12,DB::table('products')->where('id',11)->value('current_stock'));
        $this->assertEquals(10,DB::table('products')->where('id',22)->value('current_stock'));
        $this->assertEquals(14,$available->fresh()->penalty_rule_snapshot['assessed_amount']);
    }

    public function test_admin_cancellation_never_assesses_vendor_fee_or_reveals_a_previously_locked_portion(): void
    {
        $row=$this->forward();$order=Order::find(1);$order->update(['order_status'=>'canceled']);
        app(VendorFulfillmentDecisionService::class)->synchronizeExternalCancellation($order);
        $this->assertSame('canceled',$row->fresh()->status);$this->assertSame(0,DB::table('vendor_debt_charges')->count());
        $this->assertTrue(app(VendorFulfillmentEligibilityService::class)->hasRestrictions($order));
        $this->assertFalse(app(VendorFulfillmentEligibilityService::class)->lineAllowed(\App\Models\OrderDetail::find(1)));
    }

    public function test_delivery_requires_proof_and_settles_only_vendor_entitlement_once(): void
    {
        $row=$this->forward();$this->package();$this->unlock($row);$service=app(VendorFulfillmentDecisionService::class);
        foreach(['accept','preparing','ready','shipping'] as $action)$service->decide($this->seller(),$row->id,$action);
        try{$service->decide($this->seller(),$row->id,'delivered',proofId:999);$this->fail();}catch(\DomainException){}
        $proof=\App\Models\OrderShippingProof::create(['order_id'=>1,'seller_id'=>7,'fulfillment_request_id'=>$row->id,'shipping_status'=>'delivered','file_path'=>'fixture.png','disk'=>'local','review_status'=>'pending']);
        $service->decide($this->seller(),$row->id,'delivered',proofId:$proof->id);
        $service->decide($this->seller(),$row->id,'delivered',proofId:$proof->id);
        $this->assertSame('delivered',Order::find(1)->order_status);
        $this->assertEquals(160,DB::table('seller_settlements')->value('amount'));
        $this->assertSame(1,DB::table('seller_settlements')->count());
        $this->assertEquals(160,app(SellerLedgerService::class)->summary(7)['pending']);
        $this->assertEquals(0,app(VendorDebtService::class)->outstanding(7));
    }

    public function test_other_vendor_and_stale_penalty_revision_cannot_apply_a_decision(): void
    {
        $row=$this->forward();$this->package();$this->unlock($row);DB::table('sellers')->insert(['id'=>8,'activation_status'=>'active']);
        try{app(VendorFulfillmentDecisionService::class)->decide(Seller::find(8),$row->id,'accept');$this->fail();}catch(\Illuminate\Database\Eloquent\ModelNotFoundException){}
        $service=app(VendorFulfillmentDecisionService::class);$service->decide($this->seller(),$row->id,'accept');
        app(VendorCancellationPenaltyService::class)->save(['enabled'=>true,'mode'=>'fixed','fixed_amount'=>50],9);
        $this->expectException(\DomainException::class);
        try{$service->decide($this->seller(),$row->id,'cancel','سبب',expectedPenaltyRevision:0);}finally{$this->assertSame('accepted',$row->fresh()->status);$this->assertSame(0,DB::table('vendor_debt_charges')->count());}
    }

    public function test_reserved_slots_and_maximum_capacity_are_not_silently_bypassed(): void
    {
        $row=$this->forward();$sub=$this->package();
        VendorAdvertisingSlot::create(['seller_id'=>7,'seller_package_subscription_id'=>$sub->id,'position'=>1,
            'reservation_token_hash'=>'occupied','reservation_expires_at'=>now()->addMinute(),'starts_at'=>$sub->starts_at,'expires_at'=>$sub->expires_at]);
        VendorAdvertisingSlot::first()->forceFill(['reservation_token_hash'=>'occupied','reservation_expires_at'=>now()->addMinute()])->save();
        $quote=app(VendorFulfillmentUnlockService::class)->quote($this->seller(),$row->id);$this->assertSame(1,$quote['extra_count']);
        DB::table('vendor_extra_ad_settings')->update(['max_slots'=>0]);
        $this->expectException(\DomainException::class);app(VendorFulfillmentUnlockService::class)->quote($this->seller(),$row->id);
    }

    public function test_price_change_after_quote_requires_review_and_keeps_request_locked(): void
    {
        $row=$this->forward();$this->package(true);$service=app(VendorFulfillmentUnlockService::class);$quote=$service->quote($this->seller(),$row->id);
        DB::table('vendor_extra_ad_settings')->update(['unit_price'=>150]);
        $this->expectException(\DomainException::class);
        try{$service->unlock($this->seller(),$row->id,$quote['token']);}finally{$this->assertSame('locked',$row->fresh()->status);$this->assertSame(0,DB::table('vendor_debt_charges')->count());}
    }

    public function test_concurrent_unlocks_and_cancellations_have_one_financial_effect_each(): void
    {
        $row=$this->forward();$this->package(true);$this->fund(30);$quote=app(VendorFulfillmentUnlockService::class)->quote($this->seller(),$row->id);
        $this->parallel(['action'=>'unlock','id'=>$row->id,'token'=>$quote['token']],function(\PDO $pdo){
            $this->assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM vendor_advertising_slots')->fetchColumn());
            $this->assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM vendor_debt_charges')->fetchColumn());
            $this->assertEquals(70,$pdo->query('SELECT SUM(outstanding) FROM vendor_debt_charges')->fetchColumn());
        });
        // A second independent fixture snapshot exercises the cancellation race.
        $this->unlock($row);app(VendorFulfillmentDecisionService::class)->decide($this->seller(),$row->id,'accept');
        app(VendorCancellationPenaltyService::class)->save(['enabled'=>true,'mode'=>'fixed','fixed_amount'=>25],9);
        $this->parallel(['action'=>'cancel','id'=>$row->id],function(\PDO $pdo){
            $this->assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM vendor_debt_charges WHERE kind='cancellation_penalty'")->fetchColumn());
            $this->assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM vendor_fulfillment_events WHERE event_type='canceled'")->fetchColumn());
            $this->assertSame(12,(int)$pdo->query('SELECT current_stock FROM products WHERE id=11')->fetchColumn());
        });
    }

    private function parallel(array $data,callable $verify): void
    {
        $directory=storage_path('framework/testing');if(!is_dir($directory))mkdir($directory,0777,true);
        $base=$directory.'/phase56-'.bin2hex(random_bytes(8));$database=$base.'.sqlite';$payload=$base.'.json';
        file_put_contents($payload,json_encode($data,JSON_THROW_ON_ERROR));DB::statement('VACUUM INTO ?',[$database]);
        $workers=[new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tests/Support/vendor-fulfillment-worker.php'),$database,$payload]),new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tests/Support/vendor-fulfillment-worker.php'),$database,$payload])];
        try{
            foreach($workers as $worker)$worker->start();
            foreach($workers as $worker){$worker->wait();$this->assertTrue($worker->isSuccessful(),$worker->getOutput().$worker->getErrorOutput());$this->assertSame($data['action']==='unlock'?'available':'canceled',trim($worker->getOutput()));}
            $pdo=new \PDO('sqlite:'.$database);$verify($pdo);$pdo=null;
        }finally{$pdo=null;foreach([$database,$payload] as $file)if(is_file($file))@unlink($file);}
    }

    public function test_customer_refund_is_reviewed_credited_once_and_never_deducted_from_vendor_balance(): void
    {
        $row=$this->forward();$this->package(true);$this->unlock($row);
        app(VendorFulfillmentDecisionService::class)->decide($this->seller(),$row->id,'reject','لا يمكن التوريد');
        $service=app(\App\Services\VendorFulfillmentRefundService::class);
        $refund=$service->prepare($row->id,1,100,9,'مراجعة مبلغ العميل بعد الخصومات');
        $this->assertSame('pending',$refund->status);$this->assertEquals(0,DB::table('users')->value('wallet_balance'));
        $data=['refund_status'=>'refunded','payment_method'=>'customer_wallet','payment_info'=>'confirmed-refund-1','approved_note'=>'رد معتمد'];
        $service->process($refund->id,$data,9);$service->process($refund->id,$data,9);
        $this->assertEquals(100,DB::table('users')->value('wallet_balance'));
        $this->assertSame(1,DB::table('wallet_transactions')->count());$this->assertSame(1,DB::table('refund_transactions')->count());
        $this->assertSame('admin',DB::table('refund_transactions')->value('paid_by'));
        $this->assertEquals(1000,app(SellerLedgerService::class)->summary(7)['available']);
        $this->assertEquals(100,app(VendorDebtService::class)->outstanding(7));
    }

    public function test_refund_cannot_exceed_customer_line_or_duplicate_an_order_wide_refund(): void
    {
        $row=$this->forward();$this->package();$this->unlock($row);
        app(VendorFulfillmentDecisionService::class)->decide($this->seller(),$row->id,'reject','لا يمكن التوريد');
        $service=app(\App\Services\VendorFulfillmentRefundService::class);
        try{$service->prepare($row->id,1,2000,9,'too much');$this->fail();}catch(\DomainException){}
        $this->assertSame(0,DB::table('refund_requests')->count());
        $refund=$service->prepare($row->id,1,100,9,'reviewed');
        \Illuminate\Support\Facades\Schema::create('order_admin_purchase_refunds',fn(\Illuminate\Database\Schema\Blueprint $t)=>[$t->id(),$t->integer('order_id'),$t->string('status')]);
        DB::table('order_admin_purchase_refunds')->insert(['order_id'=>1,'status'=>'wallet_approved']);
        $this->expectException(\DomainException::class);
        try{$service->process($refund->id,['refund_status'=>'refunded','payment_method'=>'customer_wallet','payment_info'=>'blocked'],9);}
        finally{$this->assertEquals(0,DB::table('users')->value('wallet_balance'));$this->assertSame(0,DB::table('refund_transactions')->count());}
    }
}
