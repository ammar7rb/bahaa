<?php

namespace App\Services;

use App\Models\OfflinePayments;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerOrderPaymentRetryService
{
    public function submit(int $customerId, int $orderId, string $key, string $reference, string $proof, array $sender = []): bool
    {
        return DB::transaction(function () use ($customerId, $orderId, $key, $reference, $proof, $sender) {
            User::lockForUpdate()->findOrFail($customerId);
            $order = Order::where('customer_id', $customerId)->where('is_guest', 0)->lockForUpdate()->findOrFail($orderId);
            $payment = OfflinePayments::where('order_id', $orderId)->lockForUpdate()->firstOrFail();
            $info = $payment->payment_info ?: [];
            if (($info['retry_key'] ?? null) === $key) return false;
            if (!$order->customer_checkout_operation_id || $order->payment_status === 'paid'
                || $order->payment_method !== 'offline_payment' || $order->activation_status !== 'offline_payment_rejected'
                || in_array($order->order_status, ['canceled', 'failed', 'returned', 'delivered'], true)) {
                throw ValidationException::withMessages(['payment' => 'لا يمكن إعادة تقديم إثبات الدفع لهذا الطلب حاليًا.']);
            }
            // Preserve every rejected proof and reference for the financial audit.
            $history = $info['retry_history'] ?? [];
            $previous = $info;
            unset($previous['retry_history']);
            $history[] = ['information' => $previous, 'replaced_at' => now()->toIso8601String()];
            $info['retry_history'] = $history;
            $info['retry_key'] = $key;
            foreach ($sender as $name => $value) $info[$name] = $value;
            $info['transferred_amount'] = (string) $order->order_amount;
            $info['payment_reference'] = $reference;
            $info['private_retry_proof'] = $proof;
            $payment->update(['payment_info' => $info]);
            $order->update(['activation_status' => 'offline_payment_pending_review']);
            app(CustomerPurchaseEligibilityService::class)->reevaluateCustomer($customerId);
            return true;
        }, 3);
    }
}
