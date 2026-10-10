<?php
namespace Tests\Unit;

use App\Http\Controllers\Admin\AccountDirectoryController;
use App\Models\{Admin,AdminRole,User,Seller};
use App\Services\AdminAccountDirectoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema,Blade};
use Tests\TestCase;

class AdminAccountDirectoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if(!defined('DOMAIN_POINTED_DIRECTORY')) define('DOMAIN_POINTED_DIRECTORY','public');
        session()->put('local','ar');
        Schema::create('users',function(Blueprint $t){$t->id();foreach(['name','f_name','l_name','email','phone','remember_token'] as $c)$t->string($c)->nullable();$t->boolean('is_active')->default(1);$t->timestamps();});
        Schema::create('sellers',function(Blueprint $t){$t->id();foreach(['f_name','l_name','email','phone','auth_token'] as $c)$t->string($c)->nullable();$t->string('status')->default('approved');$t->timestamps();});
        Schema::create('shops',function(Blueprint $t){$t->id();$t->integer('seller_id');$t->string('name');$t->string('contact')->nullable();});
        Schema::create('vendor_account_profiles',function(Blueprint $t){$t->id();$t->integer('seller_id');$t->text('phone_numbers')->nullable();});
        Schema::create('orders',function(Blueprint $t){$t->id();$t->integer('customer_id')->nullable();$t->integer('seller_id')->nullable();$t->string('seller_is')->default('seller');$t->boolean('is_guest')->default(0);});
        Schema::create('support_tickets',function(Blueprint $t){$t->id();$t->integer('customer_id');});
        Schema::create('products',function(Blueprint $t){$t->id();$t->integer('user_id');$t->string('added_by');});
        Schema::create('oauth_access_tokens',function(Blueprint $t){$t->string('id')->primary();$t->integer('user_id');$t->boolean('revoked')->default(0);});
        Schema::create('oauth_refresh_tokens',function(Blueprint $t){$t->id();$t->string('access_token_id');$t->boolean('revoked')->default(0);});
        Schema::create('business_settings',function(Blueprint $t){$t->id();$t->string('type');$t->text('value');});
        DB::table('users')->insert([['id'=>12,'f_name'=>'أحمد','l_name'=>'محمد','email'=>'customer@example.test','phone'=>'+201012345678'],['id'=>13,'f_name'=>'حساب','l_name'=>'فارغ','email'=>null,'phone'=>null],['id'=>14,'f_name'=>'Walking','l_name'=>null,'email'=>'walking@customer.com','phone'=>null]]);
        DB::table('sellers')->insert(['id'=>7,'f_name'=>'تاجر','l_name'=>'سيجما','email'=>'vendor@example.test','phone'=>'01099998888','auth_token'=>str_repeat('x',40)]);
        DB::table('shops')->insert(['seller_id'=>7,'name'=>'متجر سيجما','contact'=>'01099997777']);
        DB::table('vendor_account_profiles')->insert(['seller_id'=>7,'phone_numbers'=>json_encode(['+201099998888','01099996666'])]);
        $admin=new Admin(['admin_role_id'=>1]);$admin->id=1;$admin->setRelation('role',new AdminRole(['module_access'=>'[]','status'=>1]));$this->actingAs($admin,'admin');
    }

    public function test_real_union_search_filters_and_pagination(): void
    {
        $service=app(AdminAccountDirectoryService::class);
        $this->assertSame(3,$service->query([])->count());
        foreach(['c12','C12','أحمد محمد','010-1234-5678','٠١٠١٢٣٤٥٦٧٨','customer@example.test'] as $q) $this->assertSame(12,$service->query(['q'=>$q])->first()->id);
        foreach(['m7','متجر سيجما','تاجر سيجما','01099996666','01099997777'] as $q) $this->assertSame('vendor',$service->query(['q'=>$q])->first()->kind);
        $this->assertSame(0,$service->query(['q'=>'0000'])->count());
        $this->assertSame(1,$service->query(['type'=>'vendor','status'=>'active'])->count());
        $page=$service->query(['sort'=>'identifier','direction'=>'asc'])->paginate(1,['*'],'page',2);
        $this->assertSame(2,$page->firstItem());
    }

    public function test_secondary_contacts_are_real_and_primary_is_deduplicated(): void
    {
        $service=app(AdminAccountDirectoryService::class);
        $contacts=$service->contacts($service->query([])->get());
        $this->assertSame(['01099996666','01099997777'],$contacts['vendor:7']);
        $this->assertSame([],$contacts['customer:12']);
    }

    public function test_freeze_revokes_access_and_refresh_tokens_and_reactivation_persists(): void
    {
        DB::table('oauth_access_tokens')->insert(['id'=>'token','user_id'=>12]);DB::table('oauth_refresh_tokens')->insert(['access_token_id'=>'token']);
        $controller=app(AccountDirectoryController::class);$service=app(AdminAccountDirectoryService::class);
        $controller->status(Request::create('/','POST',['action'=>'freeze']),'customer',12,$service);
        $this->assertSame(0,(int)DB::table('users')->find(12)->is_active);
        $this->assertSame(1,(int)DB::table('oauth_access_tokens')->first()->revoked);
        $this->assertSame(1,(int)DB::table('oauth_refresh_tokens')->first()->revoked);
        $controller->status(Request::create('/','POST',['action'=>'reactivate']),'customer',12,$service);
        $this->assertSame(1,(int)DB::table('users')->find(12)->is_active);
        $controller->status(Request::create('/','POST',['action'=>'freeze']),'vendor',7,$service);
        $this->assertSame('suspended',DB::table('sellers')->find(7)->status);$this->assertNull(DB::table('sellers')->find(7)->auth_token);
        $controller->status(Request::create('/','POST',['action'=>'reactivate']),'vendor',7,$service);
        $this->assertSame('approved',DB::table('sellers')->find(7)->status);
        DB::table('sellers')->where('id',7)->update(['status'=>'pending']);
        $controller->status(Request::create('/','POST',['action'=>'reactivate']),'vendor',7,$service);
        $this->assertSame('pending',DB::table('sellers')->find(7)->status);
    }

    public function test_retained_orders_support_and_products_block_deletion_without_changes(): void
    {
        $service=app(AdminAccountDirectoryService::class);
        DB::table('orders')->insert(['customer_id'=>12]);DB::table('support_tickets')->insert(['customer_id'=>12]);DB::table('products')->insert(['user_id'=>7,'added_by'=>'seller']);
        $this->assertContains('orders',$service->permanentlyDelete('customer',12));
        $this->assertContains('support_tickets',$service->deletionBlockers('customer',12));
        $this->assertContains('products',$service->permanentlyDelete('vendor',7));
        $this->assertNotNull(DB::table('users')->find(12));$this->assertSame(1,DB::table('shops')->count());
    }

    public function test_empty_accounts_are_physically_deleted_with_owned_profiles(): void
    {
        $service=app(AdminAccountDirectoryService::class);
        $this->assertSame([],$service->permanentlyDelete('customer',13));$this->assertNull(DB::table('users')->find(13));
        $this->assertSame([],$service->permanentlyDelete('vendor',7));$this->assertNull(DB::table('sellers')->find(7));$this->assertSame(0,DB::table('shops')->count());$this->assertSame(0,DB::table('vendor_account_profiles')->count());
    }

    public function test_permissions_and_confirmation_are_enforced_in_backend(): void
    {
        $admin=new Admin(['admin_role_id'=>2]);$admin->id=2;$admin->setRelation('role',new AdminRole(['module_access'=>'["people"]','status'=>1]));$this->actingAs($admin,'admin');
        try {app(AccountDirectoryController::class)->destroy(Request::create('/','DELETE',['confirmation'=>'c13','acknowledge'=>1]),'customer',13,app(AdminAccountDirectoryService::class));$this->fail('Expected denied deletion');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        $admin->setRelation('role',new AdminRole(['module_access'=>'[]','status'=>1]));
        try {app(AccountDirectoryController::class)->index(Request::create('/'),app(AdminAccountDirectoryService::class));$this->fail('Expected denied list');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        $admin->admin_role_id=1;
        $response=app(AccountDirectoryController::class)->destroy(Request::create('/','DELETE',['confirmation'=>'c99','acknowledge'=>1]),'customer',13,app(AdminAccountDirectoryService::class));
        $this->assertNotNull(DB::table('users')->find(13));$this->assertTrue($response->getSession()->has('errors'));
    }

    public function test_frozen_customer_is_denied_on_existing_api_session(): void
    {
        $user=User::find(12);$user->is_active=false;$this->actingAs($user,'api');
        $middleware=new \App\Http\Middleware\Authenticate(app('auth'));
        $response=$middleware->handle(Request::create('/api/private'),fn()=>response('allowed'),'api');
        $this->assertSame(403,$response->getStatusCode());
        $response=(new \App\Http\Middleware\APIGuestMiddleware)->handle(Request::create('/api/guest','GET',[],[],[],['HTTP_AUTHORIZATION'=>'Bearer existing']),fn()=>response('allowed'));
        $this->assertSame(403,$response->getStatusCode());
    }

    public function test_delete_failure_rolls_back_owned_rows(): void
    {
        DB::unprepared("CREATE TRIGGER prevent_seller_delete BEFORE DELETE ON sellers BEGIN SELECT RAISE(ABORT, 'retained'); END");
        try {app(AdminAccountDirectoryService::class)->permanentlyDelete('vendor',7);$this->fail('Expected failure');}catch(\Illuminate\Database\QueryException $error){$this->assertStringContainsString('retained',$error->getMessage());}
        $this->assertSame(1,DB::table('shops')->count());$this->assertSame(1,DB::table('vendor_account_profiles')->count());$this->assertNotNull(DB::table('sellers')->find(7));
    }

    public function test_status_failure_rolls_back_revocation_and_reports_error(): void
    {
        DB::table('oauth_access_tokens')->insert(['id'=>'token','user_id'=>12]);
        DB::unprepared("CREATE TRIGGER prevent_user_update BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'write failed'); END");
        $response=app(AccountDirectoryController::class)->status(Request::create('/','POST',['action'=>'freeze']),'customer',12,app(AdminAccountDirectoryService::class));
        $this->assertSame(1,(int)DB::table('users')->find(12)->is_active);
        $this->assertSame(0,(int)DB::table('oauth_access_tokens')->first()->revoked);
        $this->assertTrue($response->getSession()->has('errors'));
    }

    public function test_blade_columns_profiles_and_sidebar_placement(): void
    {
        $directory=app(AdminAccountDirectoryService::class);$accounts=$directory->query([])->paginate(20);
        $source=file_get_contents(resource_path('views/admin-views/accounts/index.blade.php'));preg_match("/@section\('content'\)([\s\S]*?)@endsection/",$source,$matches);
        $html=Blade::render($matches[1],['accounts'=>$accounts,'contacts'=>$directory->contacts(collect($accounts->items())),'errors'=>new \Illuminate\Support\ViewErrorBag]);
        $this->assertStringContainsString('c12',$html);$this->assertStringContainsString('m7',$html);$this->assertStringContainsString('متجر سيجما',$html);$this->assertStringContainsString('الاسم:',$html);$this->assertStringContainsString('المتجر:',$html);$this->assertStringContainsString('+2',$html);
        $this->assertStringContainsString(route('admin.customer.view',12),$html);$this->assertStringContainsString(route('admin.vendors.view',7),$html);
        $sidebar=file_get_contents(resource_path('views/layouts/admin/partials/v2/_side-bar.blade.php'));$this->assertGreaterThan(strpos($sidebar,'data-item="pos"'),strpos($sidebar,'data-item="customer-list"'));
        if(getenv('ADMIN_DIRECTORY_QA_EXPORT')) {
            $dir=storage_path('app/admin-account-directory-qa');if(!is_dir($dir))mkdir($dir,0777,true);
            foreach(['light','dark'] as $theme) foreach(['rtl','ltr'] as $direction) {
                file_put_contents($dir.'/'.$theme.'-'.$direction.'.html','<!doctype html><html lang="ar" dir="'.$direction.'" data-ba-admin-theme="'.$theme.'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/backend/libs/fonts/inter/inter.css"><link rel="stylesheet" href="/assets/backend/libs/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="/assets/backend/admin/css/style.css"><link rel="stylesheet" href="/assets/back-end/css/admin-v2.css"><link rel="stylesheet" href="/assets/back-end/css/admin-dark-mode.css"><link rel="stylesheet" href="/assets/back-end/css/admin-workspace.css"><link rel="stylesheet" href="/assets/back-end/css/admin-account-directory.css"><link rel="stylesheet" href="/assets/backend/webfonts/uicons-regular-rounded.css"></head><body class="v2-active"><div class="app-v2" style="display:block;padding:24px"><main class="v2-main">'.$html.'</main></div><script src="/assets/back-end/js/admin-account-directory.js"></script></body></html>');
            }
        }
    }
}
