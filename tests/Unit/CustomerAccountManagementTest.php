<?php

namespace Tests\Unit;

use App\Models\{User, CustomerBalanceDeposit, CustomerPurchasePackage, CustomerSubscriptionPayment};
use App\Services\{CustomerAccountDebtService, CustomerAccountAdjustmentService, CustomerBalanceDepositService, CustomerPackageWalletService, CustomerPackageCatalogService, CustomerAccountAccess};
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAccountManagementTest extends TestCase
{
    public function test_manual_confirmed_payment_repay_debt_once_and_return_credit_stays_separate(): void
    {
        $this->debt(40);$service=app(\App\Services\CustomerManualCreditService::class);
        $first=$service->credit(1,'100','تحويل متحقق',9,(string)Str::uuid(),'confirmed_payment','BANK-UNIQUE-100');
        $again=$service->credit(1,'100','تحويل متحقق',9,(string)Str::uuid(),'confirmed_payment','BANK-UNIQUE-100');
        $this->assertSame($first->id,$again->id);$this->assertEquals(160,User::find(1)->wallet_balance);$this->assertEquals(60,$first->credit);
        $debt=$this->debt(10);$service->credit(1,'20','اعتماد رد مبلغ',9,(string)Str::uuid(),'return_credit','RETURN-APPROVAL-20');
        $this->assertEquals(180,User::find(1)->wallet_balance);$this->assertEquals(0,DB::table('customer_account_debts')->where('id',$debt)->value('paid_amount'));
        $this->expectException(DomainException::class);$service->credit(2,'100','تحويل متحقق',9,(string)Str::uuid(),'confirmed_payment','BANK-UNIQUE-100');
    }
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users',function(Blueprint $t){$t->id();$t->decimal('wallet_balance',24,3)->default(0);$t->boolean('is_active')->default(true);$t->timestamp('account_deleted_at')->nullable();$t->boolean('package_auto_renew')->default(false);$t->timestamp('package_auto_renew_failed_at')->nullable();$t->timestamps();});
        Schema::create('currencies',function(Blueprint $t){$t->id();$t->string('code');$t->decimal('exchange_rate',18,6)->default(1);$t->string('symbol');$t->boolean('status')->default(true);$t->timestamps();});
        Schema::create('business_settings',function(Blueprint $t){$t->id();$t->string('type');$t->text('value');$t->timestamps();});
        Schema::create('wallet_transactions',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');foreach(['transaction_id','reference','transaction_type','payment_method'] as $s)$t->string($s);foreach(['credit','debit','admin_bonus','balance'] as $s)$t->decimal($s,24,3)->default(0);$t->timestamps();});
        Schema::create('customer_purchase_packages',function(Blueprint $t){$t->id();$t->unsignedBigInteger('customer_id')->nullable();$t->string('name');$t->text('description')->nullable();$t->decimal('package_price',18,3);$t->decimal('purchase_limit',18,3);$t->boolean('status')->default(true);$t->boolean('is_custom')->default(false);$t->integer('sort_order')->default(0);$t->timestamps();});
        foreach (['Silver','Gold','Diamond'] as $index=>$name) CustomerPurchasePackage::create(['name'=>$name,'package_price'=>10*($index+1),'purchase_limit'=>1000*($index+1)]);
        foreach(['2026_10_10_200000_create_customer_monthly_subscriptions.php','2026_10_12_100000_customer_monthly_package_terms.php','2026_10_12_160000_snapshot_customer_increase_quantity.php','2026_10_14_100000_customer_package_wallet_policy.php','2026_11_06_120000_create_customer_account_management.php'] as $file) (require database_path('migrations/'.$file))->up();
        (require database_path('migrations/2026_09_06_000001_create_customer_balance_deposits_table.php'))->up();
        DB::table('currencies')->insert(['id'=>1,'code'=>'EGP','symbol'=>'EGP']);
        foreach(['currency_model'=>'single_currency','system_default_currency'=>'1','wallet_status'=>'1'] as $key=>$value)DB::table('business_settings')->insert(['type'=>$key,'value'=>$value]);
        DB::table('users')->insert([['id'=>1,'wallet_balance'=>100,'is_active'=>1],['id'=>2,'wallet_balance'=>70,'is_active'=>1]]);
        config(['customer_accounts.debt_allocation'=>'oldest_first']);
    }

    private function debt(int $amount, int $customer=1): int
    {
        return app(CustomerAccountDebtService::class)->create($customer,(string)$amount,'سبب موثق',9,(string)Str::uuid());
    }

    private function deposit(int $amount): CustomerBalanceDeposit
    {
        return CustomerBalanceDeposit::create(['customer_id'=>1,'wallet_type'=>'purchase','amount'=>$amount,'submitted_amount'=>$amount,'currency_code'=>'EGP','offline_payment_method_id'=>1,'method_name'=>'تحويل','request_key'=>(string)Str::uuid(),'payment_reference'=>(string)Str::uuid(),'payment_proof'=>'test-only.enc','status'=>'pending']);
    }

    public function test_pending_rejected_and_repeated_deposit_never_duplicate_money(): void
    {
        $this->debt(30);$pending=$this->deposit(100);
        $this->assertEquals(100,User::find(1)->wallet_balance);
        app(CustomerBalanceDepositService::class)->review($pending,'reject',9,'التحويل غير مستلم');
        $this->assertEquals(100,User::find(1)->wallet_balance);
        $this->assertSame(0,DB::table('customer_debt_repayments')->count());
        $approved=$this->deposit(100);app(CustomerBalanceDepositService::class)->review($approved,'approve',9,null);
        $this->assertEquals(170,User::find(1)->wallet_balance);
        $this->assertEquals(70,DB::table('wallet_transactions')->value('credit'));
        try {app(CustomerBalanceDepositService::class)->review($approved,'approve',9,null);$this->fail('Duplicate approved');} catch(DomainException $e){$this->assertSame('deposit_already_reviewed',$e->getMessage());}
        $this->assertSame(1,DB::table('wallet_transactions')->count());
        $this->assertEquals(70,User::find(2)->wallet_balance);
    }

    public function test_oldest_debts_settle_first_and_partial_repayment_preserves_remaining_and_history(): void
    {
        $old=$this->debt(60);$new=$this->debt(80);
        app(CustomerBalanceDepositService::class)->review($this->deposit(100),'approve',9,null);
        $this->assertEquals(60,DB::table('customer_account_debts')->where('id',$old)->value('paid_amount'));
        $this->assertEquals(40,DB::table('customer_account_debts')->where('id',$new)->value('paid_amount'));
        $this->assertEquals(100,User::find(1)->wallet_balance);
        app(CustomerAccountDebtService::class)->archive(1,$old,9);
        $this->assertNotNull(DB::table('customer_account_debts')->where('id',$old)->value('archived_at'));
        $this->assertSame(2,DB::table('customer_debt_repayments')->count());
        $this->expectException(DomainException::class);app(CustomerAccountDebtService::class)->archive(1,$new,9);
    }

    public function test_adjustments_are_idempotent_and_do_not_repay_debts(): void
    {
        $id=$this->debt(50);$key=(string)Str::uuid();$service=app(CustomerAccountAdjustmentService::class);
        $a=$service->credit(1,'25.125','تسوية إدارية موثقة',9,$key);$b=$service->credit(1,'25.125','تسوية إدارية موثقة',9,$key);
        $this->assertSame($a->id,$b->id);$this->assertEquals(125.125,User::find(1)->wallet_balance);
        $this->assertEquals(0,DB::table('customer_account_debts')->where('id',$id)->value('paid_amount'));
        $this->assertSame(1,DB::table('customer_account_actions')->count());
        $this->expectException(DomainException::class);$service->credit(2,'25.125','تسوية إدارية موثقة',9,$key);
    }

    public function test_verified_digital_funding_also_recovers_debt_and_preserves_net_credit(): void
    {
        Schema::create('add_fund_bonus_categories',function(Blueprint $t){$t->id();$t->boolean('is_active')->default(false);$t->date('start_date_time');$t->date('end_date_time');$t->decimal('min_add_money_amount',24,3);});
        $id=$this->debt(20);
        DB::transaction(fn()=>\App\Utils\CustomerManager::create_wallet_transaction(1,50,'add_fund','add_funds_to_wallet',['id'=>'digital-test','payment_method'=>'verified_gateway']));
        $this->assertEquals(130,User::find(1)->wallet_balance);
        $this->assertEquals(30,DB::table('wallet_transactions')->value('credit'));
        $this->assertEquals(20,DB::table('customer_account_debts')->where('id',$id)->value('paid_amount'));
    }

    public function test_failed_wallet_write_rolls_back_debt_and_deposit_together(): void
    {
        $id=$this->debt(50);$deposit=$this->deposit(100);
        DB::statement("CREATE TRIGGER reject_wallet BEFORE INSERT ON wallet_transactions BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        try {app(CustomerBalanceDepositService::class)->review($deposit,'approve',9,null);$this->fail('Write must fail');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('synthetic failure',$e->getMessage());}
        $this->assertSame('pending',$deposit->fresh()->status);$this->assertEquals(100,User::find(1)->wallet_balance);
        $this->assertEquals(0,DB::table('customer_account_debts')->where('id',$id)->value('paid_amount'));
        $this->assertSame(0,DB::table('customer_debt_repayments')->count());
    }

    public function test_closed_account_cannot_receive_adjustment_and_unauthenticated_permissions_fail(): void
    {
        $this->assertFalse(CustomerAccountAccess::allows('wallet_adjust'));
        DB::table('users')->where('id',1)->update(['account_deleted_at'=>now()]);
        $this->expectException(DomainException::class);app(CustomerAccountAdjustmentService::class)->credit(1,'10','سبب',9,(string)Str::uuid());
    }

    public function test_admin_assisted_subscription_uses_same_atomic_wallet_transaction_and_records_source_once(): void
    {
        $eligibility=\Mockery::mock(\App\Services\CustomerPurchaseEligibilityService::class);
        $eligibility->shouldReceive('assertPackagePurchaseAvailable')->once();$eligibility->shouldReceive('reevaluateCustomer')->once();
        app()->instance(\App\Services\CustomerPurchaseEligibilityService::class,$eligibility);
        $package=CustomerPurchasePackage::find(1);$key=(string)Str::uuid();
        $input=['request_key'=>$key,'package_id'=>1,'operation'=>'purchase','expected_revision'=>0,'expected_amount'=>10,'quote_token'=>app(CustomerPackageCatalogService::class)->token($package,'package')];
        $context=['admin_id'=>9,'source'=>'support','reason'=>'طلب اشتراك موثق','support_reference'=>'ticket:123'];
        $payment=app(CustomerPackageWalletService::class)->transact(1,$input,$context);
        app(CustomerPackageWalletService::class)->transact(1,$input,$context);
        $this->assertEquals(90,User::find(1)->wallet_balance);$this->assertSame('approved',$payment->status);
        $this->assertSame(1,DB::table('wallet_transactions')->count());
        $this->assertSame('ticket:123',DB::table('customer_account_actions')->value('support_reference'));
        $this->assertSame(1,DB::table('customer_account_actions')->count());
        $this->assertEquals(1000,$payment->allowance_after);
    }

    public function test_customer_workspace_queries_and_renders_each_tab_without_vendor_accounts(): void
    {
        if(!defined('DOMAIN_POINTED_DIRECTORY'))define('DOMAIN_POINTED_DIRECTORY','public');
        session()->put(['local'=>'ar','direction'=>'rtl']);
        Schema::table('users',function(Blueprint $t){foreach(['name','f_name','l_name','email','phone'] as $field)$t->string($field)->nullable();});
        Schema::create('sellers',function(Blueprint $t){$t->id();foreach(['f_name','l_name','email','phone','status'] as $field)$t->string($field)->nullable();$t->timestamps();});
        Schema::create('shops',function(Blueprint $t){$t->id();$t->unsignedBigInteger('seller_id');$t->string('name')->nullable();$t->string('contact')->nullable();});
        Schema::create('products',function(Blueprint $t){$t->id();$t->string('added_by');$t->integer('user_id');$t->boolean('status');$t->integer('request_status');});
        Schema::create('orders',function(Blueprint $t){$t->id();$t->integer('customer_id');$t->boolean('is_guest')->default(false);$t->string('seller_is')->nullable();$t->integer('seller_id')->nullable();$t->decimal('order_amount',24,3)->default(0);$t->timestamps();});
        Schema::create('order_admin_purchase_refunds',function(Blueprint $t){$t->id();$t->integer('customer_id');$t->integer('order_id');$t->string('reason');$t->string('status');$t->decimal('amount',24,3);$t->timestamp('due_at')->nullable();$t->timestamp('completed_at')->nullable();$t->string('reference')->nullable();$t->timestamps();});
        Schema::create('refund_requests',function(Blueprint $t){$t->id();$t->integer('customer_id');$t->integer('order_id');$t->string('status');$t->decimal('amount',24,3);$t->timestamps();});
        DB::table('users')->where('id',1)->update(['f_name'=>'عميل اختبار واضح','l_name'=>'المعاينة','email'=>'fixture@example.test','phone'=>'01012345678']);
        DB::table('sellers')->insert(['id'=>1,'f_name'=>'VENDOR MUST NOT APPEAR','email'=>'vendor@example.test','status'=>'approved']);
        $admin=new \App\Models\Admin();$admin->id=9;$admin->admin_role_id=1;$admin->setRelation('role',new \App\Models\AdminRole(['status'=>true,'module_access'=>'[]']));auth('admin')->setUser($admin);
        $archived=$this->debt(10);$this->debt(15);
        app(CustomerAccountDebtService::class)->allocate(1,'25','fixture-debt-settlement');
        app(CustomerAccountDebtService::class)->archive(1,$archived,9);
        $this->debt(40);$this->deposit(25);
        app(CustomerAccountAdjustmentService::class)->credit(1,'10','تسوية للمعاينة',9,(string)Str::uuid());
        $fixturePayment=CustomerSubscriptionPayment::create(['customer_id'=>1,'request_key'=>(string)Str::uuid(),'quoted_amount'=>10,'wallet_debit_amount'=>10,'currency_code'=>'EGP','status'=>'approved','package_id'=>1,'terms_name'=>'Silver','purchase_kind'=>'activation','payment_method'=>'wallet_balance','payment_reference'=>'fixture-package-1','approved_at'=>now(),'subscription_expires_at'=>now()->addDays(29),'terms_snapshot'=>['auto_renew'=>false,'source'=>'self_service']]);
        \App\Models\CustomerMonthlySubscription::create(['customer_id'=>1,'payment_id'=>$fixturePayment->id,'package_id'=>1,'package_name'=>'Silver','package_price'=>10,'granted_allowance'=>1000,'starts_at'=>now()->subDay(),'expires_at'=>now()->addDays(29)]);
        $controller=app(\App\Http\Controllers\Admin\Customer\CustomerAccountsController::class);
        $source=file_get_contents(resource_path('views/admin-views/customer/accounts/index.blade.php'));
        $start=strpos($source,"@section('content')")+strlen("@section('content')");$end=strrpos($source,'@endsection');
        $directory=storage_path('app/customer-accounts-qa');if(!is_dir($directory))mkdir($directory,0755,true);
        foreach(['list'=>['overview'],'payments'=>['active','settled','history','overview','requests','transactions','refunds'],'debts'=>['active','settled','history'],'subscriptions'=>['current','subscriptions','actions']] as $page=>$tabs) foreach($tabs as $tab) {
            $request=\Illuminate\Http\Request::create('/admin/customer/accounts/'.$page,'GET',['customer_id'=>1,'tab'=>$tab]);app()->instance('request',$request);
            $view=$controller->index($request,$page);
            $html=\Illuminate\Support\Facades\Blade::render(substr($source,$start,$end-$start),$view->getData()+['errors'=>new \Illuminate\Support\ViewErrorBag()]);
            $this->assertStringNotContainsString('VENDOR MUST NOT APPEAR',$html);$this->assertStringContainsString('C000001',$html);
            $this->assertStringNotContainsString('class="ca-navigation"',$html);
            if($page==='subscriptions' && $tab==='current') {$this->assertSame(1,$view->getData()['currentSubscriptions']->total());$this->assertStringContainsString('Silver',$html);}
            foreach(['light','dark'] as $theme) {
                $document='<!doctype html><html lang="ar" dir="rtl" data-ba-admin-theme="'.$theme.'"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/backend/libs/fonts/inter/inter.css"><link rel="stylesheet" href="/assets/backend/webfonts/uicons-regular-rounded.css"><link rel="stylesheet" href="/assets/backend/libs/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="/assets/backend/libs/bootstrap/bootstrap.rtl.css"><link rel="stylesheet" href="/assets/backend/admin/css/style.css"><link rel="stylesheet" href="/assets/back-end/css/admin-v2.css"><link rel="stylesheet" href="/assets/back-end/css/admin-dark-mode.css"><link rel="stylesheet" href="/assets/back-end/css/customer-accounts.css"><body class="app-v2" style="display:block;min-height:100vh"><p style="padding:8px">معاينة محلية ببيانات اختبار — ليست حسابات فعلية</p>'.$html.'<script src="/assets/back-end/js/customer-accounts.js"></script></body></html>';
                file_put_contents($directory.'/'.$page.'-'.$tab.'-'.$theme.'.html',$document);
            }
        }
        $request=\Illuminate\Http\Request::create('/admin/customer/accounts/list','GET',['q'=>'01012345678']);app()->instance('request',$request);
        $this->assertSame(1,$controller->index($request,'list')->getData()['accounts']->total());
        $beforeReads=[User::find(1)->wallet_balance,DB::table('wallet_transactions')->count(),DB::table('customer_account_actions')->count()];
        foreach(['C000001','01012345678','عميل اختبار'] as $term) {
            $request=\Illuminate\Http\Request::create('/admin/customer/accounts/list','GET',['q'=>$term]);app()->instance('request',$request);
            $this->assertSame(1,$controller->index($request,'list')->getData()['accounts']->total());
        }
        $request=\Illuminate\Http\Request::create('/admin/customer/accounts/list','GET',['q'=>'M000001']);app()->instance('request',$request);
        $this->assertSame(0,$controller->index($request,'list')->getData()['accounts']->total());
        $request=\Illuminate\Http\Request::create('/admin/customer/accounts/list','GET',['customer_id'=>1,'focus_action'=>'password']);app()->instance('request',$request);
        $this->assertSame('password',$controller->index($request,'list')->getData()['focusedAction']);
        $request=\Illuminate\Http\Request::create('/admin/customer/accounts/debts','GET',['tab'=>'transactions','customer_id'=>1]);app()->instance('request',$request);
        $this->assertSame('active',$controller->index($request,'debts')->getData()['tab']);
        $request=\Illuminate\Http\Request::create('/admin/customer/accounts/subscriptions','GET',[]);app()->instance('request',$request);
        $current=$controller->index($request,'subscriptions')->getData();$this->assertSame('current',$current['tab']);$this->assertSame(1,$current['currentSubscriptions']->total());
        $this->assertSame($beforeReads,[User::find(1)->wallet_balance,DB::table('wallet_transactions')->count(),DB::table('customer_account_actions')->count()]);
    }

    public function test_refund_credit_reuses_original_processor_and_is_not_repeated_or_used_to_pay_debt(): void
    {
        Schema::create('orders',function(Blueprint $t){$t->id();$t->integer('customer_id');});
        Schema::create('refund_requests',function(Blueprint $t){$t->id();$t->integer('customer_id');$t->integer('order_id');$t->string('status');$t->decimal('amount',24,3);$t->timestamps();});
        Schema::create('refund_transactions',function(Blueprint $t){$t->id();$t->integer('refund_id');});
        DB::table('orders')->insert(['id'=>1,'customer_id'=>1]);
        DB::table('refund_requests')->insert(['id'=>1,'customer_id'=>1,'order_id'=>1,'status'=>'approved','amount'=>20]);
        $debt=$this->debt(50);
        $processor=\Mockery::mock(\App\Http\Controllers\Admin\Order\RefundController::class);
        $processor->shouldReceive('updateRefundStatus')->once()->andReturnUsing(function($request){
            $this->assertSame('purchase_wallet',$request->input('payment_method'));
            DB::table('refund_requests')->where('id',1)->update(['status'=>'refunded']);
            DB::table('refund_transactions')->insert(['refund_id'=>1]);
            return response()->json(['success'=>'تم الرد']);
        });app()->instance(\App\Http\Controllers\Admin\Order\RefundController::class,$processor);
        $service=app(\App\Services\CustomerAccountRefundService::class);$key=(string)Str::uuid();
        $first=$service->credit(1,1,9,'مرتجع معتمد',$key);$second=$service->credit(1,1,9,'مرتجع معتمد',$key);
        $this->assertSame($first->id,$second->id);$this->assertEquals(120,User::find(1)->wallet_balance);
        $this->assertSame(1,DB::table('refund_transactions')->count());$this->assertSame(1,DB::table('wallet_transactions')->count());
        $this->assertEquals(0,DB::table('customer_account_debts')->where('id',$debt)->value('paid_amount'));
    }

    public function test_people_permission_does_not_grant_financial_write_access(): void
    {
        $admin=new \App\Models\Admin();$admin->id=11;$admin->admin_role_id=2;$admin->setRelation('role',new \App\Models\AdminRole(['status'=>true,'module_access'=>'["people"]']));auth('admin')->setUser($admin);
        $this->assertFalse(CustomerAccountAccess::allows('wallet_adjust'));
        $this->assertFalse(CustomerAccountAccess::allows('debts_manage'));
        $this->assertFalse(CustomerAccountAccess::allows('subscriptions_manage'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        CustomerAccountAccess::authorize('wallet_adjust');
    }
}
