<?php

namespace Tests\Unit;

use App\Models\CustomerSubscriptionPayment;
use App\Models\CustomerMonthlySubscription;
use App\Models\OfflinePaymentMethod;
use App\Services\CustomerMonthlySubscriptionService;
use App\Services\MonthlySubscriptionPricingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerMonthlySubscriptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        Schema::create('users',function(Blueprint $t){$t->id();$t->decimal('wallet_balance',24,3)->default(0);$t->boolean('is_active')->default(true);$t->timestamps();});
        Schema::create('currencies',function(Blueprint $t){$t->id();$t->string('code');$t->decimal('exchange_rate',18,6)->default(1);$t->string('symbol');$t->boolean('status')->default(true);$t->timestamps();});
        Schema::create('business_settings',function(Blueprint $t){$t->id();$t->string('type');$t->text('value');$t->timestamps();});
        Schema::create('wallet_transactions',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');foreach(['transaction_id','reference','transaction_type','payment_method'] as $s)$t->string($s);foreach(['credit','debit','admin_bonus','balance'] as $s)$t->decimal($s,24,3)->default(0);$t->timestamps();});
        Schema::create('offline_payment_methods',function(Blueprint $t){$t->id();$t->string('method_name');$t->string('payment_channel');$t->boolean('status')->default(true);$t->json('method_fields')->nullable();$t->json('method_informations')->nullable();$t->timestamps();});
        (require base_path('database/migrations/2026_10_10_200000_create_customer_monthly_subscriptions.php'))->up();
        DB::table('currencies')->insert(['id'=>1,'code'=>'EGP','symbol'=>'EGP']);
        foreach(['currency_model'=>'single_currency','system_default_currency'=>'1','wallet_status'=>'1','offline_payment'=>'{"status":1}'] as $key=>$value)DB::table('business_settings')->insert(['type'=>$key,'value'=>$value]);
        DB::table('users')->insert(['id'=>1,'wallet_balance'=>300,'is_active'=>1]);
        DB::table('users')->insert(['id'=>2,'wallet_balance'=>300,'is_active'=>1]);
        DB::table('monthly_subscription_prices')->insert(['id'=>1,'customer_price'=>100,'vendor_price'=>250,'currency_code'=>'EGP']);
    }

    public function test_transfer_phone_is_validated_and_stored_canonically(): void {
        $p=$this->checkout();
        $method=OfflinePaymentMethod::create(['method_name'=>'wallet','payment_channel'=>'wallet','status'=>1,'method_informations'=>[['customer_input'=>'sender_phone','is_required'=>true]]]);
        $service=app(CustomerMonthlySubscriptionService::class);
        try {
            $service->submitOffline(1,$p->id,$method,['payment_reference'=>'bad-phone','method_information'=>['sender_phone'=>'01312345678']], 'private/proof');
            $this->fail('Unsupported Egyptian phone prefix was accepted');
        } catch(\DomainException $exception) { $this->assertStringContainsString('11',$exception->getMessage()); }
        $this->assertSame('draft',$p->fresh()->status);
        $service->submitOffline(1,$p->id,$method,['payment_reference'=>'valid-phone','method_information'=>['sender_phone'=>'٠١٠ ١٢٣٤-٥٦٧٨']], 'private/proof');
        $this->assertSame('01012345678',$p->fresh()->method_information['sender_phone']);
        $this->assertSame(0,CustomerMonthlySubscription::count());
    }
    public function test_sender_form_uses_server_reference_and_quote_and_retry_does_not_duplicate_proof(): void
    {
        $p=$this->checkout();
        $method=OfflinePaymentMethod::create(['method_name'=>'InstaPay','payment_channel'=>'instapay','status'=>1,'method_informations'=>[['customer_input'=>'transaction_number','is_required'=>true]]]);
        \Illuminate\Support\Facades\Storage::fake('local');
        $request=\Illuminate\Http\Request::create('/api/v1/customer/subscription/'.$p->id.'/offline','POST',[
            'transfer_schema'=>'sender_v2','method_id'=>$method->id,'sender_phone'=>'٠١٠ ١٢٣٤-٥٦٧٨','sender_name'=>'حساب العميل','transferred_amount'=>'100.00',
        ],[],['payment_proof'=>\Illuminate\Http\UploadedFile::fake()->image('receipt.png')]);
        $request->setUserResolver(fn()=>\App\Models\User::find(1));
        $controller=app(\App\Http\Controllers\Customer\MonthlySubscriptionController::class);
        $controller->offline($request,$p->id);
        $controller->offline($request,$p->id);
        $this->assertSame('subscription-transfer-'.$p->id,$p->fresh()->payment_reference);
        $this->assertSame('01012345678',$p->fresh()->method_information['sender_phone']);
        $this->assertSame('حساب العميل',$p->fresh()->method_information['sender_name']);
        $this->assertSame('pending_review',$p->fresh()->status);
        $this->assertCount(1,\Illuminate\Support\Facades\Storage::disk('local')->allFiles());
        $this->assertSame(0,CustomerMonthlySubscription::count());
    }
    private function checkout(?string $key=null): CustomerSubscriptionPayment {return app(CustomerMonthlySubscriptionService::class)->checkout(1,$key ?? (string)Str::uuid());}
    private function offline(CustomerSubscriptionPayment $p): void {
        $method=OfflinePaymentMethod::create(['method_name'=>'InstaPay','payment_channel'=>'instapay','method_fields'=>[['input_name'=>'account','input_data'=>'test@instapay']]]);
        app(CustomerMonthlySubscriptionService::class)->submitOffline(1,$p->id,$method,['payment_reference'=>'transfer-'.$p->id], 'private/proof.enc');
    }

    public function test_price_is_not_inferred_and_payment_disabled_until_configured(): void {
        DB::table('monthly_subscription_prices')->delete();
        $this->assertFalse(app(CustomerMonthlySubscriptionService::class)->status(1)['payment_available']);
        $this->expectException(\DomainException::class); $this->checkout();
    }
    public function test_one_open_quote_from_two_devices_and_retry_are_the_same(): void {
        $first=$this->checkout(); $second=$this->checkout();
        $this->assertSame($first->id,$second->id);$this->assertSame(1,CustomerSubscriptionPayment::count());
    }
    public function test_wallet_debit_and_approval_are_atomic_and_idempotent(): void {
        $p=$this->checkout(); $s=app(CustomerMonthlySubscriptionService::class);
        $s->payFromWallet(1,$p->id);$s->payFromWallet(1,$p->id);
        $this->assertEquals(200,DB::table('users')->where('id',1)->value('wallet_balance'));
        $this->assertEquals(300,DB::table('users')->where('id',2)->value('wallet_balance'));
        $this->assertSame(1,DB::table('wallet_transactions')->count());$this->assertSame(1,CustomerMonthlySubscription::count());
        $this->assertTrue($s->status(1)['active']);$this->assertSame(30,$s->status(1)['remaining_days']);
    }
    public function test_insufficient_balance_leaves_quote_and_no_debit_or_activation(): void {
        DB::table('users')->where('id',1)->update(['wallet_balance'=>20]);$p=$this->checkout();
        try {app(CustomerMonthlySubscriptionService::class)->payFromWallet(1,$p->id);$this->fail();} catch(\DomainException $e) {$this->assertStringContainsString('لا يكفي',$e->getMessage());}
        $this->assertSame('draft',$p->fresh()->status);$this->assertSame(0,CustomerMonthlySubscription::count());$this->assertSame(0,DB::table('wallet_transactions')->count());
    }
    public function test_quote_amount_and_currency_survive_price_changes(): void {
        $p=$this->checkout();DB::table('monthly_subscription_prices')->where('id',1)->update(['customer_price'=>200]);
        $this->offline($p);app(CustomerMonthlySubscriptionService::class)->review($p->id,'approve',9,null);
        $this->assertEquals(100,$p->fresh()->quoted_amount);$this->assertEquals(100,$p->fresh()->wallet_debit_amount);$this->assertSame('EGP',$p->fresh()->currency_code);
    }
    public function test_submission_never_activates_and_approval_starts_exactly_thirty_days(): void {
        $p=$this->checkout();$this->offline($p);$s=app(CustomerMonthlySubscriptionService::class);
        $this->assertFalse($s->status(1)['active']);$this->assertSame('pending_review',$p->fresh()->status);
        $this->travel(5)->days();$approvedAt=now();$s->review($p->id,'approve',9,null);$sub=CustomerMonthlySubscription::first();
        $this->assertTrue($sub->starts_at->equalTo($approvedAt));$this->assertSame(30*86400,$sub->expires_at->timestamp-$sub->starts_at->timestamp);
        $this->travelBack();
    }
    public function test_duplicate_review_does_not_restart_subscription(): void {
        $p=$this->checkout();$this->offline($p);$s=app(CustomerMonthlySubscriptionService::class);$s->review($p->id,'approve',9,null);$expiry=CustomerMonthlySubscription::first()->expires_at;
        $this->travel(2)->days();$s->review($p->id,'approve',10,null);$this->assertSame(1,CustomerMonthlySubscription::count());$this->assertTrue($expiry->equalTo(CustomerMonthlySubscription::first()->expires_at));$this->travelBack();
    }
    public function test_expiry_is_enforced_without_scheduler_and_ceil_days(): void {
        $p=$this->checkout();$s=app(CustomerMonthlySubscriptionService::class);$s->payFromWallet(1,$p->id);
        $this->travel(29)->days();$this->travel(1)->hours();$this->assertSame(1,$s->status(1)['remaining_days']);
        $this->travel(23)->hours();$this->assertFalse($s->status(1)['active']);$this->assertSame('expired',$s->status(1)['status']);$this->assertSame(0,$s->status(1)['remaining_days']);$this->travelBack();
    }
    public function test_early_payment_restarts_thirty_days_does_not_extend_old_expiry(): void {
        $s=app(CustomerMonthlySubscriptionService::class);$p=$this->checkout();$s->payFromWallet(1,$p->id);$this->travel(5)->days();
        $second=$this->checkout();$s->payFromWallet(1,$second->id);$state=$s->status(1);
        $this->assertSame(30,$state['remaining_days']);$this->assertSame(2,CustomerMonthlySubscription::count());$this->travelBack();
    }
    public function test_rejection_requires_reason_and_does_not_activate(): void {
        $p=$this->checkout();$this->offline($p);$s=app(CustomerMonthlySubscriptionService::class);
        try {$s->review($p->id,'reject',9,'');$this->fail();}catch(\DomainException $e){$this->assertStringContainsString('سبب',$e->getMessage());}
        $s->review($p->id,'reject',9,'تحويل غير مؤكد');$this->assertFalse($s->status(1)['active']);$this->assertNull($p->fresh()->open_customer_id);$this->assertNotSame($p->id,$this->checkout()->id);
    }
    public function test_other_customer_cannot_debit_a_payment(): void {
        $p=$this->checkout();$this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);app(CustomerMonthlySubscriptionService::class)->payFromWallet(2,$p->id);
    }
    public function test_pending_transfer_cannot_be_paid_again_from_wallet(): void {
        $p=$this->checkout();$this->offline($p);$this->expectException(\DomainException::class);app(CustomerMonthlySubscriptionService::class)->payFromWallet(1,$p->id);
    }
    public function test_administrative_freeze_prevents_debit_but_not_status_access(): void {
        $p=$this->checkout();DB::table('users')->where('id',1)->update(['is_active'=>0]);$this->assertIsArray(app(CustomerMonthlySubscriptionService::class)->status(1));
        $this->expectException(\DomainException::class);app(CustomerMonthlySubscriptionService::class)->payFromWallet(1,$p->id);
    }
    public function test_disabled_offline_method_cannot_receive_submission(): void {
        $p=$this->checkout();$m=OfflinePaymentMethod::create(['method_name'=>'Old','payment_channel'=>'instapay','status'=>0]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);app(CustomerMonthlySubscriptionService::class)->submitOffline(1,$p->id,$m,['payment_reference'=>'old'],'proof.enc');
    }
    public function test_api_requires_authentication(): void {
        $this->getJson('http://localhost/api/v1/customer/subscription')->assertUnauthorized();
    }
    public function test_api_uses_authenticated_customer_and_never_exposes_proof_path(): void {
        $p=$this->checkout();$this->offline($p);
        $this->actingAs(\App\Models\User::find(1),'api')->getJson('http://localhost/api/v1/customer/subscription')
            ->assertOk()->assertJsonPath('subscription.open_payment.status','pending_review')
            ->assertJsonMissingPath('subscription.open_payment.payment_proof');
        $this->actingAs(\App\Models\User::find(2),'api')->getJson('http://localhost/api/v1/customer/subscription')->assertOk()->assertJsonPath('subscription.open_payment',null);
    }
    public function test_api_rejects_user_supplied_price_and_foreign_payment_id(): void {
        $this->actingAs(\App\Models\User::find(1),'api')->postJson('http://localhost/api/v1/customer/subscription/checkout',['request_key'=>(string)Str::uuid(),'quoted_amount'=>1])
            ->assertOk()->assertJsonPath('payment.quoted_amount','100.000');
        $p=CustomerSubscriptionPayment::first();
        $this->actingAs(\App\Models\User::find(2),'api')->postJson('http://localhost/api/v1/customer/subscription/'.$p->id.'/wallet')->assertNotFound();
    }
    public function test_transfer_amount_mismatch_leaves_payment_draft_and_subscription_inactive(): void {
        $p=$this->checkout();$method=OfflinePaymentMethod::create(['method_name'=>'InstaPay','payment_channel'=>'instapay']);
        try {app(CustomerMonthlySubscriptionService::class)->submitOffline(1,$p->id,$method,['payment_reference'=>'wrong-amount','transferred_amount'=>'99.99'],'private/proof');$this->fail();} catch(\DomainException $e) {$this->assertStringContainsString('مبلغ التحويل',$e->getMessage());}
        $this->assertSame('draft',$p->fresh()->status);$this->assertSame(0,CustomerMonthlySubscription::count());
    }
    public function test_transfer_amount_is_preserved_for_admin_review_without_activating_subscription(): void {
        $p=$this->checkout();$method=OfflinePaymentMethod::create(['method_name'=>'InstaPay','payment_channel'=>'instapay']);
        app(CustomerMonthlySubscriptionService::class)->submitOffline(1,$p->id,$method,['payment_reference'=>'correct-amount','transferred_amount'=>'100.00'],'private/proof');
        $this->assertSame('100.00',$p->fresh()->method_information['transferred_amount']);$this->assertSame('pending_review',$p->fresh()->status);$this->assertSame(0,CustomerMonthlySubscription::count());
    }
}
