<?php
if(PHP_SAPI!=='cli')exit(1);
putenv('APP_RUNNING_IN_CONSOLE=false');const DOMAIN_POINTED_DIRECTORY='public';
$root='/home/sigma-eg/htdocs/sigma-eg.net';$release='/home/sigma-eg/git/customer-monthly-release-20261012';
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';
$app->instance('request',Illuminate\Http\Request::create('https://sigma-eg.net/account-notifications'));
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();session()->put(['local'=>'ar','direction'=>'rtl']);view()->share('errors',new Illuminate\Support\ViewErrorBag());
$fake=new App\Models\User(['f_name'=>'عميل المعاينة','l_name'=>'','is_active'=>1,'wallet_balance'=>0,'created_at'=>now()]);$fake->id=999999999;auth('customer')->setUser($fake);
$notices=new Illuminate\Pagination\LengthAwarePaginator(collect([new App\Models\Notification(['title'=>'تحديث من الإدارة','description'=>'ستجد تحديثات حسابك وطلباتك هنا. هذه معاينة غير محفوظة.','created_at'=>now()])]),1,20);
try {
$notices->getCollection()->each(fn($notice)=>$notice->setAttribute('created_at',now()));
$notificationPage=view('customer-account.notifications',['notifications'=>$notices])->render();
$message=new App\Models\SupportTicket(['subject'=>'التواصل مع الدعم','description'=>'رسائلك وردود فريق الدعم محفوظة في هذه المحادثة. هذه معاينة غير محفوظة.','created_at'=>now(),'updated_at'=>now()]);$message->id=999999999;
$messagePage=view('customer-support.index',['conversations'=>collect([$message])])->render();
$open=new App\Models\CustomerSubscriptionPayment(['terms_name'=>'Diamond','quoted_amount'=>10000,'wallet_debit_amount'=>10000,'currency_code'=>'EGP','granted_allowance'=>8000,'package_id'=>24,'status'=>'draft','purchase_kind'=>'activation']);$open->id=999999999;
$package=new App\Models\CustomerPurchasePackage(['name'=>'Diamond','package_price'=>10000,'purchase_limit'=>8000,'description'=>'باقة للاحتياجات الشهرية','status'=>1]);$package->id=24;
$packagePage=view('customer-subscription.packages',['subscription'=>['active'=>false,'open_payment'=>$open],'packages'=>collect([$package]),'increase_offers'=>collect(),'purchase_wallet_enabled'=>true,'purchase_balance'=>0,'offline_methods'=>collect(),'payments'=>new Illuminate\Pagination\LengthAwarePaginator([],0,10)])->render();
foreach(['notifications'=>$notificationPage,'messages'=>$messagePage,'payment'=>$packagePage] as $name=>$html)file_put_contents($release.'/communication-'.$name.'-preview.html',$html);
$controller=app(App\Http\Controllers\Customer\NotificationCenterController::class)->index();
$manifest=json_decode(file_get_contents($release.'/communication-center-manifest.json'),true);$matches=true;foreach($manifest['files'] as $file)$matches=$matches&&hash_file('sha256',$root.'/'.$file)===hash_file('sha256',$release.'/communication-center/'.$file);
$sidebar=file_get_contents($root.'/resources/themes/theme_sigma_care/theme-views/partials/_profile-aside.blade.php');
$results=['files_match'=>$matches,'notification_page_rendered'=>str_contains($notificationPage,'تحديث من الإدارة'),'messages_page_rendered'=>str_contains($messagePage,'رسائلك وردود'),'pending_payment_refined'=>str_contains($packagePage,'package-pending-facts'),'profile_credit_footer_removed'=>!str_contains(file_get_contents($root.'/resources/views/customer-subscription/profile-overview.blade.php'),'profile-credit-details'),'navigation_under_profile'=>strpos($sidebar,'sidebar-communication')>strpos($sidebar,"route('user-profile')")];
foreach(['customer.notifications.index','customer.messages.index'] as $name)$results[$name.'_protected']=in_array('customer',app('router')->getRoutes()->getByName($name)->gatherMiddleware());
echo json_encode($results,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;if(in_array(false,$results,true))exit(1);

} catch (Throwable $e) { echo get_class($e).': '.mb_substr($e->getMessage(),0,600).PHP_EOL;exit(1); }
