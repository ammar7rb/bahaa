<?php

namespace App\Console\Commands;

use App\Models\OrderAdminPurchaseRefund;
use App\Services\CustomerPackageCancellationService;
use Illuminate\Console\Command;

class ReleaseCustomerPackageRefunds extends Command
{
    protected $signature = 'customer-package-refunds:release';
    protected $description = 'Credit approved and due package-cancellation refunds to customer wallets';

    public function handle(CustomerPackageCancellationService $service): int
    {
        OrderAdminPurchaseRefund::where('reason', 'customer_canceled_waiting_for_package')
            ->where('status', 'wallet_approved')->where('due_at', '<=', now())
            ->chunkById(100, function ($refunds) use ($service) {
                foreach ($refunds as $refund) {
                    $service->complete($refund->id, (int) $refund->admin_id, 'purchase_wallet', null, 'Scheduled wallet refund');
                }
            });

        return self::SUCCESS;
    }
}
