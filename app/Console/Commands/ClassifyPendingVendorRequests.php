<?php

namespace App\Console\Commands;

use App\Services\VendorFulfillmentEligibilityService;
use Illuminate\Console\Command;

class ClassifyPendingVendorRequests extends Command
{
    protected $signature = 'vendors:classify-pending-requests';
    protected $description = 'Classify restricted requests 24 hours after administrative forwarding without cancellation or charging.';

    public function handle(VendorFulfillmentEligibilityService $service): int
    {
        $this->info('Classified: '.$service->classifyPending());
        return self::SUCCESS;
    }
}
