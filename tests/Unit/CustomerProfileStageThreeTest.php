<?php
namespace Tests\Unit;

use App\Models\{User, ShippingAddress, Admin};
use App\Services\{CustomerContactPhoneService, CustomerProfileAuditService, GoogleMapsShippingLocationService};
use App\Http\Controllers\Customer\ContactPhoneController;
use App\Http\Controllers\Admin\Customer\CustomerProfileSnapshotController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema,Hash};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerProfileStageThreeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if(!defined('DOMAIN_POINTED_DIRECTORY')) define('DOMAIN_POINTED_DIRECTORY','public');
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('f_name');$t->string('l_name');$t->string('phone');$t->string('canonical_phone')->nullable();$t->string('email')->nullable();$t->string('password');$t->string('image')->nullable();$t->timestamps();});
        Schema::create('storages',function(Blueprint $t){$t->id();$t->string('data_type');$t->unsignedBigInteger('data_id');$t->string('key');$t->string('value');$t->timestamps();});
        Schema::create('shipping_addresses',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('customer_id');$t->boolean('is_guest')->default(false);
            foreach(array_diff(CustomerProfileAuditService::ADDRESS_FIELDS,['floor','unit_number','company_details','is_billing']) as $field) $t->string($field)->nullable();
            $t->boolean('is_billing')->default(false);$t->timestamps();
            foreach(['latitude','longitude','dispatch_point_id','shipping_zone_rate_id','distance_km','geocode_status','geocoded_address','geocoded_at'] as $field) $t->string($field)->nullable();
        });
        (require database_path('migrations/2026_10_13_100000_customer_profile_contacts_and_history.php'))->up();
        foreach([1,2] as $id) DB::table('users')->insert(['id'=>$id,'f_name'=>'عميل','l_name'=>(string)$id,'phone'=>$id===1?'01012345678':'01112345678','password'=>Hash::make('OldPassword123'),'created_at'=>now(),'updated_at'=>now()]);
        auth('customer')->setUser(User::find(1));
    }

    public function test_contacts_add_edit_remove_normalize_and_audit_without_changing_login_phone(): void
    {
        $service=app(CustomerContactPhoneService::class);
        $this->assertSame(['01212345678','01512345678'],$service->replace(1,['+201212345678','01512345678']));
        $this->assertSame('01012345678',User::find(1)->phone);
        $this->assertSame(['01199999999'],$service->replace(1,['01199999999']));
        $this->assertSame([],$service->replace(1,[]));
        $this->assertSame(3,DB::table('customer_profile_changes')->count());
        $this->assertSame([], $service->list(2));
        foreach([['01212345678','+201212345678'],['01012345678'],['bad']] as $phones){
            try{$service->replace(1,$phones);$this->fail('Invalid contact accepted');}catch(ValidationException $e){$this->assertNotEmpty($e->errors());}
        }
        $this->assertSame(3,DB::table('customer_profile_changes')->count());
    }

    public function test_profile_changes_record_old_and_new_values_but_never_password_hashes(): void
    {
        $user=User::find(1);
        DB::transaction(fn()=>$user->update(['email'=>'new@example.test','phone'=>'01511112222','password'=>Hash::make('NewPassword456'),'image'=>'new-avatar.webp']));
        $changes=json_decode(DB::table('customer_profile_changes')->first()->changes,true);
        $this->assertSame(['from'=>null,'to'=>'new@example.test'],$changes['email']);
        $this->assertSame(['from'=>'01012345678','to'=>'01511112222'],$changes['phone']);
        $this->assertSame(['changed'=>true],$changes['password']);
        $json=json_encode($changes);
        foreach(['OldPassword123','NewPassword456','$2y$','current_password'] as $secret) $this->assertStringNotContainsString($secret,$json);
        $before=DB::table('customer_profile_changes')->count();
        try{DB::transaction(function(){User::find(1)->update(['f_name'=>'لن يحفظ']);throw new \RuntimeException('rollback');});}catch(\RuntimeException $e){}
        $this->assertSame('عميل',User::find(1)->f_name);
        $this->assertSame($before,DB::table('customer_profile_changes')->count());
    }

    public function test_multiple_addresses_and_optional_details_round_trip_and_log_mutations(): void
    {
        $first=ShippingAddress::create(['customer_id'=>1,'is_guest'=>0,'district'=>'حي أول','area'=>'منطقة مختلفة','street'=>'شارع','building_number'=>'12','floor'=>'الثالث','unit_number'=>'7','company_details'=>'مكتب سيجما']);
        $second=ShippingAddress::create(['customer_id'=>1,'is_guest'=>0,'district'=>'حي ثان','area'=>'منطقة ثانية','floor'=>'الأرضي']);
        $this->assertSame('منطقة مختلفة',$first->fresh()->area);
        $this->assertSame('7',$first->fresh()->unit_number);
        $first->update(['floor'=>'الرابع']);$second->delete();
        $this->assertSame(['address_created','address_created','address_updated','address_deleted'],DB::table('customer_profile_changes')->orderBy('id')->pluck('action')->all());
        $this->assertSame(['from'=>'الثالث','to'=>'الرابع'],json_decode(DB::table('customer_profile_changes')->where('action','address_updated')->value('changes'),true)['floor']);
        $fields=app(GoogleMapsShippingLocationService::class)->persistedLocationFields(['floor'=>'3','unit_number'=>'8','company_details'=>'مكتب'],['status'=>'needs_review']);
        $this->assertSame('8',$fields['unit_number']);
        $oldClient=app(GoogleMapsShippingLocationService::class)->persistedLocationFields([],['status'=>'needs_review']);
        $this->assertArrayNotHasKey('floor',$oldClient);
    }

    public function test_api_uses_authenticated_owner_and_admin_snapshot_is_private_and_paginated(): void
    {
        $user=User::find(1);auth('api')->setUser($user);
        $request=Request::create('/api/v1/customer/contact-phones','PUT',['customer_id'=>2,'phones'=>['01212345678']]);
        $request->setUserResolver(fn()=>$user);app()->instance('request',$request);
        app(ContactPhoneController::class)->update($request,app(CustomerContactPhoneService::class));
        $this->assertSame(['01212345678'],app(CustomerContactPhoneService::class)->list(1));
        $this->assertSame([],app(CustomerContactPhoneService::class)->list(2));
        $admin=new Admin();$admin->id=1;auth('admin')->setUser($admin);
        $response=app(CustomerProfileSnapshotController::class)->index(Request::create('/admin/customer/profile-snapshot/1'),1)->getData(true);
        $this->assertSame(['01212345678'],$response['customer']['contact_phones']);
        $this->assertSame('contact_phones_updated',$response['history'][0]['action']);
        $this->assertArrayNotHasKey('password',$response['customer']);
        $this->assertContains('module:people',app('router')->getRoutes()->getByName('admin.customer.profile-snapshot')->gatherMiddleware());
        auth('admin')->forgetUser();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CustomerProfileSnapshotController::class)->index(Request::create('/'),1);
    }

    public function test_actual_profile_form_fragments_render_contact_and_address_controls(): void
    {
        app()->instance('request',Request::create('/user-profile'));
        session()->put('local','ar');session()->put('direction','rtl');
        app(CustomerContactPhoneService::class)->replace(1,['01212345678','01512345678']);
        User::find(1)->update(['f_name'=>'أحمد','l_name'=>'محمد','email'=>'customer@example.test','password'=>Hash::make('LocalTestPassword123')]);
        ShippingAddress::create(['customer_id'=>1,'is_guest'=>0,'contact_person_name'=>'أحمد محمد','district'=>'مدينة نصر','area'=>'الحي العاشر','street'=>'شارع الاختبار','building_number'=>'12','floor'=>'3','unit_number'=>'7','company_details'=>'مكتب سيجما']);
        $view=\Illuminate\Support\Facades\Blade::render('@include("customer-account.contact-phones") @stack("script")',['errors'=>new \Illuminate\Support\ViewErrorBag()]);
        $this->assertStringContainsString('01212345678',$view);
        $this->assertStringContainsString('01512345678',$view);
        $address=view('customer-account.address-extra-fields',['addressDetails'=>['floor'=>'3','unit_number'=>'7','company_details'=>'مكتب سيجما']])->render();
        $this->assertStringContainsString('name="unit_number"',$address);
        $this->assertStringContainsString('value="7"',$address);
        $scripts=app('view')->yieldPushContent('script');
        $view=str_replace('http://localhost','http://127.0.0.1:8769',$view);
        $directory=base_path('output/customer-stage3');if(!is_dir($directory))mkdir($directory,0777,true);
        $style='<style>body{font:16px Arial,sans-serif;color:#172942;background:#f4f7fb;margin:0}.container{max-width:900px;padding:24px;margin:auto}.card{background:white;padding:24px;border-radius:12px}.d-flex{display:flex}.gap-2{gap:8px}.mb-2,.mb-3{margin-bottom:12px}input{padding:12px;border:1px solid #dce4f0;border-radius:8px;box-sizing:border-box;max-width:100%;width:100%}.btn,button{padding:12px;border-radius:8px;border:1px solid #1a428a;cursor:pointer;background:white;color:#1a428a;min-height:44px}.btn-primary{background:#1a428a;color:white}p{line-height:1.8}label{display:block;margin-bottom:8px}.text-danger{color:#a01818}h2{font-size:22px}fieldset{border:0;padding:16px 0}a{color:#1a428a}</style>';
        $html='<!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.$style.'</head><body><main class="container"><h1>الملف الشخصي — معاينة اختبار</h1><p>هذه بيانات اصطناعية وليست حساباً فعلياً.</p>'.$view.'<form><fieldset><legend>تفاصيل العنوان</legend>'.$address.'</fieldset></form></main>'.$scripts.'</body></html>';
        file_put_contents($directory.'/profile-preview.html',$html);
        $admin=new Admin();$admin->id=1;auth('admin')->setUser($admin);
        $snapshot=app(CustomerProfileSnapshotController::class)->index(Request::create('/'),1)->getData(true);
        file_put_contents($directory.'/snapshot.json',json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        $section=view('admin-views.customer.profile-history',['customer'=>User::find(1)])->render();
        $section=str_replace('http://localhost','http://127.0.0.1:8769',$section);
        file_put_contents($directory.'/admin-preview.html','<!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.$style.'</head><body><main class="container"><h1>ملف العميل في الإدارة — معاينة اختبار</h1>'.$section.'</main><script src="/assets/back-end/js/customer-profile-history.js"></script></body></html>');
    }

    public function test_address_and_profile_templates_compile_to_valid_php(): void
    {
        foreach(['resources/views/customer-account/contact-phones.blade.php','resources/views/customer-account/address-extra-fields.blade.php','resources/views/admin-views/customer/profile-history.blade.php','resources/views/admin-views/customer/customer-view.blade.php',
            'resources/themes/default/web-views/users-profile/user-account.blade.php','resources/themes/theme_aster/theme-views/users-profile/profile/update-user-profile.blade.php','resources/themes/theme_sigma_care/theme-views/users-profile/profile/update-user-profile.blade.php',
            'resources/themes/default/web-views/users-profile/account-address.blade.php','resources/themes/default/web-views/users-profile/account-address-edit.blade.php','resources/themes/theme_aster/theme-views/users-profile/profile/account-address-add.blade.php','resources/themes/theme_aster/theme-views/users-profile/profile/account-address-edit.blade.php',
            'resources/themes/default/web-views/checkout/shipping.blade.php','resources/themes/theme_aster/theme-views/checkout/shipping.blade.php'] as $relative){
            $temporary=tempnam(sys_get_temp_dir(),'customer-profile-');
            try{file_put_contents($temporary,app('blade.compiler')->compileString(file_get_contents(base_path($relative))));
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,'-l',$temporary]);$process->run();$this->assertTrue($process->isSuccessful(),$relative.': '.$process->getOutput());
            }finally{unlink($temporary);}
        }
    }

    public function test_checkout_saves_new_address_for_customer_and_reuses_owned_selection(): void
    {
        $location=\Mockery::mock(GoogleMapsShippingLocationService::class)->makePartial();
        $location->shouldReceive('resolve')->andReturn(['status'=>'needs_review']);app()->instance(GoogleMapsShippingLocationService::class,$location);
        $service=app(\App\Services\CustomerCheckoutAddressService::class);
        $input=['contact_person_name'=>'عميل','address_type'=>'home','phone'=>'01012345678','address'=>'عنوان للاختبار','city'=>'القاهرة','state'=>'القاهرة','district'=>'حي','area'=>'منطقة أخرى','street'=>'شارع','building_number'=>'12','floor'=>'3','unit_number'=>'7','company_details'=>'مكتب'];
        $id=$service->choose(1,false,$input+['shipping_method_id'=>0]);
        $this->assertSame(1,ShippingAddress::find($id)->customer_id);
        $this->assertSame('7',ShippingAddress::find($id)->unit_number);
        $this->assertSame($id,$service->choose(1,false,['shipping_method_id'=>$id]));
        $this->assertSame(1,ShippingAddress::count());
        $service->choose(1,false,array_merge($input,['shipping_method_id'=>$id,'update_address'=>'on','floor'=>'4']));
        $this->assertSame('4',ShippingAddress::find($id)->floor);
        $this->assertSame(2,DB::table('customer_profile_changes')->count());
        $foreign=ShippingAddress::create(['customer_id'=>2,'is_guest'=>0,'address'=>'خاص بعميل آخر']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->choose(1,false,['shipping_method_id'=>$foreign->id]);
    }

    public function test_admin_change_is_attributed_to_admin_even_with_customer_session(): void
    {
        $admin=new Admin();$admin->id=77;auth('admin')->setUser($admin);
        $request=Request::create('/admin/customer/reset-password/1','POST');
        $route=(new \Illuminate\Routing\Route('POST','admin/customer/reset-password/1',[]))->name('admin.customer.reset-password');
        $request->setRouteResolver(fn()=>$route);app()->instance('request',$request);
        User::find(1)->update(['password'=>Hash::make('LocalAdminReset123')]);
        $entry=DB::table('customer_profile_changes')->first();
        $this->assertSame('admin',$entry->actor_type);$this->assertSame(77,$entry->actor_id);
        $this->assertSame(['password'=>['changed'=>true]],json_decode($entry->changes,true));
    }
}
