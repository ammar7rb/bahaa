<?php

namespace App\Console\Commands;

use App\Services\OrderCancellationSettlementService;
use Illuminate\Console\Command;

class ExpireOrderCancellationCredits extends Command
{
    protected $signature = 'orders:expire-cancellation-credits';
    protected $description = 'Expire the unspent amount of customer cancellation credits';

    public function handle(OrderCancellationSettlementService $service): int
    {
        $count = $service->expireCredits();
        $this->info("Processed {$count} cancellation credits.");
        return self::SUCCESS;
    }
}
