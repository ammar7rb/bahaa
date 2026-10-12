<?php
if(PHP_SAPI!=='cli')exit(1);
putenv('APP_RUNNING_IN_CONSOLE=false');const DOMAIN_POINTED_DIRECTORY='public';
$root='/home/sigma-eg/htdocs/sigma-eg.net';$release='/home/sigma-eg/git/customer-monthly-release-20261012';
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';
$app->instance('request',Illuminate\Http\Request::create('https://sigma-eg.net/products'));
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
session()->put(['local'=>'ar','direction'=>'rtl']);view()->share('errors',new Illuminate\Support\ViewErrorBag());
$fake=new App\Models\User(['f_name'=>'معاينة غير محفوظة','is_active'=>1,'wallet_balance'=>0]);$fake->id=0;auth('customer')->setUser($fake);
$app->instance(App\Services\CustomerPurchaseEligibilityService::class,new class extends App\Services\CustomerPurchaseEligibilityService { public function status(object $customer): array{return [];} });
$app->instance(App\Services\CustomerMonthlyAllowanceService::class,new class extends App\Services\CustomerMonthlyAllowanceService {
    public function enabled():bool{return true;}
    public function summary(int $customerId,float $cartAmount=0,bool $provisional=false):array{return ['has_active_package'=>true,'provisional'=>false,'package_name'=>'Gold','granted_limit'=>5000,'available_limit'=>4000,'cart_reserved_amount'=>0,'projected_remaining'=>4000-$cartAmount,'indicator'=>'green'];}
});
$bar=view('web-views.users-profile.partials.purchasing-limit-strip',['limitVariant'=>'browsing'])->render();
$card=view('web-views.users-profile.partials.purchasing-limit-strip',['limitVariant'=>'cart'])->render();
$html='<!doctype html><html lang="ar" dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/front-end/css/customer-credit-indicator.css"><style>@font-face{font-family:Care;src:url(/assets/front-end/fonts/brand/vazirmatn.ttf)}body{margin:0;padding:24px;background:#f4f7fb;color:#172942;font-family:Care,sans-serif}.qa-content{max-width:1120px;margin:auto}.qa-columns{display:grid;grid-template-columns:1fr 340px;gap:24px}.qa-product{background:white;border:1px solid #dce4f0;border-radius:16px;padding:24px;margin:16px 0}input{font:inherit;padding:12px;width:80px}button{font:inherit;padding:12px 24px;border:0;border-radius:10px;background:#1a428a;color:white}@media(max-width:700px){.qa-columns{grid-template-columns:1fr}body{padding:16px}}</style><main class="qa-content">'.$bar.'<div class="qa-columns"><section class="qa-product"><h2>سلة المشتريات</h2><p>منتج معاينة · صافي سعر الوحدة 600 ج.م</p><label>الكمية <input data-cart="1" type="number" min="1" value="1"></label><p>معاينة غير محفوظة لا تنشئ طلبًا أو دفعة.</p></section><aside>'.$card.'<button id="proceed-to-next-action" type="button">انتقل إلى التالي</button></aside></div></main><script src="/assets/front-end/js/customer-purchasing-limit.js"></script></html>';
$html=str_replace(['data-cart="0"','data-items=\'[]\''],['data-cart="600"','data-items=\'[{"id":1,"price":600,"quantity":1}]\''],$html);
file_put_contents($release.'/live-credit-preview.html',$html);
$manifest=json_decode(file_get_contents($release.'/live-credit-manifest.json'),true);$match=true;
foreach($manifest['files'] as $file)$match=$match&&hash_file('sha256',$root.'/'.$file)===hash_file('sha256',$release.'/live-credit/'.$file);
$summary=file_get_contents($root.'/resources/themes/theme_sigma_care/theme-views/partials/_order-summery.blade.php');
$results=['files_match'=>$match,'cart_has_indicator'=>str_contains($summary,'purchasing-limit-strip'),'cart_has_no_coupon_form'=>!str_contains($summary,'submit-coupon-code'),'cart_label'=>translate('view_cart')==='عرض سلة المشتريات','active_browsing_bar'=>str_contains($bar,'credit-indicator--browsing'),'bar_css_loaded'=>str_contains(file_get_contents($root.'/resources/themes/theme_sigma_care/theme-views/layouts/app.blade.php'),'customer-credit-indicator.css')];
echo json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;if(in_array(false,$results,true))exit(1);
