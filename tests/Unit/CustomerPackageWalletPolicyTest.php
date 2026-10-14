<?php
namespace Tests\Unit;

use App\Models\{User,CustomerPurchasePackage,CustomerMonthlySubscription,CustomerSubscriptionPayment};
use App\Services\{CustomerPackageCatalogService,CustomerMonthlySubscriptionService};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerPackageWalletPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();$this->travelTo(\Carbon\Carbon::parse('2026-10-01 10:00:00','Africa/Cairo'));
        Schema::create('users',function(Blueprint $t){$t->id();$t->decimal('wallet_balance',24,3)->default(0);$t->boolean('is_active')->default(true);$t->timestamps();});
        Schema::create('currencies',function(Blueprint $t){$t->id();$t->string('code');$t->decimal('exchange_rate',18,6)->default(1);$t->string('symbol');$t->boolean('status')->default(true);$t->timestamps();});
        Schema::create('business_settings',function(Blueprint $t){$t->id();$t->string('type');$t->text('value');$t->timestamps();});
        Schema::create('wallet_transactions',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');foreach(['transaction_id','reference','transaction_type','payment_method'] as $s)$t->string($s);foreach(['credit','debit','admin_bonus','balance'] as $s)$t->decimal($s,24,3)->default(0);$t->timestamps();});
        Schema::create('customer_purchase_packages',function(Blueprint $t){$t->id();$t->unsignedBigInteger('customer_id')->nullable();$t->string('name');$t->text('description')->nullable();$t->decimal('package_price',18,3);$t->decimal('purchase_limit',18,3);$t->boolean('status')->default(true);$t->boolean('is_custom')->default(false);$t->integer('sort_order')->default(0);$t->timestamps();});
        (require database_path('migrations/2026_10_10_200000_create_customer_monthly_subscriptions.php'))->up();
        (require database_path('migrations/2026_10_12_100000_customer_monthly_package_terms.php'))->up();
        (require database_path('migrations/2026_10_12_160000_snapshot_customer_increase_quantity.php'))->up();
        Schema::table('customer_purchase_packages',function(Blueprint $t){$t->string('duration_unit')->default('day');$t->unsignedInteger('duration_value')->default(30);$t->unsignedBigInteger('created_by_admin_id')->nullable();$t->unsignedBigInteger('updated_by_admin_id')->nullable();});
        foreach([['Silver',100,3000],['Gold',200,5000],['Diamond',300,10000],['begad',10,100],['Test User',10,100]] as [$name,$price,$limit])CustomerPurchasePackage::create(['name'=>$name,'package_price'=>$price,'purchase_limit'=>$limit]);
        // Preserve the historical row and archive its unpaid draft before cancellation.
        CustomerSubscriptionPayment::create(['customer_id'=>2,'open_customer_id'=>2,'request_key'=>(string)Str::uuid(),'quoted_amount'=>10,'wallet_debit_amount'=>10,'currency_code'=>'EGP','package_id'=>4]);
        (require database_path('migrations/2026_10_14_100000_customer_package_wallet_policy.php'))->up();
        DB::table('currencies')->insert(['id'=>1,'code'=>'EGP','symbol'=>'EGP']);
        foreach(['currency_model'=>'single_currency','system_default_currency'=>'1','wallet_status'=>'1'] as $key=>$value)DB::table('business_settings')->insert(['type'=>$key,'value'=>$value]);
        DB::table('users')->insert([['id'=>1,'wallet_balance'=>1000,'is_active'=>1],['id'=>2,'wallet_balance'=>1000,'is_active'=>1]]);
        DB::table('monthly_subscription_prices')->insert(['id'=>1,'customer_price'=>100,'currency_code'=>'EGP']);
    }

    public function test_catalog_retains_only_three_tiers_without_deleting_history_or_charging_old_draft(): void
    {
        $this->assertSame(['Silver','Gold','Diamond'],app(CustomerPackageCatalogService::class)->available()->pluck('name')->all());
        $this->assertSame(5,CustomerPurchasePackage::count());
        $this->assertSame('cancelled',CustomerSubscriptionPayment::first()->status);
        $this->assertSame('draft',json_decode(DB::table('customer_financial_archives')->value('snapshot'),true)['status']);
        $this->assertEquals(1,CustomerSubscriptionPayment::first()->wallet_policy_version);
        $this->assertSame(0,DB::table('wallet_transactions')->count());
        $this->assertNull(CustomerPurchasePackage::first()->renewal_price);
        CustomerPurchasePackage::whereKey(4)->update(['status'=>1]);
        $this->assertSame(3,app(CustomerPackageCatalogService::class)->available()->count());
    }

    public function test_policy_migration_preserves_submitted_reviews_and_payment_evidence_and_can_resume(): void
    {
        $base=['customer_id'=>2,'quoted_amount'=>10,'wallet_debit_amount'=>10,'currency_code'=>'EGP','package_id'=>4];
        $review=CustomerSubscriptionPayment::create($base+['request_key'=>(string)Str::uuid(),'status'=>'pending_review','submitted_at'=>now()]);
        $proof=CustomerSubscriptionPayment::create($base+['request_key'=>(string)Str::uuid(),'payment_proof'=>'historical-proof.png']);
        $paid=CustomerSubscriptionPayment::create($base+['request_key'=>(string)Str::uuid()]);
        DB::table('wallet_transactions')->insert(['user_id'=>2,'transaction_id'=>'historical-wallet','reference'=>'customer-monthly-subscription-'.$paid->id,'transaction_type'=>'monthly_subscription','payment_method'=>'wallet_balance','debit'=>10,'balance'=>990]);
        $migration=require database_path('migrations/2026_10_14_100000_customer_package_wallet_policy.php');
        $migration->up();$migration->up();
        $this->assertSame('pending_review',$review->fresh()->status);
        $this->assertSame('draft',$proof->fresh()->status);
        $this->assertSame('draft',$paid->fresh()->status);
        $this->assertSame(1,DB::table('customer_financial_archives')->count());
        $this->assertSame(1,DB::table('wallet_transactions')->count());
        $this->assertEquals(1000,User::find(2)->wallet_balance);
    }

    public function test_quote_token_changes_when_admin_changes_price_limit_or_renewal_fee(): void
    {
        $catalog=app(CustomerPackageCatalogService::class);$package=CustomerPurchasePackage::find(1);
        $token=$catalog->token($package,'package');$package->renewal_price=25;
        $this->assertNotSame($token,$catalog->token($package,'package'));
        $package->save();$this->assertEquals(25,CustomerPurchasePackage::find(1)->renewal_price);
    }

    private function purchase(array $changes=[]): array
    {
        $package=CustomerPurchasePackage::find(1);
        return array_replace(['request_key'=>(string)Str::uuid(),'operation'=>'purchase','package_id'=>1,'quantity'=>1,
            'quote_token'=>app(CustomerPackageCatalogService::class)->token($package,'package'),'expected_amount'=>100,
            'expected_subscription_id'=>null,'expected_revision'=>0],$changes);
    }

    public function test_purchase_debits_and_activates_atomically_once_and_rejects_reuse_for_different_terms(): void
    {
        $input=$this->purchase();$service=app(\App\Services\CustomerPackageWalletService::class);
        $payment=$service->transact(1,$input);$retry=$service->transact(1,$input);
        $this->assertSame($payment->id,$retry->id);$this->assertSame('approved',$payment->status);
        $this->assertEquals(900,User::find(1)->wallet_balance);$this->assertEquals(3000,$payment->allowance_after);
        $this->assertEquals(900,$payment->wallet_balance_after);$this->assertSame(1,CustomerMonthlySubscription::count());
        $this->assertSame(1,DB::table('wallet_transactions')->count());
        $this->assertEquals($payment->id,DB::table('wallet_transactions')->value('customer_subscription_payment_id'));
        try{$service->transact(1,array_replace($input,['expected_amount'=>101]));$this->fail('Reused key changed terms');}catch(\DomainException $e){$this->assertStringContainsString('مختلف',$e->getMessage());}
    }

    public function test_insufficient_balance_stale_quote_and_disabled_or_excluded_package_never_debit(): void
    {
        $service=app(\App\Services\CustomerPackageWalletService::class);$input=$this->purchase();
        DB::table('users')->where('id',1)->update(['wallet_balance'=>99]);
        try{$service->transact(1,$input);$this->fail('Insufficient accepted');}catch(\DomainException $e){$this->assertStringContainsString('اشحن',$e->getMessage());}
        DB::table('users')->where('id',1)->update(['wallet_balance'=>1000]);
        CustomerPurchasePackage::whereKey(1)->update(['package_price'=>101]);
        try{$service->transact(1,$input);$this->fail('Stale price accepted');}catch(\DomainException $e){$this->assertStringContainsString('تغير',$e->getMessage());}
        $excluded=CustomerPurchasePackage::find(4);$excluded->update(['status'=>1]);
        try{$service->transact(1,$this->purchase(['package_id'=>4,'quote_token'=>app(CustomerPackageCatalogService::class)->token($excluded,'package'),'expected_amount'=>10]));$this->fail('Excluded package accepted');}catch(\DomainException $e){$this->assertStringContainsString('تغير',$e->getMessage());}
        $this->assertSame(0,DB::table('wallet_transactions')->count());$this->assertSame(0,CustomerMonthlySubscription::count());
        $this->assertSame(1,CustomerSubscriptionPayment::count());$this->assertEquals(1000,User::find(1)->wallet_balance);
    }

    public function test_increase_keeps_expiry_and_revision_blocks_a_second_key_from_the_same_screen(): void
    {
        $service=app(\App\Services\CustomerPackageWalletService::class);$service->transact(1,$this->purchase());
        $sub=CustomerMonthlySubscription::first();$expiry=$sub->expires_at->toIso8601String();
        $id=DB::table('customer_credit_offers')->insertGetId(['name'=>'More','price'=>50,'allowance'=>1000,'active'=>1]);
        $offer=DB::table('customer_credit_offers')->find($id);
        $input=$this->purchase(['package_id'=>null,'offer_id'=>$id,'quantity'=>2,'expected_subscription_id'=>$sub->id,
            'quote_token'=>app(CustomerPackageCatalogService::class)->token($offer,'offer'),'expected_amount'=>100]);
        $payment=$service->transact(1,$input);
        $this->assertSame('increase',$payment->purchase_kind);$this->assertEquals(5000,$payment->allowance_after);
        $this->assertSame($expiry,$sub->fresh()->expires_at->toIso8601String());
        try{$service->transact(1,array_replace($input,['request_key'=>(string)Str::uuid()]));$this->fail('Duplicate screen transaction accepted');}catch(\DomainException $e){$this->assertStringContainsString('حالة',$e->getMessage());}
        $this->assertEquals(800,User::find(1)->wallet_balance);$this->assertSame(2,DB::table('wallet_transactions')->count());
    }

    public function test_day_twenty_renewal_restores_three_thousand_without_starting_a_new_month_or_erasing_usage(): void
    {
        $service=app(\App\Services\CustomerPackageWalletService::class);$service->transact(1,$this->purchase());
        $sub=CustomerMonthlySubscription::first();$start=$sub->starts_at->toIso8601String();$expiry=$sub->expires_at->toIso8601String();
        $sub->update(['committed_allowance'=>3000]);CustomerPurchasePackage::whereKey(1)->update(['renewal_price'=>25]);
        $this->travel(19)->days();
        $input=$this->purchase(['operation'=>'renew_limit','expected_amount'=>25,'expected_subscription_id'=>$sub->id]);
        $payment=$service->transact(1,$input);$service->transact(1,$input);
        $this->assertSame('renew_limit',$payment->purchase_kind);$this->assertEquals(0,$payment->allowance_before);$this->assertEquals(3000,$payment->allowance_after);
        $this->assertSame($start,$sub->fresh()->starts_at->toIso8601String());$this->assertSame($expiry,$sub->fresh()->expires_at->toIso8601String());
        $this->assertEquals(3000,$sub->fresh()->committed_allowance);$this->assertSame(1,CustomerMonthlySubscription::count());
        $this->assertEquals(875,User::find(1)->wallet_balance);$this->assertSame(2,DB::table('wallet_transactions')->count());
        $this->travelTo($sub->fresh()->expires_at->copy()->addSecond());
        $this->assertEquals(0,app(\App\Services\CustomerMonthlyAllowanceService::class)->summary(1)['available_limit']);
        try{$service->transact(1,array_replace($input,['request_key'=>(string)Str::uuid()]));$this->fail('Expired renewal accepted');}catch(\DomainException $e){$this->assertNotEmpty($e->getMessage());}
        $this->assertEquals(875,User::find(1)->wallet_balance);
    }

    public function test_partial_remaining_is_restored_to_base_and_full_available_limit_cannot_be_charged_again(): void
    {
        $service=app(\App\Services\CustomerPackageWalletService::class);$service->transact(1,$this->purchase());
        $sub=CustomerMonthlySubscription::first();$sub->update(['committed_allowance'=>2500]);
        CustomerPurchasePackage::whereKey(1)->update(['renewal_price'=>25]);
        $payment=$service->transact(1,$this->purchase(['operation'=>'renew_limit','expected_amount'=>25,'expected_subscription_id'=>$sub->id]));
        $this->assertEquals(500,$payment->allowance_before);$this->assertEquals(2500,$payment->granted_allowance);$this->assertEquals(3000,$payment->allowance_after);
        try{$service->transact(1,$this->purchase(['operation'=>'renew_limit','expected_amount'=>25,'expected_subscription_id'=>$sub->id,'expected_revision'=>1]));$this->fail('Unnecessary fee charged');}catch(\DomainException $e){$this->assertStringContainsString('بالكامل',$e->getMessage());}
        $this->assertEquals(875,User::find(1)->wallet_balance);
    }

    public function test_upgrade_keeps_original_expiry_and_charges_full_higher_price(): void
    {
        $service=app(\App\Services\CustomerPackageWalletService::class);$service->transact(1,$this->purchase());$sub=CustomerMonthlySubscription::first();
        $expiry=$sub->expires_at->toIso8601String();$this->travel(5)->days();$gold=CustomerPurchasePackage::find(2);
        $payment=$service->transact(1,$this->purchase(['package_id'=>2,'quote_token'=>app(CustomerPackageCatalogService::class)->token($gold,'package'),'expected_amount'=>200,'expected_subscription_id'=>$sub->id]));
        $this->assertSame('upgrade',$payment->purchase_kind);$this->assertEquals(200,$payment->quoted_amount);$this->assertEquals(700,User::find(1)->wallet_balance);
        $this->assertSame(2,CustomerMonthlySubscription::count());$this->assertSame(25,app(CustomerMonthlySubscriptionService::class)->status(1)['remaining_days']);
        $this->assertSame($expiry,CustomerMonthlySubscription::latest('id')->first()->expires_at->toIso8601String());
        $this->assertTrue($sub->fresh()->expires_at->lte(now()));
    }

    public function test_api_uses_authenticated_owner_and_closes_all_old_payment_paths(): void
    {
        $this->actingAs(User::find(1),'api')->postJson('http://localhost/api/v1/customer/subscription/transact',$this->purchase(['customer_id'=>2,'quoted_amount'=>1]))->assertOk()->assertJsonPath('payment.customer_id',1)->assertJsonPath('payment.status','approved');
        $this->assertEquals(1000,User::find(2)->wallet_balance);
        foreach(['checkout','1/wallet','1/offline'] as $path)$this->postJson('http://localhost/api/v1/customer/subscription/'.$path,[])->assertStatus(410);
        $this->getJson('http://localhost/api/v1/customer/subscription')->assertOk()->assertJsonCount(3,'packages')->assertJsonCount(0,'offline_methods');
        $this->assertSame('cancelled',CustomerSubscriptionPayment::where('customer_id',2)->first()->status);
    }

    public function test_cancelled_legacy_draft_is_preserved_and_not_converted_when_a_new_operation_is_requested(): void
    {
        $legacy=CustomerSubscriptionPayment::first();
        $new=app(\App\Services\CustomerPackageWalletService::class)->transact(2,$this->purchase());
        $this->assertSame('approved',$new->status);$this->assertSame('cancelled',$legacy->fresh()->status);
        $this->assertEquals(900,User::find(2)->wallet_balance);$this->assertSame(1,DB::table('wallet_transactions')->count());
        try{app(CustomerMonthlySubscriptionService::class)->payFromWallet(2,$legacy->id);$this->fail('Legacy draft debited');}catch(\DomainException $e){$this->assertStringContainsString('السابقة',$e->getMessage());}
        $this->assertEquals(900,User::find(2)->wallet_balance);
    }

    public function test_failure_after_debit_rolls_back_subscription_payment_balance_and_ledger(): void
    {
        $this->mock(\App\Services\CustomerPaymentNotificationService::class,fn($mock)=>$mock->shouldReceive('notify')->once()->andThrow(new \RuntimeException('Synthetic notification failure')));
        try{app(\App\Services\CustomerPackageWalletService::class)->transact(1,$this->purchase());$this->fail('Synthetic failure absent');}catch(\RuntimeException $e){$this->assertSame('Synthetic notification failure',$e->getMessage());}
        $this->assertEquals(1000,User::find(1)->wallet_balance);$this->assertSame(0,CustomerMonthlySubscription::count());
        $this->assertSame(0,DB::table('wallet_transactions')->count());$this->assertSame(1,CustomerSubscriptionPayment::count());
    }

    public function test_actual_customer_and_admin_blade_render_wallet_only_controls_and_fee_configuration(): void
    {
        if(!defined('DOMAIN_POINTED_DIRECTORY'))define('DOMAIN_POINTED_DIRECTORY','public');
        app(\App\Services\CustomerPackageWalletService::class)->transact(1,$this->purchase());
        CustomerMonthlySubscription::first()->update(['committed_allowance'=>3000]);
        CustomerPurchasePackage::whereIn('id',[1,2,3])->update(['renewal_price'=>25]);
        DB::table('customer_credit_offers')->insert(['name'=>'زيادة الحد','price'=>50,'allowance'=>1000,'active'=>1]);
        auth('customer')->setUser(User::find(1));view()->share('errors',new \Illuminate\Support\ViewErrorBag());
        $request=\Illuminate\Http\Request::create('/customer/subscription');
        $view=app(\App\Http\Controllers\Customer\MonthlySubscriptionController::class)->index($request);
        $payload=$view->getData();
        $render=function(string $name,array $data){$source=file_get_contents(resource_path('views/'.$name.'.blade.php'));$start=strpos($source,"@section('content')")+strlen("@section('content')");$end=strrpos($source,'@endsection');return \Illuminate\Support\Facades\Blade::render(substr($source,$start,$end-$start),$data);};
        $customer=$render('customer-subscription/packages-wallet',$payload);
        $this->assertStringContainsString('تجديد الحد بـ25',$customer);$this->assertStringContainsString('name="operation" value="renew_limit"',$customer);
        $this->assertStringNotContainsString('subscription-methods',$customer);$this->assertStringNotContainsString('بيجاد',$customer);
        $admin=$render('admin-views/customer/monthly-packages',['packages'=>app(CustomerPackageCatalogService::class)->query()->get(),'offers'=>collect(),'tab'=>'customers']);
        $this->assertSame(3,substr_count($admin,'name="renewal_price"'));
        $directory=base_path('output/customer-stage4');if(!is_dir($directory))mkdir($directory,0777,true);
        $wrap=fn($body,$admin=false)=>'<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.($admin?'<link rel="stylesheet" href="/assets/back-end/css/bootstrap.min.css">':'<link rel="stylesheet" href="/assets/front-end/css/customer-subscription.css"><link rel="stylesheet" href="/assets/front-end/css/customer-packages.css">').'<style>body{margin:0;background:#f7f8fa;font-family:Tahoma,sans-serif}.qa-label{padding:16px;background:#1a428a;color:white}.monthly-page{margin:0 auto;padding-inline:20px}.content{padding:24px;max-width:1160px;margin:auto}</style></head><body><div class="qa-label">سيجما — معاينة معزولة ببيانات اختبار</div>'.$body.'<script src="/assets/front-end/js/customer-packages.js"></script></body></html>';
        file_put_contents($directory.'/customer.html',$wrap($customer));file_put_contents($directory.'/admin.html',$wrap($admin,true));
    }

    public function test_urgent_payment_review_renders_package_request_and_sent_state(): void
    {
        if(!defined('DOMAIN_POINTED_DIRECTORY'))define('DOMAIN_POINTED_DIRECTORY','public');
        session()->put(['local'=>'ar','direction'=>'rtl']);
        $actor = User::find(1); $actor->f_name = 'عميل المعاينة'; $actor->phone = '01000000000';
        $row = ['id'=>501,'type'=>'customer_initial_order','kind'=>'order','audience'=>'customer','status'=>'approved',
            'actor'=>$actor,'label'=>'مراجعة دفع الطلب الأول #501','amount'=>8000,'currency'=>'EGP','reference'=>'QA-501','offline'=>null,
            'customer_state'=>['is_urgent_order'=>true,'can_request_package'=>true,'package_request_sent'=>false,
                'customer'=>['onboarding_journey'=>'standard','account_activated'=>true,'subscription'=>['active'=>false]],
                'message'=>'تم اعتماد الدفع. الطلب بانتظار اختيار باقة.']];
        $source=file_get_contents(resource_path('views/admin-views/payment-reviews/index.blade.php'));
        $start=strpos($source,"@section('content')")+strlen("@section('content')");$end=strrpos($source,'@endsection');
        $render=fn($rows)=>\Illuminate\Support\Facades\Blade::render(substr($source,$start,$end-$start),['reviews'=>new \Illuminate\Pagination\LengthAwarePaginator($rows,count($rows),20)]);
        $html=$render([$row]);
        $this->assertStringContainsString('حساب استعجالي',$html);
        $this->assertStringContainsString(route('admin.payment-reviews.request-package',501),$html);
        $pendingRow=$row;
        $row['id']=502;$row['reference']='QA-502';$row['customer_state']['package_request_sent']=true;
        $sent=$render([$row]);
        $this->assertStringContainsString('تم إرسال طلب اختيار الباقة',$sent);
        $this->assertStringNotContainsString(route('admin.payment-reviews.request-package',502),$sent);
        $directory=base_path('output/customer-stage5');if(!is_dir($directory))mkdir($directory,0777,true);
        file_put_contents($directory.'/admin.html','<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/back-end/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/back-end/css/style.css"><style>body{background:#f4f7fb;font-family:Tahoma,sans-serif}.content{padding:24px;margin:auto;max-width:1400px}button{min-height:44px}.btn--primary{background:#1a428a;color:white}</style></head><body>'.$render([$pendingRow,$row]).'</body></html>');
    }

    public function test_admin_fee_save_is_returned_to_customer_and_stale_fee_cannot_charge(): void
    {
        $admin=new \App\Models\Admin();$admin->id=9;auth('admin')->setUser($admin);
        $controller=app(\App\Http\Controllers\Admin\Customer\MonthlyPackageController::class);
        $controller->save(\Illuminate\Http\Request::create('/','POST',['name'=>'Unauthorized rename','package_price'=>100,'purchase_limit'=>3000,'status'=>1,'renewal_price'=>'25.125']),1);
        $this->assertSame('Silver',CustomerPurchasePackage::find(1)->name);$this->assertEquals(25.125,CustomerPurchasePackage::find(1)->renewal_price);
        $service=app(\App\Services\CustomerPackageWalletService::class);$service->transact(1,$this->purchase());$sub=CustomerMonthlySubscription::first();$sub->update(['committed_allowance'=>3000]);
        $old=$this->purchase(['operation'=>'renew_limit','expected_amount'=>'25.125','expected_subscription_id'=>$sub->id]);
        $controller->save(\Illuminate\Http\Request::create('/','POST',['package_price'=>100,'purchase_limit'=>3000,'status'=>1,'renewal_price'=>'30.125']),1);
        try{$service->transact(1,$old);$this->fail('Changed renewal fee charged');}catch(\DomainException $e){$this->assertStringContainsString('تغير',$e->getMessage());}
        $this->assertEquals(900,User::find(1)->wallet_balance);
        $this->actingAs(User::find(1),'api')->getJson('http://localhost/api/v1/customer/subscription')->assertJsonPath('packages.0.renewal_price',30.125);
    }
}
