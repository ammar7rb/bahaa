<?php
// Explicitly authorized demonstration configuration, CLI only, kept outside the public site.
if (PHP_SAPI !== 'cli') exit(1);
$root='/home/sigma-eg/htdocs/sigma-eg.net';
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (($argv[1] ?? '') === 'apply') {
    $result=Illuminate\Support\Facades\DB::transaction(function () {
        $adminId=App\Models\Admin::where('admin_role_id',1)->orderBy('id')->value('id');
        if (!$adminId) throw new RuntimeException('Administrator identity required');
        $packageIds=[];
        foreach ([['Silver',5000,3000],['Gold',8000,5000],['Diamond',10000,8000]] as [$name,$price,$limit]) {
            $existing=App\Models\CustomerPurchasePackage::where('name',$name)->where('is_custom',0)->first();
            if (!$existing) $existing=App\Models\CustomerPurchasePackage::create([
                'name'=>$name,'package_price'=>$price,'purchase_limit'=>$limit,'status'=>1,'is_custom'=>0,
                'duration_unit'=>'day','duration_value'=>30,'sort_order'=>count($packageIds)+10,
                'description'=>'بيانات تجريبية قابلة للتعديل من الإدارة.', 'created_by_admin_id'=>$adminId,'updated_by_admin_id'=>$adminId,
            ]);
            $packageIds[$name]=$existing->id;
        }
        $offer=Illuminate\Support\Facades\DB::table('customer_credit_offers')->where('name','زيادة الحد الائتماني')->first();
        $offerId=$offer?->id ?? Illuminate\Support\Facades\DB::table('customer_credit_offers')->insertGetId([
            'name'=>'زيادة الحد الائتماني','price'=>1000,'allowance'=>5000,'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return ['demo_package_ids'=>$packageIds,'demo_offer_id'=>$offerId];
    });
    file_put_contents('/home/sigma-eg/git/customer-monthly-release-20261012/demo-configuration.json',json_encode($result,JSON_PRETTY_PRINT));
}
echo json_encode([
    'packages'=>App\Models\CustomerPurchasePackage::where('is_custom',0)->get(['id','name','package_price','purchase_limit','status'])->toArray(),
    'offers'=>Illuminate\Support\Facades\DB::table('customer_credit_offers')->get(['id','name','price','allowance','active'])->toArray(),
],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
