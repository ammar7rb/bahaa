<?php
$root=dirname(__DIR__,2);$database=$argv[1]??'';$payload=$argv[2]??'';
$allowed=realpath($root.'/storage/framework/testing');
if(!$allowed||!is_file($database)||realpath(dirname($database))!==$allowed||!is_file($payload)||realpath(dirname($payload))!==$allowed)exit(2);
foreach(['APP_ENV'=>'testing','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$database,'CACHE_DRIVER'=>'array','SESSION_DRIVER'=>'array'] as $key=>$value){putenv($key.'='.$value);$_ENV[$key]=$_SERVER[$key]=$value;}
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('database.default')!=='sqlite'||config('database.connections.sqlite.database')!==$database)exit(3);
config(['vendor_order_eligibility.enabled'=>true,'vendor_advertising.enabled'=>true,'vendor_product_workspace.enabled'=>false]);
Illuminate\Support\Facades\DB::connection()->getPdo()->exec('PRAGMA busy_timeout = 5000');
try{
 $data=json_decode(file_get_contents($payload),true,512,JSON_THROW_ON_ERROR);
 if($data['action']==='unlock')$row=app(App\Services\VendorFulfillmentUnlockService::class)->unlock(App\Models\Seller::findOrFail(7),$data['id'],$data['token']);
 elseif($data['action']==='cancel')$row=app(App\Services\VendorFulfillmentDecisionService::class)->decide(App\Models\Seller::findOrFail(7),$data['id'],'cancel','concurrent cancellation');
 else throw new RuntimeException('Invalid test operation');
 echo $row->status;
}catch(Throwable $e){fwrite(STDERR,get_class($e).':'.$e->getMessage());exit(1);}
