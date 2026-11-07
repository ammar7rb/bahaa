<?php

// Read-only. Refuse activation if a restored historical migration would rerun.
$root = realpath(getenv('SIGMA_APP_ROOT') ?: __DIR__.'/..');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$required = [
    '2026_10_15_000001_create_vendor_extra_ad_purchases',
    '2026_10_15_000002_create_vendor_funding_intents',
    '2026_10_16_000001_create_activation_conversation_reads',
    '2026_10_17_220000_remove_vendor_withdrawal_destination_approval',
    '2026_10_18_030000_add_vendor_slot_reservations',
    '2026_10_23_030000_create_order_financial_events',
    '2026_10_25_000001_create_company_medical_devices',
];
$missing = [];
foreach ($required as $migration) {
    $batch = Illuminate\Support\Facades\DB::table('migrations')->where('migration', $migration)->value('batch');
    echo $migration.': '.($batch === null ? 'NOT RECORDED — STOP' : 'Ran [batch '.$batch.']').PHP_EOL;
    if ($batch === null) $missing[] = $migration;
}
if ($missing) {
    fwrite(STDERR, 'Historical migrations require a separate data/schema review and approval. Do not run migrate, fresh, refresh or rollback.'.PHP_EOL);
    exit(1);
}
echo 'Historical migration preflight passed; no database changes performed.'.PHP_EOL;
