<?php
if (PHP_SAPI!=='cli') exit(1);
putenv('APP_RUNNING_IN_CONSOLE=false');
const DOMAIN_POINTED_DIRECTORY='public';
$root='/home/sigma-eg/htdocs/sigma-eg.net';$release='/home/sigma-eg/git/customer-monthly-release-20261012';
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';
$app->instance('request',Illuminate\Http\Request::create('https://sigma-eg.net/user-profile'));
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
session()->put(['local'=>'ar','direction'=>'rtl']);view()->share('errors',new Illuminate\Support\ViewErrorBag());
$fake=new App\Models\User(['f_name'=>'معاينة غير محفوظة','is_active'=>1,'wallet_balance'=>0]);$fake->id=0;auth('customer')->setUser($fake);
$profile=view('customer-subscription.profile-overview',['total_order'=>3,'wishlists'=>0,'totalWalletBalance'=>0])->render();
auth('admin')->setUser(App\Models\Admin::firstOrFail());
$admin=app(App\Http\Controllers\Admin\Customer\MonthlyPackageController::class)->index(Illuminate\Http\Request::create('/'))->render();
$manifest=json_decode(file_get_contents($release.'/credit-summary-manifest.json'),true);
$matches=true;foreach($manifest['files'] as $file)$matches=$matches&&hash_file('sha256',$root.'/'.$file)===hash_file('sha256',$release.'/credit-summary/'.$file);
$route=app('router')->getRoutes()->getByName('admin.monthly-packages.offer-delete');
$results=['files_match'=>$matches,'profile_package_name'=>str_contains($profile,'اسم الباقة'),'profile_remaining_credit'=>str_contains($profile,'الحد الائتماني المتبقي'),
    'old_sentence_removed'=>!str_contains($profile,'الممنوح'),'add_offer_label'=>str_contains($admin,'إضافة عرض زيادة جديد'),'edit_offer_label'=>str_contains($admin,'حفظ تعديلات العرض'),
    'delete_offer_button'=>str_contains($admin,'حذف عرض'),'protected_delete_route'=>in_array('DELETE',$route->methods())&&in_array('admin',$route->gatherMiddleware())&&in_array('module:people',$route->gatherMiddleware())];
echo json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
if(in_array(false,$results,true))exit(1);
