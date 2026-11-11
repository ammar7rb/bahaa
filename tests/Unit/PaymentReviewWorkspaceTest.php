<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\PaymentReviewController;
use App\Models\{Admin, AdminRole, CustomerBalanceDeposit};
use App\Services\AdminWorkspaceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Blade, Cache, DB, Schema, Storage};
use Tests\TestCase;

class PaymentReviewWorkspaceTest extends TestCase
{
    public function test_customer_scope_controls_rows_counts_methods_and_pagination(): void
    {
        $this->role(['people','customer_financial_view']);
        DB::table('users')->insert(['id'=>2,'f_name'=>'عميل آخر','phone'=>'01099999999']);
        $own=$this->deposit();$other=$this->deposit('paid','OTHER-BANK');$other->update(['customer_id'=>2]);
        DB::table('seller_balance_deposits')->insert(['seller_id'=>1,'source'=>'offline','status'=>'pending','amount'=>70,'currency_code'=>'EGP','transaction_reference'=>'VENDOR-1','created_at'=>now(),'updated_at'=>now()]);
        $data=$this->data(['customer_id'=>1,'audience'=>'vendor','kind'=>'balance_deposit']);
        $this->assertSame(1,$data['reviews']->total());$this->assertSame(1,(int)$data['customer']->id);
        $this->assertSame(1,$data['counts']->get('pending'));$this->assertFalse($data['methods']->contains('OTHER-BANK'));
        $this->assertSame($own->id,$data['reviews']->first()['id']);$this->assertSame('customer',$data['reviews']->first()['audience']);
        $this->assertSame(0,$this->data(['customer_id'=>1,'status'=>'approved','kind'=>'balance_deposit'])['reviews']->total());
        foreach([1,2] as $owner)DB::table('wallet_transactions')->insert(['user_id'=>$owner,'transaction_id'=>'WALLET-'.$owner,'reference'=>'WALLET-'.$owner,'transaction_type'=>'add_fund','payment_method'=>'historical','credit'=>5,'debit'=>0,'balance'=>5,'admin_bonus'=>0,'created_at'=>now(),'updated_at'=>now()]);
        $wallet=$this->data(['customer_id'=>1,'kind'=>'wallet_transaction']);$this->assertSame(1,$wallet['reviews']->total());$this->assertTrue($wallet['reviews']->first()['read_only']);$this->assertSame('WALLET-1',$wallet['reviews']->first()['reference']);
        $data=$this->data(['customer_id'=>1,'kind'=>'balance_deposit']);
        $source=file_get_contents(resource_path('views/admin-views/payment-reviews/index.blade.php'));
        $start=strpos($source,"@section('content')")+strlen("@section('content')");$end=strrpos($source,'@endsection');
        $html=Blade::render(substr($source,$start,$end-$start),$data);
        $this->assertStringContainsString('مدفوعات العميل C000001',$html);
        $this->assertStringNotContainsString('مراجعة موحدة',$html);
        $this->assertStringNotContainsString('name="audience" class="form-select"',$html);
        $this->assertStringContainsString('name="customer_id" value="1"',$html);
        $directory=storage_path('app/customer-accounts-qa');if(!is_dir($directory))mkdir($directory,0755,true);
        foreach(['light','dark'] as $theme)file_put_contents($directory.'/reviews-'.$theme.'.html','<!doctype html><html lang="ar" dir="rtl" data-ba-admin-theme="'.$theme.'"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/backend/libs/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="/assets/backend/libs/bootstrap/bootstrap.rtl.css"><link rel="stylesheet" href="/assets/backend/libs/fonts/inter/inter.css"><link rel="stylesheet" href="/assets/backend/webfonts/uicons-regular-rounded.css"><link rel="stylesheet" href="/assets/backend/admin/css/style.css"><link rel="stylesheet" href="/assets/back-end/css/admin-v2.css"><link rel="stylesheet" href="/assets/back-end/css/admin-dark-mode.css"><link rel="stylesheet" href="/assets/back-end/css/customer-accounts.css"><body class="app-v2" style="display:block;height:auto;overflow:auto;direction:rtl"><p>معاينة اختبار — ليست حسابات فعلية</p>'.$html.'</body></html>');
    }
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('DOMAIN_POINTED_DIRECTORY')) define('DOMAIN_POINTED_DIRECTORY', 'public');
        session()->put('local', 'ar');
        app()->setLocale('ar');
        session()->put('system_default_currency_info', new \App\Models\Currency(['code'=>'EGP','symbol'=>'ج.م']));
        Cache::put('pagination_limit', 2);
        Cache::put('currency_model', 'single_currency');
        Cache::put('currency_symbol_position', 'right');
        Cache::put('decimal_point_settings', 2);
        Schema::create('business_settings', fn (Blueprint $t) => [$t->id(),$t->string('type'),$t->text('value')->nullable()]);
        foreach (['users','sellers'] as $table) Schema::create($table, function (Blueprint $t) {
            $t->id(); foreach (['f_name','l_name','email','phone'] as $field) $t->string($field)->nullable();
            $t->decimal('wallet_balance',24,3)->default(0); $t->timestamps();
        });
        DB::table('users')->insert(['id'=>1,'f_name'=>'أحمد','l_name'=>'محمد','phone'=>'01012345678','wallet_balance'=>100]);
        DB::table('sellers')->insert(['id'=>1,'f_name'=>'تاجر','l_name'=>'سيجما','phone'=>'01112345678']);
        (require base_path('database/migrations/2026_09_06_000001_create_customer_balance_deposits_table.php'))->up();
        Schema::create('wallet_transactions', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); foreach (['transaction_id','transaction_type','reference','payment_method'] as $field) $t->string($field);
            foreach (['credit','debit','balance','admin_bonus'] as $field) $t->decimal($field,24,3)->default(0); $t->timestamps();
        });
        Schema::create('customer_subscription_payments', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->string('status'); $t->string('method_name'); $t->decimal('quoted_amount');
            $t->string('currency_code'); $t->string('payment_reference'); $t->timestamps();
        });
        Schema::create('seller_balance_deposits', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->integer('offline_payment_method_id')->nullable(); $t->string('source'); $t->string('status');
            $t->decimal('amount'); $t->string('currency_code'); $t->string('transaction_reference')->nullable();
            foreach (['metadata','offline_information','payment_proof'] as $field) $t->json($field)->nullable(); $t->timestamps();
        });
        Schema::create('offline_payment_methods', function (Blueprint $t) { $t->id();$t->string('method_name');$t->string('payment_channel')->default('other');$t->boolean('status')->default(true);$t->json('method_fields')->nullable();$t->json('method_informations')->nullable(); });
        Schema::create('seller_packages', fn (Blueprint $t) => [$t->id(),$t->string('name')]);
        Schema::create('seller_package_subscriptions', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->integer('package_id')->nullable(); $t->string('payment_method'); $t->string('status'); $t->decimal('paid_package_price')->default(0); $t->timestamps();
        });
        $this->role(['people']);
    }

    private function role(array $modules): void
    {
        $admin = new Admin(['admin_role_id'=>2]); $admin->id=9;
        $admin->setRelation('role', new AdminRole(['module_access'=>json_encode($modules),'status'=>1]));
        $this->actingAs($admin,'admin');
    }

    private function deposit(string $status='pending', string $method='Bank', string $date='2026-10-20'): CustomerBalanceDeposit
    {
        return CustomerBalanceDeposit::create(['customer_id'=>1,'wallet_type'=>'purchase','amount'=>50,'submitted_amount'=>50,'currency_code'=>'EGP',
            'offline_payment_method_id'=>1,'method_name'=>$method,'payment_reference'=>'REF-'.uniqid(), 'payment_proof'=>'receipt.enc',
            'request_key'=>uniqid(), 'status'=>$status,'created_at'=>$date.' 12:00:00']);
    }

    private function data(array $filters=[]): array
    {
        $request = Request::create('/admin/payment-reviews','GET',$filters);
        app()->instance('request', $request);
        return app(PaymentReviewController::class)->index($request)->getData();
    }

    public function test_deposits_and_subscriptions_share_counts_filters_and_pagination(): void
    {
        $this->deposit(); $this->deposit('paid'); $this->deposit('rejected','InstaPay','2026-10-19');
        $retired = $this->deposit(); $retired->update(['wallet_type'=>'insurance']);
        DB::table('customer_subscription_payments')->insert(['customer_id'=>1,'status'=>'approved','method_name'=>'Bank','quoted_amount'=>100,'currency_code'=>'EGP','payment_reference'=>'SUB-1','created_at'=>now(),'updated_at'=>now()]);
        DB::table('seller_balance_deposits')->insert(['seller_id'=>1,'source'=>'offline','status'=>'pending','amount'=>70,'currency_code'=>'EGP','transaction_reference'=>'VENDOR-1','created_at'=>now(),'updated_at'=>now()]);
        $data=$this->data();
        $this->assertSame(5,$data['reviews']->total());
        $this->assertCount(2,$data['reviews']);
        $this->assertSame(2,$data['counts']->get('pending'));
        $this->assertSame(2,$data['counts']->get('approved'));
        $approved=$this->data(['status'=>'approved']);
        $this->assertSame(2,$approved['reviews']->total());
        $this->assertSame(2,$approved['counts']->get('pending'));
        $filtered=$this->data(['audience'=>'customer','kind'=>'balance_deposit','method'=>'Bank','from'=>'2026-10-20','to'=>'2026-10-20','searchValue'=>'أحمد محمد']);
        $this->assertSame(2,$filtered['reviews']->total());
        $this->assertSame(1,$filtered['counts']->get('pending'));
        $this->assertSame(0,$filtered['counts']->get('rejected',0));
        $this->assertSame(1,$this->data(['searchValue'=>'VENDOR-1'])['reviews']->total());
        $this->assertSame(3,$this->data(['searchValue'=>'01012345678','kind'=>'balance_deposit'])['reviews']->total());
        $this->assertSame(3,$this->data(['searchValue'=>'+٢٠ ١٠١ ٢٣٤ ٥٦٧٨','kind'=>'balance_deposit'])['reviews']->total());
        $this->assertSame(3,$this->data(['searchValue'=>'C1','kind'=>'balance_deposit'])['reviews']->total());
        $this->assertSame(1,$this->data(['method'=>'InstaPay','to'=>'2026-10-19'])['reviews']->total());
        $history=$this->data(['record_type'=>'customer_balance_deposit','record_id'=>$retired->id]);
        $this->assertSame(1,$history['reviews']->total());
        $this->assertTrue($history['reviews']->first()['read_only']);
        $this->assertSame('reconciliation',$history['reviews']->first()['status']);
    }

    public function test_customer_deposit_actions_use_existing_credit_and_rejection_rules(): void
    {
        \Devrabiul\ToastMagic\Facades\ToastMagic::shouldReceive('success')->zeroOrMoreTimes();
        \Devrabiul\ToastMagic\Facades\ToastMagic::shouldReceive('error')->zeroOrMoreTimes();
        $deposit=$this->deposit();
        $controller=app(PaymentReviewController::class);
        $controller->approve(Request::create('/','POST'), 'customer_balance_deposit', $deposit->id);
        $controller->approve(Request::create('/','POST'), 'customer_balance_deposit', $deposit->id);
        $controller->reject(Request::create('/','POST',['review_note'=>'Conflicting decision']), 'customer_balance_deposit', $deposit->id);
        $this->assertSame('paid',$deposit->fresh()->status);
        $this->assertEquals(150,DB::table('users')->value('wallet_balance'));
        $this->assertSame(1,DB::table('wallet_transactions')->count());
        $this->assertSame(9,$deposit->fresh()->reviewed_by_admin_id);
        $rejected=$this->deposit();
        $controller->reject(Request::create('/','POST',['review_note'=>'Unverified transfer']), 'customer_balance_deposit', $rejected->id);
        $this->assertSame('rejected',$rejected->fresh()->status);
        $this->assertSame('Unverified transfer',$rejected->fresh()->review_note);
        $this->assertEquals(150,DB::table('users')->value('wallet_balance'));
    }

    public function test_rejection_requires_a_reason(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PaymentReviewController::class)->reject(Request::create('/','POST'), 'customer_balance_deposit', $this->deposit()->id);
    }

    public function test_order_reviews_normalize_statuses_without_counting_confirmed_funds_as_deposits(): void
    {
        $this->role(['orders']);
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->string('payment_method'); $t->string('payment_status');
            $t->string('activation_status')->nullable(); $t->string('customer_initial_payment_review_status')->nullable();
            $t->decimal('order_amount'); $t->string('transaction_ref')->nullable(); $t->timestamps();
        });
        Schema::create('offline_payments', fn (Blueprint $t) => [$t->id(),$t->integer('order_id'),$t->json('payment_info')->nullable()]);
        $service=\Mockery::mock(\App\Services\CustomerPurchaseEligibilityService::class);
        $service->shouldReceive('orderPayload')->andReturn(['message'=>'Existing order review']);
        app()->instance(\App\Services\CustomerPurchaseEligibilityService::class,$service);
        foreach (['pending','approved','rejected'] as $index=>$status) DB::table('orders')->insert([
            'id'=>$index+1,'customer_id'=>1,'payment_method'=>'offline_payment','payment_status'=>$status === 'approved' ? 'paid' : 'unpaid',
            'activation_status'=>$status === 'rejected' ? 'offline_payment_rejected' : null,'order_amount'=>100,'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('orders')->insert(['id'=>4,'customer_id'=>1,'payment_method'=>'wallet','payment_status'=>'paid',
            'customer_initial_payment_review_status'=>'pending','order_amount'=>100,'created_at'=>now(),'updated_at'=>now()]);
        $data=$this->data(['status'=>'pending']);
        $this->assertSame(2,$data['reviews']->total());
        $this->assertSame(1,$data['counts']->get('approved'));
        $this->assertSame(1,$data['counts']->get('rejected'));
        $this->assertSame(['customer_initial_order','customer_order'],$data['reviews']->pluck('type')->sort()->values()->all());
        $this->assertSame(2,app(AdminWorkspaceService::class)->payments()['count']);
        $this->assertEquals(100,DB::table('users')->value('wallet_balance'));
        $this->assertSame(0,DB::table('wallet_transactions')->count());
    }

    public function test_orders_only_admin_cannot_read_deposits_proofs_or_decide_them(): void
    {
        $this->role(['orders']);
        foreach (['approve','reject','proof'] as $method) {
            try { app(PaymentReviewController::class)->$method(Request::create('/','POST',['review_note'=>'No access']), 'vendor_balance_deposit',1); $this->fail('Forbidden'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        }
        $this->assertSame(0,$this->data(['kind'=>'balance_deposit'])['reviews']->total());
    }

    public function test_legacy_order_decisions_cannot_overwrite_an_existing_decision(): void
    {
        $this->role(['orders']);
        \Devrabiul\ToastMagic\Facades\ToastMagic::shouldReceive('success')->once();
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->integer('customer_checkout_operation_id')->nullable();
            $t->string('payment_method'); $t->string('payment_status'); $t->string('activation_status')->nullable();
            $t->string('order_status'); $t->text('order_note')->nullable(); $t->timestamps();
        });
        DB::table('orders')->insert(['id'=>1,'customer_id'=>1,'payment_method'=>'offline_payment','payment_status'=>'unpaid','order_status'=>'pending']);
        $controller=app(PaymentReviewController::class);
        $controller->reject(Request::create('/','POST',['review_note'=>'Transfer not received']), 'customer_order',1);
        $controller->reject(Request::create('/','POST',['review_note'=>'Repeated decision']), 'customer_order',1);
        $controller->approve(Request::create('/','POST'), 'customer_order',1);
        $order=DB::table('orders')->first();
        $this->assertSame('unpaid',$order->payment_status);
        $this->assertSame('offline_payment_rejected',$order->activation_status);
        $this->assertSame('canceled',$order->order_status);
        $this->assertSame(1,substr_count($order->order_note,'Offline payment rejected:'));
        $this->assertStringNotContainsString('Repeated decision',$order->order_note);
    }

    public function test_notifications_and_old_links_target_unified_deposit(): void
    {
        $deposit=$this->deposit();
        $items=app(AdminWorkspaceService::class)->payments();
        $this->assertSame(1,$items['count']);
        $this->assertStringContainsString('record_type=customer_balance_deposit',$items['items'][0]['url']);
        $this->assertSame(1,$this->data(['record_type'=>'customer_balance_deposit','record_id'=>$deposit->id])['reviews']->total());
        $old=app(\App\Http\Controllers\Admin\Customer\CustomerBalanceDepositController::class)->index(Request::create('/','GET',['record_id'=>$deposit->id,'status'=>'paid']));
        $this->assertStringContainsString('status=approved',$old->getTargetUrl());
        $this->assertStringContainsString('record_id='.$deposit->id,$old->getTargetUrl());
    }

    public function test_vendor_proof_is_authenticated_and_uses_record_path(): void
    {
        Storage::fake('public');
        $image=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        Storage::disk('public')->put('seller-balance/payment-proof/proof.png',$image);
        DB::table('seller_balance_deposits')->insert(['id'=>1,'seller_id'=>1,'source'=>'offline','status'=>'pending','amount'=>70,'currency_code'=>'EGP','payment_proof'=>json_encode(['image_name'=>'proof.png','storage'=>'public'])]);
        $response=app(PaymentReviewController::class)->proof(Request::create('/'), 'vendor_balance_deposit',1);
        $this->assertSame($image,$response->getContent());
        $this->assertSame('image/png',$response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        DB::table('seller_balance_deposits')->where('id',1)->update(['payment_proof'=>json_encode(['image_name'=>'../proof.png'])]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PaymentReviewController::class)->proof(Request::create('/'), 'vendor_balance_deposit',1);
    }

    public function test_order_review_uses_payment_proof_not_an_unrelated_image(): void
    {
        $this->role(['orders']);
        Schema::create('orders', fn (Blueprint $t) => [$t->id()]);
        Schema::create('offline_payments', fn (Blueprint $t) => [$t->id(), $t->integer('order_id'), $t->text('payment_info')]);
        DB::table('orders')->insert(['id' => 10]);
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        Storage::fake('public');
        Storage::disk('public')->put('offline-payment/order-proof/real.png', $image);
        Storage::disk('public')->put('offline-payment/order-proof/other.png', $image.'wrong');
        DB::table('offline_payments')->insert(['order_id' => 10, 'payment_info' => json_encode([
            'unrelated_image' => ['image_name' => 'other.png', 'storage' => 'public'],
            'payment_proof' => ['image_name' => 'real.png', 'storage' => 'public'],
        ])]);
        $response = app(PaymentReviewController::class)->proof(Request::create('/'), 'customer_order', 10);
        $this->assertSame($image, $response->getContent());
    }

    public function test_drawer_and_filters_render_arabic_without_exposing_storage_paths(): void
    {
        $this->deposit();
        $data=$this->data();
        $source=file_get_contents(resource_path('views/admin-views/payment-reviews/index.blade.php'));
        $source=str_replace(["@extends('layouts.admin.app')", "@section('title', __('payments.title'))", "@section('content')", '@endsection'],'',$source);
        $html=Blade::render($source,$data + ['errors'=>new \Illuminate\Support\ViewErrorBag]);
        $this->assertStringContainsString('pr-drawer',$html);
        $this->assertStringContainsString('أحمد محمد',$html);
        $this->assertStringContainsString('محفظة مشتريات العميل',$html);
        $this->assertStringContainsString('deposits/1/proof',$html);
        $this->assertStringNotContainsString('receipt.enc',$html);
        $this->assertStringNotContainsString('name="insurance"',$html);

        if ($directory=getenv('PAYMENTS_REVIEW_QA_DIR')) {
            if (!is_dir($directory)) mkdir($directory,0775,true);
            $this->deposit('paid'); $this->deposit('rejected','InstaPay');
            foreach(DB::table('customer_balance_deposits')->pluck('id') as $id) DB::table('customer_balance_deposits')->where('id',$id)->update(['payment_reference'=>'deposit-transfer-23:'.str_repeat('a1234567-',9).$id]);
            DB::table('users')->where('id',1)->update(['f_name'=>'محمد أحمد عبدالرحمن','l_name'=>'حساب للاختبار','phone'=>'+201012345678']);
            foreach (['wallet','instapay'] as $i=>$channel) DB::table('offline_payment_methods')->insert(['id'=>$i+1,'method_name'=>$channel==='wallet'?'محفظة إلكترونية':'InstaPay','payment_channel'=>$channel,'status'=>$i===0?1:0,
                'method_fields'=>json_encode([['input_name'=>'account_holder_name','input_data'=>'Sigma Medical Supplies'],['input_name'=>$channel==='wallet'?'wallet_number':'instapay_account','input_data'=>$channel==='wallet'?'01012345678':'sigma.care@instapay'],['input_name'=>'instructions','input_data'=>'أرسل التحويل ثم أرفق الإيصال واسم المرسل ورقم هاتفه لمراجعة العملية.']]),
                'method_informations'=>json_encode([['customer_input'=>'sender_name','customer_placeholder'=>'الاسم كما يظهر في التحويل','is_required'=>1],['customer_input'=>'sender_phone','customer_placeholder'=>'رقم المرسل','is_required'=>1]])]);
            $this->role(['people','3rd_party_setup']);
            Cache::put('pagination_limit',20);
            foreach (['ar'=>'rtl','en'=>'ltr'] as $locale=>$direction) {
                app()->setLocale($locale); session()->put('local',$locale);
                foreach (['light','dark'] as $theme) {
                    $links='';
                    foreach (['backend/webfonts/uicons-regular-rounded.css','backend/libs/fonts/inter/inter.css','backend/libs/bootstrap/bootstrap.min.css','backend/admin/css/style.css','backend/admin/css/style-extended.css','backend/admin/css/custom.css','back-end/css/admin-v2.css','back-end/css/admin-dark-mode.css','back-end/css/payment-reviews.css'] as $asset) $links.='<link rel="stylesheet" href="/assets/'.$asset.'">';
                    foreach (['reviews'=>[], 'settings'=>['panel'=>'settings']] as $page=>$filters) {
                        $content=Blade::render($source,$this->data($filters)+['errors'=>new \Illuminate\Support\ViewErrorBag]);
                        $shell='<header class="v2-header"><strong style="padding:16px">SIGMA</strong></header><aside class="v2-rail"><div class="v2-rail-items"><button class="v2-rail-btn" aria-label="Home"><i class="fi fi-rr-home"></i></button><button class="v2-rail-btn" aria-label="Orders"><i class="fi fi-rr-shopping-cart"></i></button><button class="v2-rail-btn v2-is-active" aria-label="Operations"><i class="fi fi-rr-credit-card"></i></button></div></aside><aside class="v2-ctxpanel"><div class="v2-ctx-scroll"><div class="v2-ctx-section v2-is-on"><div class="v2-ctx-head"><div class="v2-ctx-title">'.__('payments.operations').'</div></div><div class="v2-ctx-group"><a class="v2-nav-item v2-is-active"><span class="v2-nav-btn"><span class="v2-nav-icon"><i class="fi fi-rr-credit-card"></i></span><span class="v2-nav-label">'.__('payments.title').'</span></span></a></div></div></div></aside>';
                        $document='<!doctype html><html lang="'.$locale.'" dir="'.$direction.'" data-ba-admin-theme="'.$theme.'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.$links.'<style>.content{padding:0}.v2-header{background:var(--v2-surface)}@media(max-width:1023px){body.app-v2{grid-template-columns:minmax(0,1fr);grid-template-areas:"header" "main"}.v2-rail,.v2-ctxpanel{display:none}}</style></head><body class="app-v2" data-bs-theme="'.$theme.'">'.$shell.'<main class="v2-main">'.$content.'</main><script src="/assets/back-end/js/payment-reviews.js"></script></body></html>';
                        file_put_contents($directory.'/'.$locale.'-'.$theme.'-'.$page.'.html',str_replace(['http://localhost/ba/public','http://localhost/'],['http://127.0.0.1:8791','http://127.0.0.1:8791/'],$document));
                    }
                }
            }
        }
    }

    public function test_payment_navigation_is_separate_and_all_changed_blades_compile(): void
    {
        $source=file_get_contents(resource_path('views/layouts/admin/partials/v2/_side-bar.blade.php'));
        $people=explode('data-section="people"',$source,2)[1];
        $people=explode('data-section="reports"',$people,2)[0];
        $this->assertStringNotContainsString('balance-deposits',$people);
        $this->assertStringNotContainsString('balance-funding-settings',$people);
        $this->assertStringNotContainsString('customer.wallet.deposits',$people);
        // قسم العمليات اتشال؛ مراجعة المدفوعات بقت جوه قسم الطلبات وبتظهر مرة واحدة.
        $this->assertStringNotContainsString('data-section="operations"',$source);
        $orders=explode('data-section="orders"',$source,2)[1];
        $orders=explode('data-section="auction"',$orders,2)[0];
        $this->assertSame(1,substr_count($orders,"route('admin.payment-reviews.index')"));
        $rail=file_get_contents(resource_path('views/layouts/admin/partials/v2/_rail.blade.php'));
        $this->assertStringNotContainsString('data-section="operations"',$rail);
        foreach (['layouts/admin/partials/v2/_side-bar','layouts/admin/partials/v2/_rail','layouts/admin/partials/_side-bar','admin-views/payment-reviews/index','admin-views/payment-reviews/_detail','admin-views/payment-reviews/_funding-settings','admin-views/payment-reviews/_table-row'] as $view) {
            $compiled=Blade::compileString(file_get_contents(resource_path('views/'.$view.'.blade.php')));
            $this->assertNotEmpty(token_get_all($compiled,TOKEN_PARSE));
        }
    }

    public function test_settings_show_only_offline_methods_and_respect_management_permissions(): void
    {
        DB::table('offline_payment_methods')->insert(['id'=>1,'method_name'=>'InstaPay','payment_channel'=>'instapay','status'=>1,
            'method_fields'=>json_encode([['input_name'=>'account_name','input_data'=>'Sigma Receiver'],['input_name'=>'instapay_account','input_data'=>'sigma@instapay']]),
            'method_informations'=>json_encode([['customer_input'=>'sender_name','customer_placeholder'=>'Sender','is_required'=>1]])]);
        DB::table('offline_payment_methods')->insert(['id'=>2,'method_name'=>'Wallet','payment_channel'=>'wallet','status'=>0]);
        $data=$this->data(['panel'=>'settings']);
        $this->assertArrayHasKey('settingsPanel',$data);
        $this->assertCount(2,$data['settingsPanel']['offlinePaymentMethods']);
        $html=Blade::render(file_get_contents(resource_path('views/admin-views/payment-reviews/_funding-settings.blade.php')),$data['settingsPanel']);
        $this->assertStringContainsString('Sigma Receiver',$html);
        $this->assertStringContainsString('sigma@instapay',$html);
        $this->assertStringContainsString('معطّلة',$html);
        $this->assertStringNotContainsString('name="digital_enabled"',$html);
        $this->assertStringNotContainsString('name="digital_gateways[]"',$html);
        $this->assertStringNotContainsString('name="min_amount"',$html);
        $this->assertStringNotContainsString(route('admin.third-party.offline-payment-method.add'),$html);
        $this->role(['people','3rd_party_setup']);
        $manage=$this->data(['panel'=>'settings']);
        $managedHtml=Blade::render(file_get_contents(resource_path('views/admin-views/payment-reviews/_funding-settings.blade.php')),$manage['settingsPanel']);
        $this->assertStringContainsString(route('admin.third-party.offline-payment-method.add'),$managedHtml);
        $this->assertStringContainsString(route('admin.third-party.offline-payment-method.update',['id'=>1]),$managedHtml);
        $this->assertSame(2,DB::table('offline_payment_methods')->count());
        $this->assertStringContainsString('panel=settings',app(\App\Http\Controllers\Admin\Vendor\SellerBalanceFundingSettingsController::class)->index()->getTargetUrl());
    }

    public function test_internal_payment_identifiers_stay_in_details_without_expanding_table_rows(): void
    {
        $deposit=$this->deposit();
        $reference='deposit-transfer-23:'.str_repeat('a1234567-',12);
        $deposit->update(['payment_reference'=>$reference]);
        $review=$this->data()['reviews']->first();
        $table=Blade::render(file_get_contents(resource_path('views/admin-views/payment-reviews/_table-row.blade.php')),['review'=>$review]);
        $this->assertStringNotContainsString($reference,$table);
        $this->assertStringContainsString('ADC'.$deposit->id,$table);
        $detail=Blade::render(file_get_contents(resource_path('views/admin-views/payment-reviews/_detail.blade.php')),['review'=>$review]);
        $this->assertStringContainsString($reference,$detail);
        $this->assertStringContainsString('data-pr-copy=',$detail);
        $this->assertSame($reference,$deposit->fresh()->payment_reference);
    }

    public function test_order_review_permission_does_not_allow_deposit_settings(): void
    {
        $this->role(['orders']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->data(['panel'=>'settings']);
    }
}
