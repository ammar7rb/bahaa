<?php

namespace App\Services;

use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Models\VendorAdvertisingSlot;

/** A read-only presentation contract. Never synchronizes cycles or allocates capacity. */
class VendorAccountOverviewService
{
    public function build(Seller $seller, ?array $balances = null): array
    {
        $balances ??= app(SellerLedgerService::class)->summary($seller, initializeLegacy: false);
        $cycles = SellerPackageSubscription::where('seller_id', $seller->id)->where('payment_status', 'paid');
        $cycle = (clone $cycles)->whereIn('status', ['active', 'scheduled'])
            ->where('starts_at', '<=', now())->where('expires_at', '>', now())
            ->orderByDesc('starts_at')->orderByDesc('id')->first();
        $active = $cycle !== null;
        $cycle ??= (clone $cycles)->where('starts_at', '<=', now())
            ->orderByDesc('starts_at')->orderByDesc('id')->first();
        $slots = VendorAdvertisingSlot::where('seller_id', $seller->id)
            ->where('seller_package_subscription_id', $cycle?->id ?? 0)->get();
        $available = $reserved = $expired = 0;
        foreach ($slots as $slot) {
            if (!$active || !$slot->editable) {
                $expired++;
            } elseif (!$slot->product_id) {
                if (app(VendorSlotReservationService::class)->isReserved($slot)) $reserved++;
                else $available++;
            }
        }
        return [
            'contract_version' => 'vendor_account_v1',
            'subscription' => [
                'package_name' => $cycle?->package_name,
                'status' => $active ? 'active' : ($cycle ? ($cycle->expires_at?->lte(now()) ? 'expired' : $cycle->status) : 'none'),
                'expires_at' => $cycle?->expires_at?->toISOString(),
                'active' => $active,
            ],
            'balances' => [
                'available' => (float) ($balances[SellerLedgerService::AVAILABLE] ?? 0),
                'operating' => (float) ($balances[SellerLedgerService::OPERATING] ?? 0),
            ],
            'capacity' => [
                'used' => $slots->whereNotNull('product_id')->count(), 'total' => $slots->count(),
                'available' => $available, 'reserved' => $reserved, 'expired' => $expired,
                'included' => $slots->whereNull('extra_ad_purchase_id')->count(),
                'additional' => $slots->whereNotNull('extra_ad_purchase_id')->count(),
            ],
        ];
    }
}
