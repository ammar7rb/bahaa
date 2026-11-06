<?php

$database = $argv[1] ?? '';
$root = dirname(__DIR__, 2);
$parent = realpath(dirname($database));
$allowed = realpath($root.'/storage/framework/testing');
if (!$allowed || $parent !== $allowed || !is_file($database)) exit(2);
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'CACHE_DRIVER' => 'array'] as $key => $value) {
    putenv($key.'='.$value); $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['vendor_order_eligibility.enabled' => true]);
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) exit(3);
Illuminate\Support\Facades\DB::connection()->getPdo()->exec('PRAGMA busy_timeout = 5000');
echo app(App\Services\VendorFulfillmentEligibilityService::class)->classifyPending();
