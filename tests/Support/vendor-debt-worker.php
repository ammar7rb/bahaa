<?php
$database = $argv[1] ?? '';
$root = dirname(__DIR__, 2);
if (!is_file($database) || realpath(dirname($database)) !== realpath($root.'/storage/framework/testing')) exit(2);
foreach (['APP_ENV'=>'testing','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$database,'CACHE_DRIVER'=>'array'] as $key=>$value) {
    putenv($key.'='.$value); $_ENV[$key]=$_SERVER[$key]=$value;
}
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) exit(3);
Illuminate\Support\Facades\DB::connection()->getPdo()->exec('PRAGMA busy_timeout = 5000');
try {
    $result=app(App\Services\SellerBalanceDepositService::class)->markPaid(App\Models\SellerBalanceDeposit::findOrFail((int)$argv[2]));
    if ($result->status !== 'paid') throw new RuntimeException('Deposit was not confirmed');
    echo 'paid:'.$result->id;
} catch (Throwable $exception) { fwrite(STDERR, get_class($exception).':'.$exception->getMessage()); exit(1); }
