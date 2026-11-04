<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderAdminGateDecision;
use App\Models\SellerPackageSubscription;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Release policy; never used to suspend fulfillment of received orders. */
class SellerOrderSubscriptionEligibilityService
{
    public function assertCanRelease(Order $order): void
    {
        if ($this->reason($order) === 'subscription_required') {
            throw new DomainException('seller_subscription_required_for_new_orders');
        }
    }

    public function reason(Order $order): string
    {
        // Subscription expiry freezes advertisements only; received/persisted orders remain actionable.
        if (config('vendor_product_workspace.enabled')) return 'advertising_only_restriction';
        // Phase 4/6 must supply the catalog, slots and payment journey before
        // rollout. This switch does not re-enable vendor security deposits.
        if (! config('order_commerce.features.vendor_subscription_order_gate', false)) {
            return 'rollout_not_enabled';
        }
        if ($order->seller_is !== 'seller') return 'in_house_order';
        if (config('vendor_advertising.enabled')) {
            $rollout = app(VendorAdvertisingAccessService::class)->rollout();
            if ($rollout && $order->seller_id <= $rollout->seller_cutoff_id && $order->created_at
                && $order->created_at->lt(Carbon::parse($rollout->grace_ends_at))) return 'legacy_transition_order';
        }
        if (OrderAdminGateDecision::where('order_id', $order->id)->where('action', 'released_to_seller')->exists()) {
            return 'already_received';
        }

        $subscriptions = SellerPackageSubscription::where('seller_id', $order->seller_id)
            ->where('payment_status', 'paid')
            ->whereIn('status', ['active', 'scheduled', 'expired', 'replaced'])
            ->get(['starts_at', 'started_at', 'expires_at']);
        $paidAt = $this->recordedPaymentTimes($order);
        foreach ($subscriptions as $subscription) {
            $start = $subscription->starts_at ?? $subscription->started_at;
            $end = $subscription->expires_at;
            if (! $start || ! $end) continue;
            if ($start->lte(now()) && $end->gt(now())) return 'active_subscription';
            // A customer order created in the paid period can still be released
            // afterwards. Merely having an expired subscription is not enough.
            if ($order->created_at && $start->lte($order->created_at) && $end->gt($order->created_at)) {
                return 'created_during_paid_subscription';
            }
            foreach ($paidAt as $time) {
                if ($start->lte($time) && $end->gt($time)) return 'paid_during_paid_subscription';
            }
        }
        return 'subscription_required';
    }

    /** Verified persisted payment times; never infer payment time from updated_at. */
    private function recordedPaymentTimes(Order $order): array
    {
        if ($order->payment_status !== 'paid') return [];
        $times = [];
        foreach (['customer_activation_invoices' => 'payment_status', 'post_purchase_invoices' => 'status'] as $table => $statusColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'paid_at')) continue;
            foreach (DB::table($table)->where('order_id', $order->id)->where($statusColumn, 'paid')->whereNotNull('paid_at')->pluck('paid_at') as $paidAt) {
                $times[] = Carbon::parse($paidAt);
            }
        }
        return $times;
    }
}
