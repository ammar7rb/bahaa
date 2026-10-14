<?php
namespace Tests\Unit;
use App\Models\{User,Admin};
use App\Services\{CustomerPasswordRecoveryService,CustomerTemporaryPasswordDeliveryService};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema,Hash};
use Illuminate\Validation\ValidationException;
class CustomerAdminPasswordRecoveryTest extends \Tests\TestCase {
    private object $delivery;
    protected function setUp():void {
        parent::setUp();
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('password');$t->string('email')->nullable();$t->string('phone');$t->string('canonical_phone')->nullable();$t->string('remember_token')->nullable();$t->boolean('is_active')->default(true);$t->timestamp('account_deleted_at')->nullable();$t->timestamps();});
        Schema::create('business_settings',function(Blueprint $t){$t->id();$t->string('type');$t->text('value');});
        Schema::create('contacts',function(Blueprint $t){$t->id();foreach(['name','email','mobile_number','subject','message','sender_type']as$c)$t->string($c);$t->string('mobile_support_token_hash',64)->nullable()->unique();$t->boolean('seen')->default(false);$t->timestamps();});
        Schema::create('contact_conversation_messages',function(Blueprint $t){$t->id();$t->integer('contact_id');$t->string('sender',20);$t->text('body');$t->timestamps();});
        (require database_path('migrations/2026_10_14_190000_customer_admin_password_recovery.php'))->up();
        Schema::create('customer_profile_changes',function(Blueprint $t){$t->id();$t->integer('customer_id');$t->string('actor_type');$t->integer('actor_id')->nullable();$t->string('subject_type');$t->integer('subject_id');$t->string('action');$t->text('changes');$t->timestamp('created_at');});
        Schema::create('oauth_access_tokens',function(Blueprint $t){$t->string('id')->primary();$t->integer('user_id');$t->boolean('revoked')->default(false);});
        Schema::create('oauth_refresh_tokens',function(Blueprint $t){$t->id();$t->string('access_token_id');$t->boolean('revoked')->default(false);});
        DB::table('users')->insert(['id'=>1,'phone'=>'01012345678','email'=>'customer@example.test','password'=>Hash::make('Original-pass-123')]);
        $admin=new Admin();$admin->id=7;auth('admin')->setUser($admin);
        $this->delivery=new class extends CustomerTemporaryPasswordDeliveryService{public string $password='';public int $count=0;public bool $failure=false;
            public function send(User $customer,string $password,object $request):void{if($this->failure)throw ValidationException::withMessages(['delivery'=>'failure']);$this->password=$password;$this->count++;}};
        app()->instance(CustomerTemporaryPasswordDeliveryService::class,$this->delivery);
    }
    private function service(){return app(CustomerPasswordRecoveryService::class);}
    private function verified():int{$this->service()->request('01012345678',str_repeat('a',64));$id=DB::table('customer_password_recovery_requests')->value('id');$this->service()->verify($id,7,'تم التحقق من صاحب الحساب');return$id;}
    public function test_request_is_deduplicated_and_unknown_identity_has_same_public_response():void {
        $this->service()->request('01012345678',str_repeat('a',64));$this->service()->request('+201012345678',str_repeat('a',64));$this->service()->request('01099999999',str_repeat('b',64));
        $this->assertSame(2,DB::table('customer_password_recovery_requests')->count());
        $this->assertNull(DB::table('customer_password_recovery_requests')->where('id',2)->value('customer_id'));
        $this->assertTrue(Hash::check('Original-pass-123',User::find(1)->password));
    }
    public function test_verified_issue_delivers_once_stores_only_hash_and_change_invalidates_temporary_password():void {
        $id=$this->verified();DB::table('oauth_access_tokens')->insert(['id'=>'old','user_id'=>1]);DB::table('oauth_refresh_tokens')->insert(['access_token_id'=>'old']);
        $this->service()->issue($id,7);$this->service()->issue($id,7);
        $temporary=$this->delivery->password;$user=User::find(1);
        $this->assertSame(1,$this->delivery->count);$this->assertTrue(Hash::check($temporary,$user->password));$this->assertTrue((bool)$user->must_change_password);
        $this->assertTrue((bool)DB::table('oauth_access_tokens')->value('revoked'));
        $this->assertTrue((bool)DB::table('oauth_refresh_tokens')->value('revoked'));
        $this->assertStringNotContainsString($temporary,json_encode(DB::table('customer_password_recovery_requests')->first()));
        $this->service()->change(1,$temporary,'New-password-123');
        $user=User::find(1);$this->assertFalse(Hash::check($temporary,$user->password));$this->assertTrue(Hash::check('New-password-123',$user->password));$this->assertFalse((bool)$user->must_change_password);
        $this->assertSame('completed',DB::table('customer_password_recovery_requests')->value('status'));
        $audit=DB::table('customer_profile_changes')->get();$this->assertStringContainsString('temporary_password_issued',$audit->toJson());$this->assertStringNotContainsString($temporary,$audit->toJson());$this->assertStringNotContainsString($user->password,$audit->toJson());
    }
    public function test_unverified_request_never_issues_password():void {
        $this->service()->request('01012345678');$this->expectException(ValidationException::class);$this->service()->issue(DB::table('customer_password_recovery_requests')->value('id'),7);
    }
    public function test_delivery_failure_rolls_back_password_and_request():void {
        $id=$this->verified();$this->delivery->failure=true;
        try{$this->service()->issue($id,7);$this->fail('delivery accepted');}catch(ValidationException $e){$this->assertArrayHasKey('delivery',$e->errors());}
        $this->assertTrue(Hash::check('Original-pass-123',User::find(1)->password));$this->assertSame('verified',DB::table('customer_password_recovery_requests')->value('status'));
    }
    public function test_expired_temporary_password_needs_another_admin_review():void {
        $id=$this->verified();$this->service()->issue($id,7);$this->travel(25)->hours();$this->service()->request('01012345678',str_repeat('a',64));
        $this->assertSame('expired',DB::table('customer_password_recovery_requests')->where('id',$id)->value('status'));
        $this->assertSame(2,DB::table('customer_password_recovery_requests')->count());
        $this->expectException(ValidationException::class);$this->service()->change(1,$this->delivery->password,'New-password-123');
    }
    public function test_api_and_web_gate_block_account_until_change():void {
        $id=$this->verified();$this->service()->issue($id,7);$user=User::find(1);
        auth('api')->setUser($user);auth('customer')->setUser($user);
        $middleware=new \App\Http\Middleware\RequireCustomerPasswordChange();
        $request=\Illuminate\Http\Request::create('/api/v1/customer/wallet/overview');
        $response=$middleware->handle($request,fn()=>response('unexpected'));
        $this->assertSame(403,$response->getStatusCode());$this->assertSame('password_change_required',$response->getData(true)['code']);
        $request=\Illuminate\Http\Request::create('/user-profile');$request->setLaravelSession(app('session')->driver());$request->session()->put('customer_password_revision',(int)$user->password_revision);
        $response=$middleware->handle($request,fn()=>response('unexpected'));$this->assertSame(route('customer.password-recovery.change-form'),$response->getTargetUrl());
    }
    public function test_password_is_encrypted_in_support_chat_and_cannot_be_read_with_another_token():void {
        app()->instance(CustomerTemporaryPasswordDeliveryService::class,new CustomerTemporaryPasswordDeliveryService());
        $id=$this->verified();$this->service()->issue($id,7);
        $message=DB::table('contact_conversation_messages')->where('sender','admin_temp_password')->first();
        $data=json_decode(\Illuminate\Support\Facades\Crypt::decryptString($message->body),true);
        $this->assertStringNotContainsString($data['password'],$message->body);
        $this->assertStringNotContainsString($data['password'],\App\Services\ContactPasswordResetService::displayBody($message));
        auth('admin')->logout();
        $request=\Illuminate\Http\Request::create('/api/v1/customer/password-recovery/conversation');$request->headers->set('X-Support-Token',str_repeat('a',64));
        $controller=new \App\Http\Controllers\Customer\PasswordRecoveryController();
        $body=$controller->conversation($request)->getData(true);
        $this->assertStringContainsString($data['password'],$body['messages'][1]['body']);
        $request->headers->set('X-Support-Token',str_repeat('c',64));
        try{$controller->conversation($request);$this->fail('Another token read recovery chat');}catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){$this->assertNotEmpty($e->getMessage());}
        $user=User::find(1);auth('customer')->setUser($user);
        $this->service()->change(1,$data['password'],'Changed-password-123');
        $this->assertStringNotContainsString($data['password'],\App\Services\ContactPasswordResetService::displayBody($message));
    }
    public function test_actual_recovery_forms_and_admin_queue_render():void {
        if(!defined('DOMAIN_POINTED_DIRECTORY'))define('DOMAIN_POINTED_DIRECTORY','public');
        session()->put(['local'=>'ar','direction'=>'rtl']);view()->share('errors',new \Illuminate\Support\ViewErrorBag());
        $id=$this->verified();
        $directory=base_path('output/customer-stage68');if(!is_dir($directory))mkdir($directory,0777,true);
        foreach(['password-recovery'=>'request','password-change-required'=>'change']as$view=>$name){
            $html=view('customer-account.'.$view)->render();$this->assertStringContainsString('name="_token"',$html);file_put_contents($directory.'/'.$name.'.html',preg_replace('/href="[^"]*\/assets\//','href="/assets/',$html));
        }
        $source=file_get_contents(resource_path('views/admin-views/customer/password-recovery.blade.php'));$start=strpos($source,"@section('content')")+strlen("@section('content')");$end=strrpos($source,'@endsection');
        $row=DB::table('customer_password_recovery_requests')->first();$row->f_name='عميل المعاينة';$row->phone='01000000000';$row->email=null;
        $html=\Illuminate\Support\Facades\Blade::render(substr($source,$start,$end-$start),['requests'=>new \Illuminate\Pagination\LengthAwarePaginator([$row],1,20),'channel'=>'support_chat']);
        $this->assertStringContainsString('إصدار وإرسال كلمة مؤقتة في الشات',$html);
        file_put_contents($directory.'/admin.html','<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/back-end/css/bootstrap.min.css"><style>body{padding:24px;font-family:Tahoma;background:#f4f7fb}.content{max-width:1200px;margin:auto}</style>'.$html.'</html>');
    }
    public function test_web_request_grants_only_its_own_chat_and_preserves_same_session():void {
        $controller=new \App\Http\Controllers\Customer\PasswordRecoveryController();
        $request=\Illuminate\Http\Request::create('/customer/password-recovery/request','POST',['identity'=>'01012345678']);
        $request->setLaravelSession(app('session')->driver());
        $response=$controller->request($request,$this->service());
        $contact=DB::table('customer_password_recovery_requests')->value('contact_id');
        $this->assertSame(route('contact.conversation',$contact),$response->getTargetUrl());
        $this->assertSame([(int)$contact],$request->session()->get('contact_conversation_ids'));
        $controller->request($request,$this->service());$this->assertSame(1,DB::table('customer_password_recovery_requests')->count());
    }
    public function test_old_web_session_is_revoked_when_password_revision_changes():void {
        $id=$this->verified();$this->service()->issue($id,7);
        auth('customer')->setUser(User::find(1));
        $request=\Illuminate\Http\Request::create('/user-profile');$request->setLaravelSession(app('session')->driver());$request->session()->put('customer_password_revision',0);
        (new \App\Http\Middleware\RequireCustomerPasswordChange())->handle($request,fn()=>response('continued'));
        $this->assertFalse(auth('customer')->check());
    }
    public function test_wrong_current_or_identical_password_does_not_unlock_account():void {
        $id=$this->verified();$this->service()->issue($id,7);
        foreach([['wrong','New-password-123'],[$this->delivery->password,$this->delivery->password]]as[$current,$new]) {
            try{$this->service()->change(1,$current,$new);$this->fail('Invalid credential changed');}catch(ValidationException $e){$this->assertNotEmpty($e->errors());}
            $this->assertTrue((bool)User::find(1)->must_change_password);
        }
    }
}
