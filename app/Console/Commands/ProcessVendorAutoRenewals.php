<?php

namespace App\Console\Commands;

use App\Services\VendorAutoRenewalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProcessVendorAutoRenewals extends Command
{
    protected $signature = 'vendor:process-auto-renewals';
    protected $description = 'Process due vendor subscription renewals';

    public function handle(VendorAutoRenewalService $service): int
    {
        if (!config('vendor_advertising.auto_renewal_enabled') || !Schema::hasTable('vendor_auto_renewals')) return self::SUCCESS;
        DB::table('vendor_auto_renewals')->where('enabled', true)->orderBy('id')->chunkById(100, function ($rows) use ($service) {
            foreach ($rows as $row) {
                try { $service->process((int) $row->seller_id); }
                catch (\Throwable $error) { Log::error('Vendor automatic renewal failed', ['seller_id' => $row->seller_id, 'exception' => $error]); }
            }
        });
        return self::SUCCESS;
    }
}
