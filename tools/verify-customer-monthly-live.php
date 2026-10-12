<?php
// Private, read-only server verification. No HTTP route and no persisted account impersonation.
if (PHP_SAPI !== 'cli') exit(1);
putenv('APP_RUNNING_IN_CONSOLE=false');
$root = '/home/sigma-eg/htdocs/sigma-eg.net';
$release = '/home/sigma-eg/git/customer-monthly-release-20261012';
const DOMAIN_POINTED_DIRECTORY = 'public';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$request = Illuminate\Http\Request::create('https://sigma-eg.net/customer/subscription');
$app->instance('request',$request);
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
if (($argv[1] ?? '') === 'diagnose-http') {
    $app->instance(Illuminate\Contracts\Debug\ExceptionHandler::class, new class($app) extends Illuminate\Foundation\Exceptions\Handler {
        public function render($request, Throwable $error) { echo get_class($error).': '.$error->getMessage().PHP_EOL; return response('diagnostic',500); }
        public function report(Throwable $error) {}
    });
    $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle(Illuminate\Http\Request::create('https://sigma-eg.net/','GET'));
    echo 'HTTP '.$response->getStatusCode().PHP_EOL;
    exit;
}
session()->put(['local'=>'ar','direction'=>'rtl']);
view()->share('errors',new Illuminate\Support\ViewErrorBag());
view()->addLocation(resource_path('themes/'.theme_root_path()));
if (!defined('VIEW_FILE_NAMES')) define('VIEW_FILE_NAMES', require resource_path('themes/'.theme_root_path().'/file_names.php'));
if (theme_root_path() === 'theme_sigma_care') view()->addLocation(resource_path('themes/theme_aster'));
view()->addLocation(resource_path('themes/default'));
if (($argv[1] ?? '') === 'diagnose-home') {
    auth('customer')->forgetUser();
    try { app(App\Http\Controllers\Web\HomeController::class)->index()->render(); echo "HOME_RENDER_OK\n"; }
    catch (Throwable $error) { echo get_class($error).': '.$error->getMessage().PHP_EOL; }
    exit;
}
$manifest = json_decode(file_get_contents($release.'/monthly-release-manifest.json'),true);
$mismatch = [];
foreach ($manifest['files'] as $file) if (hash_file('sha256',$root.'/'.$file) !== hash_file('sha256',$release.'/next/'.$file)) $mismatch[] = $file;
$packages = App\Models\CustomerPurchasePackage::where('status',1)->where('is_custom',0)->orderBy('sort_order')->get();
$offers = Illuminate\Support\Facades\DB::table('customer_credit_offers')->where('active',true)->get();
$fake = new App\Models\User(['f_name'=>'فحص غير محفوظ','is_active'=>1]); $fake->id=0;
auth('customer')->setUser($fake);
$payload = ['subscription'=>['active'=>false,'status'=>'inactive','package_name'=>null,'package_id'=>null,'open_payment'=>null],
    'packages'=>$packages,'increase_offers'=>$offers,'purchase_wallet_enabled'=>false,'purchase_balance'=>0,
    'offline_methods'=>collect(),'payments'=>new Illuminate\Pagination\LengthAwarePaginator([],0,10)];
$html = view('customer-subscription.packages',$payload)->render();
$active = $payload;
$active['subscription'] = ['active'=>true,'status'=>'active','package_id'=>$packages->first()?->id,'package_name'=>$packages->first()?->name ?? 'معاينة غير محفوظة',
    'remaining_days'=>10,'expires_at'=>now()->addDays(10)->toIso8601String(),'open_payment'=>null,
    'allowance'=>['granted_limit'=>3000,'base_limit'=>3000,'committed_limit'=>0,'available_limit'=>3000,'package_price'=>100]];
$activeHtml = view('customer-subscription.packages',$active)->render();
$admin=App\Models\Admin::where('admin_role_id',1)->firstOrFail();
auth('admin')->setUser($admin);
$adminHtml=view('admin-views.customer.monthly-packages',['packages'=>App\Models\CustomerPurchasePackage::orderBy('sort_order')->get(),'offers'=>$offers,'tab'=>'customers'])->render();
$summary = ['revision'=>trim(file_get_contents($root.'/.deploy-current')),'file_mismatches'=>$mismatch,
    'theme'=>theme_root_path(),'available_packages'=>$packages->count(),'configured_increase_offers'=>$offers->count(),
    'inactive_page_rendered'=>str_contains($html,'باقة تناسبك'),'active_page_rendered'=>str_contains($activeHtml,'طوّر باقتك على راحتك'),
    'theme_font_css'=>str_contains($html,'customer-packages.css'),'old_purchase_limit_label_absent'=>!str_contains($html,'حد الشراء'),
    'old_duration_field_absent'=>!str_contains($html,'30 يومًا'),'increase_options'=>substr_count($activeHtml,'type="radio" name="quantity"'),
    'admin_page_rendered'=>str_contains($adminHtml,'credit-offers'),
    'admin_offer_multiplier_preview'=>substr_count($adminHtml,'<td>×'),
    'routes'=>collect(Illuminate\Support\Facades\Route::getRoutes())->filter(fn($route)=>str_contains($route->uri(),'monthly-packages'))->map(fn($route)=>$route->uri())->values()->all()];
file_put_contents($release.'/post-deploy-verification.json',json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
if ($mismatch || !$summary['inactive_page_rendered'] || !$summary['active_page_rendered']) exit(1);
