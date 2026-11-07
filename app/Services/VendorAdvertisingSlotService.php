<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Models\VendorAdvertisingSlot;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class VendorAdvertisingSlotService
{
    public function restorePrevious(SellerPackageSubscription $subscription): void
    {
        if (!in_array($subscription->status, ['active', 'scheduled'], true)) return;
        $previous = SellerPackageSubscription::where('seller_id', $subscription->seller_id)->where('id', '!=', $subscription->id)
            ->where('payment_status', 'paid')->where('expires_at', '<=', $subscription->starts_at)->orderByDesc('expires_at')->first();
        if (! $previous) return;
        $products = VendorAdvertisingSlot::where('seller_package_subscription_id', $previous->id)->whereNotNull('product_id')->when(\Illuminate\Support\Facades\Schema::hasColumn('vendor_advertising_slots', 'extra_ad_purchase_id'), fn ($q) => $q->whereNull('extra_ad_purchase_id'))->orderBy('position')->pluck('product_id');
        $slots = $this->provision($subscription);
        foreach ($slots as $slot) {
            if ($slot->product_id === null) continue;
            DB::table('vendor_advertising_slot_history')->insert(['slot_id' => $slot->id, 'seller_id' => $subscription->seller_id, 'previous_product_id' => $slot->product_id, 'product_id' => null, 'created_at' => now()]);
            $slot->update(['product_id' => null]);
        }
        if ($products->count() > $subscription->product_limit) {
            $metadata = $subscription->metadata ?: [];
            $metadata['slot_selection_required'] = true;
            $subscription->update(['metadata' => $metadata, 'used_product_limit' => 0]);
            return;
        }
        $metadata = $subscription->metadata ?: [];
        $metadata['slot_selection_required'] = false;
        $subscription->update(['metadata' => $metadata]);
        foreach ($products as $index => $productId) {
            if (! Product::withoutGlobalScopes()->whereKey($productId)->where('user_id', $subscription->seller_id)->where('added_by', 'seller')->whereIn('request_status', [0, 1])->exists()) continue;
            $slots[$index]->update(['product_id' => $productId]);
            DB::table('vendor_advertising_slot_history')->insert(['slot_id' => $slots[$index]->id, 'seller_id' => $subscription->seller_id, 'previous_product_id' => null, 'product_id' => $productId, 'created_at' => now()]);
        }
        $subscription->update(['used_product_limit' => VendorAdvertisingSlot::where('seller_package_subscription_id', $subscription->id)->whereNotNull('product_id')->count()]);
    }

    /** Keep queued paid cycles ready at the exact boundary, without waiting for cron. */
    public function refreshScheduled(int $sellerId): void
    {
        DB::transaction(function () use ($sellerId) {
        Seller::whereKey($sellerId)->lockForUpdate()->firstOrFail();
        foreach (SellerPackageSubscription::where('seller_id', $sellerId)->where('payment_status', 'paid')->where('status', 'scheduled')
            ->where('starts_at', '>', now())->orderBy('starts_at')->lockForUpdate()->get() as $future) {
            $this->restorePrevious($future);
        }
        });
    }

    /** Idempotent advancement; never debits a wallet or buys another period. */
    public function synchronize(Seller $seller): void
    {
        DB::transaction(function () use ($seller) {
            Seller::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            SellerPackageSubscription::where('seller_id', $seller->id)->where('payment_status', 'paid')
                ->whereIn('status', ['active', 'scheduled'])->where('expires_at', '<=', now())->update(['status' => 'expired']);
            $next = SellerPackageSubscription::where('seller_id', $seller->id)->where('payment_status', 'paid')
                ->where('status', 'scheduled')->where('starts_at', '<=', now())->where('expires_at', '>', now())
                ->orderBy('starts_at')->lockForUpdate()->first();
            if (!$next) return;
            $next->update(['status' => 'active']);
            $this->restorePrevious($next);

        });
    }

    /** Called in the payment transaction: repeated callbacks never duplicate slots. */
    public function provision(SellerPackageSubscription $subscription): Collection
    {
        return DB::transaction(function () use ($subscription) {
            $subscription = SellerPackageSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if ($subscription->payment_status !== 'paid' || ! $subscription->starts_at || ! $subscription->expires_at
                || $subscription->product_limit < 1) {
                throw new DomainException('vendor_advertising_paid_cycle_required');
            }
            for ($position = 1; $position <= $subscription->product_limit; $position++) {
                VendorAdvertisingSlot::firstOrCreate([
                    'seller_package_subscription_id' => $subscription->id,
                    'position' => $position,
                ], [
                    'seller_id' => $subscription->seller_id,
                    'starts_at' => $subscription->starts_at,
                    'expires_at' => $subscription->expires_at,
                ]);
            }

            return VendorAdvertisingSlot::where('seller_package_subscription_id', $subscription->id)->orderBy('position')->get();
        });
    }

    /** Locks the seller first, matching the financial purchase lock order. */
    public function assign(Seller $seller, int $slotId, ?int $productId, bool $consumeReservation = false): VendorAdvertisingSlot
    {
        $slot = DB::transaction(function () use ($seller, $slotId, $productId, $consumeReservation) {
            Seller::query()->whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $slot = VendorAdvertisingSlot::where('seller_id', $seller->id)->whereKey($slotId)->lockForUpdate()->firstOrFail();
            $subscription = SellerPackageSubscription::whereKey($slot->seller_package_subscription_id)->lockForUpdate()->firstOrFail();
            if ($subscription->payment_status !== 'paid' || $subscription->status !== SellerPackageSubscription::STATUS_ACTIVE
                || ! app(VendorAdvertisingCyclePolicy::class)->canPublish(now(), $slot->starts_at, $slot->expires_at)) {
                throw new DomainException('vendor_advertising_active_subscription_required');
            }
            if (!$consumeReservation && app(VendorSlotReservationService::class)->isReserved($slot)) {
                throw new DomainException('المساحة محجوزة في نموذج إضافة. ألغِ الحجز أولًا.');
            }
            if ($productId !== null) {
                Product::withoutGlobalScopes()->where('added_by', 'seller')->where('user_id', $seller->id)->whereKey($productId)->firstOrFail();
                if (VendorAdvertisingSlot::where('seller_package_subscription_id', $subscription->id)
                    ->where('product_id', $productId)->where('id', '!=', $slot->id)->exists()) {
                    throw new DomainException('vendor_advertising_product_already_assigned');
                }
            }
            if ($slot->product_id === $productId) {
                return $slot;
            }
            DB::table('vendor_advertising_slot_history')->insert([
                'slot_id' => $slot->id, 'seller_id' => $seller->id,
                'previous_product_id' => $slot->product_id, 'product_id' => $productId, 'created_at' => now(),
            ]);
            if ($slot->product_id !== null) $this->stopProduct((int) $slot->seller_id, (int) $slot->product_id);
            // Replacement and removal never alter the paid time window.
            $changes = ['product_id' => $productId];
            if (config('vendor_advertising.creation_slots_enabled')) $changes += app(VendorSlotReservationService::class)->emptyReservation() + ['assignment_revision' => (int) $slot->assignment_revision + 1];
            $slot->forceFill($changes)->save();
            if ($productId !== null && config('vendor_product_workspace.enabled')) {
                $workspace = \App\Models\VendorProductWorkspace::where('product_id', $productId)->where('seller_id', $seller->id)->first();
                if ($workspace) app(VendorProductWorkspaceService::class)->recordSubmission($workspace, (int) $slot->id);
            }
            $subscription->update(['used_product_limit' => VendorAdvertisingSlot::where('seller_package_subscription_id', $subscription->id)->whereNotNull('product_id')->count()]);
            $this->refreshScheduled((int) $seller->id);

            return $slot->fresh();
        });
        DB::afterCommit(fn () => cacheRemoveByType(type: 'products'));

        return $slot;
    }
    public function releaseRejected(Product $product): void
    {
        foreach (VendorAdvertisingSlot::where('seller_id', $product->user_id)->where('product_id', $product->id)
            ->where('expires_at', '>', now())->lockForUpdate()->get() as $slot) {
            DB::table('vendor_advertising_slot_history')->insert(['slot_id' => $slot->id, 'seller_id' => $product->user_id,
                'previous_product_id' => $product->id, 'product_id' => null, 'created_at' => now()]);
            $slot->forceFill(['product_id' => null] + (config('vendor_advertising.creation_slots_enabled') ? ['assignment_revision' => (int) $slot->assignment_revision + 1] : []))->save();
            $slot->subscription()->update(['used_product_limit' => VendorAdvertisingSlot::where('seller_package_subscription_id', $slot->seller_package_subscription_id)->whereNotNull('product_id')->count()]);
        }
        $this->stopProduct((int) $product->user_id, (int) $product->id);
        DB::afterCommit(fn () => cacheRemoveByType(type: 'products'));
    }

    private function stopProduct(int $sellerId, int $productId): void
    {
        DB::table('products')->where('added_by', 'seller')->where('user_id', $sellerId)->where('id', $productId)
            ->update(['status' => 0]);
        if (config('vendor_product_workspace.enabled')) {
            \App\Models\VendorProductWorkspace::where('seller_id', $sellerId)->where('product_id', $productId)
                ->update(['publication_suspended' => true]);
        }
    }

}
