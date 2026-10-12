<?php

namespace App\Providers;

use App\Models\Product;
use Illuminate\Support\ServiceProvider;

class ObserverServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Product::observe([]);
        foreach ([\App\Models\User::class, \App\User::class] as $customerModel) {
            $customerModel::updated(function ($customer) {
                if ($customer->wasChanged(['activation_status', 'is_active'])) {
                    app(\App\Services\CustomerPurchaseEligibilityService::class)->reevaluateCustomer((int) $customer->id);
                }
            });
        }
        \App\Models\Order::updated(function ($order) {
            if ($order->customer_checkout_operation_id && $order->wasChanged(['payment_status', 'is_pause'])) {
                \Illuminate\Support\Facades\DB::afterCommit(function () use ($order) {
                    if ($order->payment_status === 'paid') app(\App\Services\PostPurchaseInvoiceService::class)->createForPaidOrderGroup([(int) $order->id]);
                    app(\App\Services\CustomerPurchaseEligibilityService::class)->reevaluateCustomer((int) $order->customer_id);
                });
            }
        });
    }
}
