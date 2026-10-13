<?php
if(PHP_SAPI!=='cli')exit(1);
putenv('APP_RUNNING_IN_CONSOLE=false');const DOMAIN_POINTED_DIRECTORY='public';
$root='/home/sigma-eg/htdocs/sigma-eg.net';$release='/home/sigma-eg/git/customer-monthly-release-20261012';
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';$app->instance('request',Illuminate\Http\Request::create('https://sigma-eg.net/account-order-tracking'));
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();session()->put(['local'=>'ar','direction'=>'rtl']);view()->share('errors',new Illuminate\Support\ViewErrorBag());
$fake=new App\Models\User();$fake->forceFill(['id'=>999999999,'f_name'=>'عميل المعاينة','l_name'=>'','phone'=>'01012345678','email'=>'preview@example.test','is_active'=>1,'is_phone_verified'=>1,'created_at'=>now()]);auth('customer')->setUser($fake);
try {
$editor=view('theme-views.users-profile.profile.update-user-profile',['customerDetail'=>$fake,'country_restrict_status'=>false])->render();
$order=new App\Models\Order();$order->forceFill(['id'=>999999999,'customer_id'=>$fake->id,'order_status'=>'processing','payment_status'=>'paid','created_at'=>now()]);
$tracking=view('customer-account.tracking',['orders'=>new Illuminate\Pagination\LengthAwarePaginator(collect([$order]),1,12)])->render();
$supportRequest=Illuminate\Http\Request::create('https://sigma-eg.net/account-messages?password_help=1');$app->instance('request',$supportRequest);
$support=view('customer-support.index',['conversations'=>collect()])->render();
foreach(['editor'=>$editor,'tracking'=>$tracking,'support'=>$support] as $name=>$html)file_put_contents($release.'/tracking-'.$name.'-preview.html',$html);
$manifest=json_decode(file_get_contents($release.'/tracking-security-manifest.json'),true);$match=true;foreach($manifest['files'] as $file)$match=$match&&hash_file('sha256',$root.'/'.$file)===hash_file('sha256',$release.'/tracking-security/'.$file);
$result=['files_match'=>$match,'current_password_input'=>str_contains($editor,'name="current_password"'),'image_picker_fixed'=>str_contains($editor,'profile-image-picker')&&!str_contains($editor,'upload-file__input'),'tracking_page'=>str_contains($tracking,'تتبع الطلبات'),'tracking_sidebar_link'=>str_contains($tracking,'account-order-tracking'),'subscription_menu_label'=>str_contains($tracking,'الاشتراكات'),'tracking_protected'=>in_array('customer',app('router')->getRoutes()->getByName('customer.tracking.index')->gatherMiddleware()),'password_support_help'=>str_contains($support,'لا ترسل كلمة مرورك')];
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;if(in_array(false,$result,true))exit(1);
} catch(Throwable $e){echo get_class($e).': '.mb_substr($e->getMessage(),0,600).PHP_EOL;exit(1);}
