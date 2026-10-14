<?php

namespace App\Services;

use App\Models\CustomerMonthlySubscription;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CustomerMonthlyAllowanceService
{
    public function enabled(): bool { return Schema::hasColumn('customer_monthly_subscriptions', 'package_id'); }

    public function active(int $customerId): ?CustomerMonthlySubscription
    {
        return CustomerMonthlySubscription::where('customer_id', $customerId)->whereNotNull('package_id')
            ->where('starts_at', '<=', now())->where('expires_at', '>', now())->latest('id')->first();
    }

    public function summary(int $customerId, float $cartAmount = 0, bool $provisional = false): array
    {
        $sub = $customerId ? $this->active($customerId) : null;
        // An initial prepaid checkout has no credit allowance until a package is selected.
        $unlimited = !$sub && $provisional;
        $granted = $sub ? (float) $sub->granted_allowance + (float) $sub->additional_allowance : 0.0;
        $committed = $sub ? (float) $sub->committed_allowance : 0.0;
        $available = max(0, round($granted - $committed, 3));
        $cartReservation = 0.0;
        $openReservation = 0.0;
        if ($customerId && Schema::hasColumn('customer_checkout_operations','reserved_limit_amount')) {
            $open = DB::table('customer_checkout_operations')->where('customer_id',$customerId)->whereNull('consumed_at')->whereNotNull('monthly_subscription_id')->first();
            $openReservation = $open ? (float)$open->reserved_limit_amount : 0.0;
            if ($open && round((float)$open->reserved_limit_amount,3) === round($cartAmount,3)) $cartReservation = (float)$open->reserved_limit_amount;
        }
        $projected = round($available - max(0, $cartAmount - $cartReservation), 3);
        $ratio = $granted > 0 ? $projected / $granted : 0;
        return ['has_active_package' => (bool) $sub, 'provisional' => $unlimited,
            'unlimited_initial_checkout' => $unlimited, 'show_credit_indicator' => !$unlimited,
            'package_name' => $sub?->package_name, 'subscription_id' => $sub?->id,
            'base_limit' => $sub ? (float) $sub->granted_allowance : 0.0,
            'package_price' => $sub ? (float) $sub->package_price : null,
            'granted_limit' => $granted, 'committed_limit' => $committed, 'available_limit' => $available,
            'cart_amount' => $cartAmount, 'projected_remaining' => $projected,
            'cart_reserved_amount' => $cartReservation,
            'open_checkout_reserved_amount' => $openReservation,
            'can_checkout' => $unlimited || ($sub && $projected >= 0),
            'indicator' => $unlimited ? null : ($ratio > .5 ? 'green' : ($ratio > .1 ? 'orange' : 'red'))];
    }

    public function assertCart(int $customerId, float $amount, bool $provisional = false): array
    {
        $summary = $this->summary($customerId, $amount, $provisional);
        if (!$summary['can_checkout']) throw ValidationException::withMessages(['purchasing_limit' =>
            'الحد الائتماني المتاح '.number_format($summary['available_limit'], 2).' ج.م لا يغطي سلة المشتريات. زد الحد أو اختر باقة أعلى أو قلل المنتجات.']);
        return $summary;
    }

    /** Lock customer before calling; order reservation is a ledger entry, never wallet money. */
    public function reserveOrder(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            \App\Models\User::lockForUpdate()->findOrFail($order->customer_id);
            $existing = DB::table('customer_monthly_limit_entries')->where('order_id', $order->id)->first();
            if ($existing) return true;
            $operation = $order->customer_checkout_operation_id ? DB::table('customer_checkout_operations')->where('id',$order->customer_checkout_operation_id)->lockForUpdate()->first() : null;
            $preReserved = $operation && !empty($operation->monthly_subscription_id);
            $sub = $preReserved ? CustomerMonthlySubscription::find($operation->monthly_subscription_id) : $this->active((int) $order->customer_id);
            if (!$sub) return false;
            $sub = CustomerMonthlySubscription::lockForUpdate()->findOrFail($sub->id);
            $details = DB::table('order_details')->where('order_id',$order->id)->get(['price','qty','discount']);
            $amount = round((float) $details->sum(fn ($d) => max(0, (float) $d->price * (int) $d->qty - (float) $d->discount)), 3);
            $available = $preReserved ? (float)$operation->reserved_limit_amount - (float)$operation->converted_limit_amount : (float) $sub->granted_allowance + (float) $sub->additional_allowance - (float) $sub->committed_allowance;
            if ($amount > round($available, 3)) return false;
            DB::table('customer_monthly_limit_entries')->insert(['customer_id' => $order->customer_id,
                'subscription_id' => $sub->id, 'order_id' => $order->id, 'amount' => $amount, 'created_at' => now(), 'updated_at' => now()]);
            if ($preReserved) DB::table('customer_checkout_operations')->where('id',$operation->id)->increment('converted_limit_amount',$amount);
            else $sub->increment('committed_allowance', $amount);
            return true;
        }, 3);
    }

    public function restoreOrder(Order $order, ?float $requested = null, string $reference = 'cancel'): array
    {
        return DB::transaction(function () use ($order, $requested, $reference) {
            \App\Models\User::lockForUpdate()->findOrFail($order->customer_id);
            if (DB::table('customer_monthly_limit_restorations')->where('order_id',$order->id)->where('reference',$reference)->exists()) return ['status' => 1, 'message' => 'already_restored'];
            $entry = DB::table('customer_monthly_limit_entries')->where('order_id', $order->id)->lockForUpdate()->first();
            if (!$entry) return ['status' => 0, 'message' => 'order_limit_debit_not_found'];
            $sub = CustomerMonthlySubscription::lockForUpdate()->findOrFail($entry->subscription_id);
            $amount = min(max(0, $requested ?? (float) $entry->amount), max(0, (float) $entry->amount - (float) $entry->restored_amount));
            DB::table('customer_monthly_limit_entries')->where('id', $entry->id)->update(['restored_amount' => (float) $entry->restored_amount + $amount, 'updated_at' => now()]);
            $sub->update(['committed_allowance' => max(0, (float) $sub->committed_allowance - $amount)]);
            DB::table('customer_monthly_limit_restorations')->insert(['order_id' => $order->id, 'reference' => $reference, 'amount' => $amount, 'created_at' => now(), 'updated_at' => now()]);
            return ['status' => 1, 'message' => 'restored'];
        }, 3);
    }
}
