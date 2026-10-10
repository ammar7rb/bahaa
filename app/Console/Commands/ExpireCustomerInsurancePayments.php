<?php

namespace App\Console\Commands;

use App\Services\CustomerPostPurchaseInsuranceService;
use Illuminate\Console\Command;

class ExpireCustomerInsurancePayments extends Command
{
    protected $signature = 'orders:expire-customer-insurance-payments {--limit=100}';
    protected $description = 'Expire unpaid post-purchase insurance claims and schedule purchase refunds';

    public function handle(CustomerPostPurchaseInsuranceService $service): int
    {
        $this->info('Customer security deposits retired; no insurance expiry cancellations.');
        return self::SUCCESS;
    }
}
