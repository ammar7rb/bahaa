<?php

namespace App\Services;

use App\Models\CustomerMonthlySubscription;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerPackageAutoRenewalService
{
    public function run(): void
    {
        User::query()->where('package_auto_renew', true)->whereNull('package_auto_renew_failed_at')
            ->where('is_active', true)->whereNull('account_deleted_at')
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('customer_monthly_subscriptions as subscription')
                    ->whereColumn('subscription.customer_id', 'users.id')
                    ->whereNotNull('subscription.package_id')->where('subscription.expires_at', '<=', now())
                    ->whereNotExists(fn ($newer) => $newer->selectRaw('1')->from('customer_monthly_subscriptions as newer')
                        ->whereColumn('newer.customer_id', 'subscription.customer_id')
                        ->whereColumn('newer.id', '>', 'subscription.id'));
            })->select('id')->chunkById(100, function ($customers) {
                foreach ($customers as $customer) {
                    $this->renew($customer->id);
                }
            });
    }

    private function renew(int $id): void
    {
        DB::transaction(function () use ($id) {
            $customer = User::lockForUpdate()->find($id);
            if (!$customer?->package_auto_renew || $customer->package_auto_renew_failed_at || !$customer->is_active || $customer->account_deleted_at) {
                return;
            }

            $subscription = CustomerMonthlySubscription::where('customer_id', $id)->latest('id')->first();
            if (!$subscription?->package_id || $subscription->expires_at->isFuture()) {
                return;
            }

            try {
                $catalog = app(CustomerPackageCatalogService::class);
                $package = $catalog->available()->find($subscription->package_id);
                if (!$package || $package->package_price <= 0) {
                    throw new DomainException('الباقة الحالية غير متاحة للتجديد.');
                }
                app(CustomerPackageWalletService::class)->transact($id, [
                    'request_key' => (string) Str::uuid(),
                    'operation' => 'purchase',
                    'package_id' => $package->id,
                    'expected_revision' => 0,
                    'quote_token' => $catalog->token($package, 'package'),
                    'expected_amount' => $package->package_price,
                ]);
            } catch (DomainException $error) {
                $customer->package_auto_renew_failed_at = now();
                $customer->save();
                app(CustomerPaymentNotificationService::class)->notify($id, 'package-renew-failed-'.$subscription->id,
                    'تعذر التجديد التلقائي. '.$error->getMessage().' راجع صفحة الباقات للتجديد يدويًا.');
            }
        }, 3);
    }
}
