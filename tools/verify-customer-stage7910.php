<?php
if(PHP_SAPI!=='cli')exit(1);
define('DOMAIN_POINTED_DIRECTORY','public');putenv('APP_RUNNING_IN_CONSOLE=false');
$root='/home/sigma-eg/htdocs/sigma-eg.net';$release='/home/sigma-eg/git/customer-stage7910-20261014';
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';
$request=Illuminate\Http\Request::create('https://sigma-eg.net/customer/account-deletion');$app->instance('request',$request);
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();session()->put(['local'=>'ar','direction'=>'rtl']);view()->share('errors',new Illuminate\Support\ViewErrorBag());
view()->addLocation(resource_path('themes/'.theme_root_path()));
if(!defined('VIEW_FILE_NAMES'))define('VIEW_FILE_NAMES',require resource_path('themes/'.theme_root_path().'/file_names.php'));
view()->addLocation(resource_path('themes/theme_aster'));view()->addLocation(resource_path('themes/default'));
set_exception_handler(function(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage().PHP_EOL);exit(1);});
$checks=[];$manifest=json_decode(file_get_contents($release.'/backend-manifest.json'),true);
$checks['revision']=trim(file_get_contents($root.'/.deploy-current'))===$manifest['commit'];
$checks['files_match']=true;foreach($manifest['deployFiles'] as $f)$checks['files_match']=$checks['files_match']&&hash('sha256',preg_replace('/^\xEF\xBB\xBF/','',str_replace("\r\n","\n",file_get_contents($root.'/'.$f))))===$manifest['hashes'][$f];
$page=App\Models\BusinessPage::where('slug','veterinary-services')->firstOrFail();
$checks['single_published_page']=App\Models\BusinessPage::where('slug','veterinary-services')->count()===1&&$page->status===1;
foreach(['ar'=>'خدمات بيطرية','en'=>'Veterinary Services'] as $locale=>$title){
 app()->setLocale($locale);
 $checks['page_'.$locale]=$page->title===$title&&str_contains($page->description,'<h2>');
 $api=(new App\Http\Controllers\RestAPI\v1\ConfigController)->getBusinessPagesList(new App\Http\Requests\Request(['type'=>'pages']));
 $checks['api_'.$locale]=$api->firstWhere('slug','veterinary-services')?->title===$title;
}
$customer=App\Models\User::where('id','>',0)->whereNull('account_deleted_at')->firstOrFail();auth('customer')->setUser($customer);app()->setLocale('ar');
$html=app(App\Http\Controllers\Customer\AccountDeletionController::class)->show()->render();
$checks['confirmation_view']=str_contains($html,'نعم، حذف الحساب')&&str_contains($html,'name="confirmation"')&&!str_contains($html,'deletion-password')&&!str_contains($html,'deletion-reason');
file_put_contents($release.'/confirmation-preview.html',$html);
$checks['web_guard']=in_array('customer',app('router')->getRoutes()->getByName('customer.deletion.confirm')->gatherMiddleware(),true);
$checks['three_packages']=app(App\Services\CustomerPackageCatalogService::class)->query()->count()===3;
$summary=app(App\Services\CustomerMonthlySubscriptionService::class)->status((int)$customer->id);
$checks['subscription_read']=isset($summary['status']);
$walletRequest=Illuminate\Http\Request::create('https://sigma-eg.net/wallet');
$wallet=app(App\Services\CustomerWalletStatementService::class)->query((int)$customer->id,$walletRequest)->limit(10)->get();
$checks['wallet_read']=count(app(App\Services\CustomerWalletStatementService::class)->decorate($wallet->all()))===$wallet->count();
echo json_encode($checks,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
file_put_contents($release.'/verification.json',json_encode($checks,JSON_PRETTY_PRINT));exit(in_array(false,$checks,true)?1:0);
