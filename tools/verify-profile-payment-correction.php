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
$open=new App\Models\CustomerSubscriptionPayment(['terms_name'=>'Gold','quoted_amount'=>8000,'currency_code'=>'EGP','wallet_debit_amount'=>8000]);$open->id=777;
$payment=view('customer-subscription.payment-dialogs',['open'=>$open,'purchase_wallet_enabled'=>true,'purchase_balance'=>0,
    'offline_methods'=>App\Models\OfflinePaymentMethod::where('status',1)->whereIn('payment_channel',['wallet','instapay'])->get()])->render();
$manifest=json_decode(file_get_contents($release.'/profile-payment-manifest.json'),true);
$mismatch=[];foreach($manifest['files'] as $file)if(hash_file('sha256',$root.'/'.$file)!==hash_file('sha256',$release.'/correction/'.$file))$mismatch[]=$file;
$duplicates=0;foreach(array_slice($manifest['files'],0,3) as $file)$duplicates+=substr_count(file_get_contents($root.'/'.$file),'customer-profile-shortcuts');
$result=['revision'=>trim(file_get_contents($root.'/.deploy-current')),'file_mismatches'=>$mismatch,'duplicate_profile_includes'=>$duplicates,
    'original_profile_sections'=>substr_count($profile,'class="profile-overview"'),'orders_label'=>str_contains($profile,'طلبات'),
    'wallet_label'=>str_contains($profile,'رصيد المحفظة'),'payment_method_rows'=>substr_count($payment,'payment-choice-option'),
    'payment_css_versioned'=>str_contains(file_get_contents($root.'/resources/views/customer-subscription/packages.blade.php'),'customer-transfer.css\') }}?v=20261012.3')];
file_put_contents($release.'/profile-payment-verification.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
if($mismatch||$duplicates||$result['original_profile_sections']!==1)exit(1);
