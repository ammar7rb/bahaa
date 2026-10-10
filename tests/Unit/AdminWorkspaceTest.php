<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\AdminWorkspaceController;
use App\Models\{Admin, AdminRole};
use App\Services\{AdminWorkspaceService, EgyptPhoneService};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('DOMAIN_POINTED_DIRECTORY')) define('DOMAIN_POINTED_DIRECTORY', 'public');
        session()->put('local', 'ar');
        Schema::create('platform_daily_metrics', function (Blueprint $t) { $t->id(); $t->string('channel'); $t->integer('page_views'); });
        DB::table('platform_daily_metrics')->insert([['channel'=>'web','page_views'=>42],['channel'=>'app','page_views'=>999]]);
        foreach (['users', 'sellers'] as $name) Schema::create($name, function (Blueprint $t) { $t->id(); $t->string('f_name')->nullable(); $t->string('l_name')->nullable(); $t->string('email')->nullable(); $t->string('phone')->nullable(); $t->timestamps(); });
        Schema::create('contacts', function (Blueprint $t) { $t->id(); foreach (['name','email','subject','message','sender_type'] as $c) $t->string($c)->nullable(); $t->boolean('seen')->default(false); $t->timestamps(); });
        Schema::create('support_tickets', function (Blueprint $t) { $t->id(); $t->integer('customer_id'); $t->string('subject'); $t->string('description'); $t->string('purpose')->nullable(); $t->timestamp('admin_read_at')->nullable(); $t->integer('admin_read_message_id')->nullable(); $t->timestamps(); });
        Schema::create('support_ticket_convs', function (Blueprint $t) { $t->id(); $t->integer('support_ticket_id'); $t->string('customer_message')->nullable(); $t->string('admin_message')->nullable(); $t->timestamps(); });
        Schema::create('vendor_support_threads', function (Blueprint $t) { $t->id(); $t->integer('seller_id'); $t->string('subject'); $t->timestamps(); });
        Schema::create('vendor_support_messages', function (Blueprint $t) { $t->id(); $t->integer('vendor_support_thread_id'); $t->string('body'); $t->timestamps(); });
        Schema::create('contact_conversation_messages', function (Blueprint $t) { $t->id(); $t->integer('contact_id'); $t->string('sender')->default('requester'); $t->string('body'); });
        Schema::create('chattings', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('admin_id')->default(0); $t->string('message')->nullable(); $t->boolean('sent_by_customer')->default(true); $t->boolean('seen_by_admin')->default(false); $t->timestamps(); });
        DB::table('users')->insert(['id'=>12,'f_name'=>'أحمد','l_name'=>'محمد','email'=>'customer@example.test','phone'=>'+201012345678']);
        DB::table('sellers')->insert(['id'=>7,'f_name'=>'تاجر','l_name'=>'سيجما','email'=>'vendor@example.test','phone'=>'010-9999-8888']);
        $now = now()->format('Y-m-d H:i:s');
        DB::table('contacts')->insert(['id'=>1,'name'=>'تاجر سيجما','email'=>'vendor@example.test','subject'=>'شحن الطلب','message'=>'متابعة الشحن','updated_at'=>$now]);
        DB::table('support_tickets')->insert([['id'=>2,'customer_id'=>12,'subject'=>'سؤال العميل','description'=>'دعم الطلب','purpose'=>null,'updated_at'=>$now], ['id'=>3,'customer_id'=>12,'subject'=>'تفعيل الحساب','description'=>'تفعيل','purpose'=>'account_activation','updated_at'=>$now]]);
        DB::table('chattings')->insert(['user_id'=>12,'message'=>'موعد التسليم','created_at'=>$now]);
        DB::table('vendor_support_threads')->insert(['id'=>9,'seller_id'=>7,'subject'=>'مراجعة الشحنة','updated_at'=>$now]);
        DB::table('vendor_support_messages')->insert(['vendor_support_thread_id'=>9,'body'=>'تفاصيل دعم التاجر']);
        $this->role(['people']);
    }

    private function role(array $modules): void
    {
        $admin = new Admin(['admin_role_id'=>2]); $admin->id=1;
        $admin->setRelation('role', new AdminRole(['module_access'=>json_encode($modules),'status'=>1]));
        $this->actingAs($admin, 'admin');
    }

    private function search(string $q): array
    {
        return app(AdminWorkspaceController::class)->search(Request::create('/admin/workspace/search','GET',['q'=>$q]), app(AdminWorkspaceService::class), new EgyptPhoneService)->getData(true)['groups'];
    }

    public function test_phone_formats_link_to_the_exact_customer_and_vendor(): void
    {
        foreach (['01012345678', '+20 101 234 5678', '00201012345678', '٠١٠١٢٣٤٥٦٧٨'] as $phone) {
            $groups=$this->search($phone);
            $this->assertSame(route('admin.customer.view',12), $groups[0]['items'][0]['url']);
        }
        $groups=$this->search('+201099998888');
        $this->assertSame(route('admin.vendors.view',7),$groups[0]['items'][0]['url']);
    }

    public function test_permissions_prevent_queries_and_results_for_denied_modules(): void
    {
        $this->role([]); DB::flushQueryLog(); DB::enableQueryLog();
        $this->assertSame([], $this->search('01012345678'));
        $queries=implode(' ',array_column(DB::getQueryLog(),'query'));
        $this->assertStringNotContainsString('"users"',$queries);
        $this->assertStringNotContainsString('"products"',$queries);
        $this->assertNull(app(AdminWorkspaceService::class)->totals()['customers']);
    }

    public function test_inbox_and_center_share_counts_and_exclude_activation_tickets(): void
    {
        $service=app(AdminWorkspaceService::class);
        $this->assertSame(4,$service->conversations()->count());
        $this->assertSame(3,(int)$service->conversations()->sum('unread'));
        $this->assertSame('vendor',$service->conversations()->where('kind','contact')->first()->audience);
        $this->assertSame(3,$service->snapshot()['categories']['messages']['count']);
        app(AdminWorkspaceController::class)->read(Request::create('/','POST',['kind'=>'ticket','id'=>2]),$service);
        $this->assertSame(2,(int)$service->conversations()->sum('unread'));
        DB::table('support_ticket_convs')->insert(['support_ticket_id'=>2,'customer_message'=>'متابعة جديدة','created_at'=>now()->addSecond()]);
        $this->assertSame(3,(int)$service->conversations()->sum('unread'));
    }

    public function test_search_finds_message_bodies_and_correct_conversation_routes(): void
    {
        DB::table('contact_conversation_messages')->insert(['contact_id'=>1,'body'=>'فاتورة جديدة']);
        $groups=$this->search('فاتورة جديدة');
        $this->assertSame(route('admin.contact.view',1),$groups[0]['items'][0]['url']);
        $groups=$this->search('تفاصيل دعم التاجر');
        $this->assertSame(route('admin.vendor-support.show',9),$groups[0]['items'][0]['url']);
        $groups=$this->search('موعد التسليم');
        $this->assertSame(route('admin.workspace.conversation',['kind'=>'chat','id'=>12]),$groups[0]['items'][0]['url']);
    }

    public function test_workspace_migration_is_reversible(): void
    {
        Schema::table('support_tickets', fn (Blueprint $t) => $t->dropColumn(['admin_read_at','admin_read_message_id']));
        Schema::table('contacts', fn (Blueprint $t) => $t->dropColumn('sender_type'));
        $migration=require base_path('database/migrations/2026_10_10_120000_add_admin_read_at_to_support_tickets.php');
        $migration->up();
        $this->assertTrue(Schema::hasColumn('support_tickets','admin_read_message_id'));
        $this->assertTrue(Schema::hasColumn('contacts','sender_type'));
        $migration->down();
        $this->assertFalse(Schema::hasColumn('support_tickets','admin_read_at'));
        $this->assertFalse(Schema::hasColumn('contacts','sender_type'));
    }

    public function test_full_names_references_and_new_pages_respect_permissions(): void
    {
        $this->assertSame(route('admin.customer.view',12), $this->search('أحمد محمد')[0]['items'][0]['url']);
        $this->assertSame(route('admin.vendors.view',7), $this->search('M7')[0]['items'][0]['url']);
        DB::table('users')->where('id',12)->update(['f_name'=>'Ahmed', 'l_name'=>'Mohamed']);
        $this->assertSame(route('admin.customer.view',12), $this->search('Ahmed Mohamed')[0]['items'][0]['url']);
        $this->assertSame(route('admin.workspace.communication'), $this->search('مركز الاتصالات')[0]['items'][0]['url']);
        Schema::create('customer_balance_deposits', function (Blueprint $t) { $t->id(); $t->string('wallet_type')->nullable(); $t->timestamps(); });
        Schema::create('seller_balance_deposits', function (Blueprint $t) { $t->id(); $t->json('metadata')->nullable(); $t->timestamps(); });
        DB::table('customer_balance_deposits')->insert([['id'=>12,'wallet_type'=>'purchase'], ['id'=>13,'wallet_type'=>'insurance']]);
        DB::table('seller_balance_deposits')->insert([['id'=>12,'metadata'=>null], ['id'=>13,'metadata'=>'{"wallet_target":"insurance"}']]);
        foreach (['ADC12','AIC13','ADM12','AIM13'] as $reference) $this->assertNotEmpty($this->search($reference));
        $this->assertSame([], $this->search('AIC12'));
        $this->assertSame(route('admin.customer.wallet.deposits',['record_id'=>12]), $this->search('ADC12')[0]['items'][0]['url']);
        $this->role([]);
        foreach (['ADC12','مركز الاتصالات','payment review','insurance'] as $query) $this->assertSame([], $this->search($query));
    }

    public function test_workspace_rate_limits_are_separate_and_admin_scoped(): void
    {
        $request=Request::create('/');
        $search=\Illuminate\Support\Facades\RateLimiter::limiter('admin-workspace-search')($request);
        $snapshot=\Illuminate\Support\Facades\RateLimiter::limiter('admin-workspace-snapshot')($request);
        $this->assertSame(90,$search->maxAttempts);
        $this->assertSame(30,$snapshot->maxAttempts);
        $this->assertSame('1',$search->key);
        $this->assertContains('throttle:admin-workspace-search', app('router')->getRoutes()->getByName('admin.workspace.search')->gatherMiddleware());
        $this->assertContains('throttle:admin-workspace-snapshot', app('router')->getRoutes()->getByName('admin.workspace.snapshot')->gatherMiddleware());
    }

    public function test_totals_and_pending_reviews_are_distinct_and_blade_renders(): void
    {
        Schema::create('products', function (Blueprint $t) { $t->id(); $t->string('added_by'); $t->integer('request_status'); $t->integer('user_id')->nullable(); $t->string('name')->nullable(); $t->string('code')->nullable(); $t->string('thumbnail')->nullable(); $t->string('thumbnail_storage_type')->nullable(); $t->double('unit_price')->default(125); $t->timestamps(); });
        Schema::create('translations', function (Blueprint $t) { $t->id(); $t->string('translationable_type'); $t->integer('translationable_id'); $t->string('locale'); $t->string('key'); $t->string('value'); });
        Schema::create('orders', function (Blueprint $t) { $t->id(); $t->integer('customer_id')->nullable(); $t->string('order_status'); $t->string('commerce_flow_status')->nullable(); $t->string('payment_method')->nullable(); $t->string('payment_status')->nullable(); $t->string('activation_status')->nullable(); $t->double('order_amount')->default(0); $t->string('transaction_ref')->nullable(); $t->timestamps(); });
        Schema::create('order_details', function (Blueprint $t) { $t->id(); $t->integer('order_id'); $t->integer('qty'); $t->text('product_details')->nullable(); });
        DB::table('order_details')->insert(['order_id'=>101,'qty'=>2,'product_details'=>json_encode(['name'=>'قفازات طبية'])]);
        Schema::create('business_settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->text('value'); });
        Schema::create('account_activation_cases', function (Blueprint $t) { $t->id(); $t->string('subject_type'); $t->integer('subject_id'); $t->string('status'); $t->timestamps(); });
        DB::table('products')->insert([['added_by'=>'admin','request_status'=>1,'name'=>'منتج معتمد','user_id'=>1], ['added_by'=>'seller','request_status'=>1,'name'=>'منتج آخر','user_id'=>7], ['added_by'=>'seller','request_status'=>0,'name'=>'قفازات طبية','user_id'=>7]]);
        DB::table('translations')->insert(['translationable_type'=>\App\Models\Product::class,'translationable_id'=>3,'locale'=>'en','key'=>'name','value'=>'Medical gloves']);
        DB::table('orders')->insert([['id'=>101,'order_status'=>'pending','commerce_flow_status'=>'pending_admin_review'], ['id'=>102,'order_status'=>'delivered','commerce_flow_status'=>'completed']]);
        DB::table('orders')->insert(['id'=>103,'order_status'=>'confirmed','commerce_flow_status'=>'awaiting_payment','payment_method'=>'offline_payment','payment_status'=>'unpaid','created_at'=>now()->subMinutes(5),'updated_at'=>now()]);
        DB::table('orders')->where('id',101)->update(['customer_id'=>12,'order_amount'=>250,'updated_at'=>now()]);
        DB::table('products')->where('id',3)->update(['updated_at'=>now()]);
        DB::table('account_activation_cases')->insert(['subject_type'=>'customer','subject_id'=>12,'status'=>'in_review','updated_at'=>now()]);
        $admin=auth('admin')->user(); $admin->admin_role_id=1; $admin->name='مسؤول الإدارة'; $admin->setRelation('storage',collect());
        $service=app(AdminWorkspaceService::class);
        $this->assertSame(['orders'=>3,'customers'=>1,'vendors'=>1,'products'=>3,'visits'=>42],$service->totals());
        $snapshot=$service->snapshot();
        $this->assertStringContainsString('تاجر سيجما',$snapshot['categories']['products']['items'][0]['detail']);
        $this->assertStringContainsString('product.png',$snapshot['categories']['products']['items'][0]['images'][0]['url']);
        $this->assertSame('أحمد محمد',$snapshot['categories']['orders']['items'][0]['detail']);
        $this->assertStringContainsString('250.00',$snapshot['categories']['orders']['items'][0]['meta']);
        $this->assertCount(1,$snapshot['categories']['orders']['items'][0]['images']);
        $this->assertNotEmpty($snapshot['categories']['payments']['items'][0]['badge']);
        $this->assertMatchesRegularExpression('/T.*[+-]\d{2}:\d{2}$/',$snapshot['categories']['payments']['items'][0]['time']);
        $this->assertEqualsWithDelta(300,\Carbon\Carbon::parse($snapshot['categories']['payments']['items'][0]['time'])->diffInSeconds(now()),2);
        $this->assertMatchesRegularExpression('/T.*[+-]\d{2}:\d{2}$/',$snapshot['categories']['orders']['items'][0]['time']);
        $this->assertNotEmpty($snapshot['categories']['messages']['items'][0]['preview']);
        $this->assertSame(1,$service->snapshot()['categories']['orders']['count']);
        $this->assertSame(1,$service->snapshot()['categories']['products']['count']);
        $this->assertSame(route('admin.products.view',['addedBy'=>'vendor','id'=>3]),$service->snapshot()['categories']['products']['items'][0]['url']);
        $this->assertSame(1,$service->snapshot()['categories']['payments']['count']);
        $this->assertSame(route('admin.orders.details',103),$service->snapshot()['categories']['payments']['items'][0]['url']);
        $this->assertSame(route('admin.products.view',['addedBy'=>'vendor','id'=>3]),$this->search('Medical gloves')[0]['items'][0]['url']);
        $header=\Illuminate\Support\Facades\Blade::render(file_get_contents(resource_path('views/layouts/admin/partials/v2/_header.blade.php')));
        $dashboard=file_get_contents(resource_path('views/admin-views/system/dashboard.blade.php'));
        $dashboard=str_replace(["@extends('layouts.admin.app')", "@section('title', translate('dashboard'))", "@section('content')", '@endsection'], '', $dashboard);
        $body=\Illuminate\Support\Facades\Blade::render($dashboard,['totals'=>$service->totals(),'data'=>$service->activation()]);
        $this->assertSame(5,substr_count($body,'data-workspace-total='));
        $this->assertStringContainsString('section=visits',$body);
        $this->assertStringContainsString('admin-notice-thumb',$header);
        $this->assertStringContainsString('admin-global-search',$header);
        $this->assertStringContainsString('activation-heading',$body);
        $script=\Illuminate\Support\Facades\Blade::render(file_get_contents(resource_path('views/layouts/admin/partials/v2/_workspace-script.blade.php')));
        $this->assertStringContainsString('window.adminWorkspaceConfig = {',$script);
        if (getenv('ADMIN_WORKSPACE_QA')) {
            $dir=storage_path('app/admin-workspace-qa'); if (!is_dir($dir)) mkdir($dir,0777,true);
            $styles=['backend/libs/fonts/inter/inter.css','backend/libs/bootstrap/bootstrap.min.css','backend/webfonts/uicons-regular-rounded.css','backend/admin/css/style.css','back-end/css/admin-v2.css','back-end/css/admin-dark-mode.css','back-end/css/admin-workspace.css'];
            $links=implode('',array_map(fn ($p)=>'<link rel="stylesheet" href="/assets/'.$p.'">',$styles));
            foreach (['light','dark'] as $theme) foreach (['rtl','ltr'] as $direction) {
                $config=json_encode(['imageFallback'=>'/assets/back-end/img/placeholder/product.png','snapshot'=>'/admin/workspace/snapshot','empty'=>translate('workspace_search_empty'),'pendingEmpty'=>translate('workspace_no_pending'),'loading'=>translate('workspace_loading'),'error'=>translate('workspace_search_error'),'refreshOk'=>translate('workspace_auto_refresh'),'refreshError'=>translate('workspace_refresh_error')]);
                $html='<!doctype html><html lang="ar" dir="'.$direction.'" data-ba-admin-theme="'.$theme.'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.$links.'</head><body class="v2-active"><div class="app-v2 v2-collapsed" id="appV2">'.$header.'<main class="v2-main">'.$body.'</main></div><script>window.adminWorkspaceConfig='.$config.';</script><script src="/assets/back-end/js/admin-v2.js"></script><script src="/assets/back-end/js/admin-workspace.js"></script></body></html>';
                $html=preg_replace('#https?://[^"\s]*/public/assets/#','/assets/',$html);
                $html=preg_replace('#https?://[^"\s]*/admin/workspace/search#','/admin/workspace/search',$html);
                file_put_contents($dir.'/'.$theme.'-'.$direction.'.html',$html);
            }
            file_put_contents($dir.'/snapshot.json',str_replace('http://localhost/ba/public/assets/','/assets/',json_encode($service->snapshot(), JSON_UNESCAPED_SLASHES)));
            file_put_contents($dir.'/search.json',json_encode(['groups'=>$this->search('01012345678')]));
        }
    }
}
