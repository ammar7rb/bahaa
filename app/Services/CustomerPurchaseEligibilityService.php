<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Customer eligibility is independent of vendor assignment and security deposits. */
class CustomerPurchaseEligibilityService
{
    public function enabled(): bool
    {
        return Schema::hasTable('customer_checkout_operations');
    }

    public function status(object $customer): array
    {
        $subscription = app(CustomerMonthlySubscriptionService::class)->status((int) $customer->id);
        $activated = $customer->activation_status === 'active'
            || (!$customer->activation_status && (bool) $customer->is_active);
        $first = $customer->onboarding_journey === 'checkout_first'
            && !$customer->first_checkout_operation_id
            && !Order::where('customer_id', $customer->id)->where('is_guest', 0)->exists();
        $resume = $customer->first_checkout_operation_id && $this->enabled()
            && DB::table('customer_checkout_operations')->where('id', $customer->first_checkout_operation_id)->whereNull('consumed_at')->exists();
        $reasons = [];
        if (!$customer->is_active) $reasons[] = 'administrative_freeze';
        if (!$activated) $reasons[] = 'account_activation';
        if (!$subscription['active']) $reasons[] = 'monthly_subscription';
        return [
            'onboarding_journey' => $customer->onboarding_journey ?: 'legacy',
            'account_activated' => $activated,
            'administratively_frozen' => !(bool) $customer->is_active,
            'subscription' => $subscription,
            'initial_order_exception_available' => $first,
            'can_resume_initial_checkout' => (bool) $resume,
            'can_checkout' => (bool) $customer->is_active && (!$reasons || $first || $resume),
            'pending_reasons' => $reasons,
            'message' => $this->message($reasons),
        ];
    }

    public function reserve(int $customerId, string $fingerprint, ?string $operationId = null, string $method = '', ?array $cartSnapshot = null): object
    {
        return DB::transaction(function () use ($customerId, $fingerprint, $operationId, $method, $cartSnapshot) {
            $customer = User::lockForUpdate()->findOrFail($customerId);
            $state = $this->status($customer);
            if ($operationId) {
                $operation = DB::table('customer_checkout_operations')->where('id', $operationId)
                    ->where('customer_id', $customerId)->lockForUpdate()->first();
                if (!$operation) $this->deny(['invalid_checkout_operation']);
                // A confirmed gateway callback may arrive after expiry; its server reservation is authoritative.
                return $operation;
            }
            if ($state['administratively_frozen']) $this->deny(['administrative_freeze']);
            $open = DB::table('customer_checkout_operations')->where('customer_id', $customerId)->whereNull('consumed_at')->lockForUpdate()->first();
            if ($open) {
                if (!hash_equals($open->cart_fingerprint, $fingerprint)) $this->deny(['pending_checkout_changed']);
                if (!$open->first_exception && $state['pending_reasons']) $this->deny($state['pending_reasons']);
                if ($open->first_exception && in_array($method, ['cash_on_delivery', 'cash'], true)) $this->deny(['first_order_prepayment']);
                return $open;
            }
            if ($customer->first_checkout_operation_id) {
                $existing = DB::table('customer_checkout_operations')->where('id', $customer->first_checkout_operation_id)->lockForUpdate()->first();
                if ($existing && !$existing->consumed_at && hash_equals($existing->cart_fingerprint, $fingerprint)) return $existing;
            }
            if ($state['pending_reasons'] && !$state['initial_order_exception_available']) $this->deny($state['pending_reasons']);
            $exception = $state['initial_order_exception_available'];
            if ($exception && in_array($method, ['cash_on_delivery', 'cash'], true)) $this->deny(['first_order_prepayment']);
            $id = (string) Str::uuid();
            $record = [
                'id' => $id, 'customer_id' => $customerId, 'cart_fingerprint' => $fingerprint,
                'first_exception' => $exception, 'qualified_at' => $state['pending_reasons'] ? null : now(),
                'created_at' => now(), 'updated_at' => now(),
            ];
            if (Schema::hasColumn('customer_checkout_operations', 'cart_snapshot')) $record['cart_snapshot'] = $cartSnapshot === null ? null : json_encode($cartSnapshot);
            DB::table('customer_checkout_operations')->insert($record);
            if ($state['initial_order_exception_available']) {
                DB::table('users')->where('id', $customerId)->update(['first_checkout_operation_id' => $id]);
            }
            return DB::table('customer_checkout_operations')->where('id', $id)->first();
        }, 3);
    }

    public function reevaluateCustomer(int $customerId): void
    {
        if (!$this->enabled()) return;
        DB::transaction(function () use ($customerId) {
            $customer = User::lockForUpdate()->find($customerId);
            if (!$customer) return;
            foreach (Order::where('customer_id', $customerId)->where('is_guest', 0)
                ->whereNotNull('customer_checkout_operation_id')->whereNull('customer_eligible_at')->lockForUpdate()->get() as $order) {
                $this->evaluateLocked($order, $customer);
            }
        }, 3);
    }

    public function orderPayload(Order $order): array
    {
        if (!$this->enabled()) return ['eligible_at' => null, 'fulfillment_status' => null, 'pending_reasons' => [], 'message' => '', 'customer' => null];
        $customer = User::find($order->customer_id);
        $state = $customer ? $this->status($customer) : null;
        $reasons = json_decode((string) $order->getRawOriginal('customer_pending_reasons'), true) ?: [];
        $statusLabel = ['pending'=>'قيد الانتظار','confirmed'=>'تم التأكيد','processing'=>'قيد التجهيز','out_for_delivery'=>'في الطريق للتسليم','delivered'=>'تم التسليم','returned'=>'تم الإرجاع','failed'=>'تعذر التسليم','canceled'=>'ملغي'][$order->order_status] ?? $order->order_status;
        return ['eligible_at' => $order->customer_eligible_at, 'fulfillment_status' => $order->customer_fulfillment_status,
            'payment_review_status' => $order->payment_status === 'paid' ? 'approved' : ($order->activation_status === 'offline_payment_rejected' ? 'rejected' : (in_array($order->payment_method,['cash_on_delivery','cash'],true) ? 'awaiting_collection' : 'pending')),
            'pending_reasons' => $reasons, 'message' => (!$order->customer_checkout_operation_id || !in_array($order->order_status,['pending','confirmed','processing'],true)) ? $statusLabel : $this->message($reasons), 'customer' => $state];
    }

    public function approveOrderPayment(int $orderId, string $reference): void
    {
        $customerId = (int) Order::findOrFail($orderId)->customer_id;
        DB::transaction(function () use ($orderId, $reference, $customerId) {
            User::lockForUpdate()->findOrFail($customerId);
            $order = Order::lockForUpdate()->findOrFail($orderId);
            if ($order->payment_status === 'paid') { $this->reevaluateCustomer($customerId); return; }
            if (in_array($order->order_status, ['canceled', 'failed', 'returned'], true)) $this->deny(['order_canceled']);
            $updates = ['payment_status' => 'paid', 'transaction_ref' => $reference];
            if ($order->activation_status === 'offline_payment_rejected') $updates['activation_status'] = null;
            $order->update($updates);
            app(PostPurchaseInvoiceService::class)->createForPaidOrderGroup([$orderId]);
            $this->reevaluateCustomer($customerId);
        }, 3);
    }

    public function rejectOrderPayment(int $orderId, string $note): void
    {
        $customerId = (int) Order::findOrFail($orderId)->customer_id;
        DB::transaction(function () use ($customerId, $orderId, $note) {
            User::lockForUpdate()->findOrFail($customerId);
            $order = Order::lockForUpdate()->findOrFail($orderId);
            if ($order->payment_status === 'paid' || $order->activation_status === 'offline_payment_rejected') return;
            if (!$order->customer_checkout_operation_id || $order->payment_method !== 'offline_payment') $this->deny(['invalid_checkout_operation']);
            $order->update(['activation_status' => 'offline_payment_rejected',
                'order_note' => trim(($order->order_note ? $order->order_note."\n" : '').'Offline payment rejected: '.$note)]);
            $this->reevaluateCustomer($customerId);
        }, 3);
    }

    public function assertCanFulfill(Order $order): void
    {
        if (!$order->customer_checkout_operation_id) return;
        $this->reevaluateCustomer((int) $order->customer_id);
        $order->refresh();
        $customer = User::find($order->customer_id);
        if (!$order->customer_eligible_at || !$customer?->is_active || $order->is_pause || $order->commerce_flow_status === 'under_investigation') {
            $this->deny($order->customer_eligible_at ? ['administrative_freeze'] : (json_decode((string) $order->getRawOriginal('customer_pending_reasons'), true) ?: ['order_payment_review']));
        }
    }

    private function evaluateLocked(Order $order, User $customer): void
    {
        if (in_array($order->order_status, ['canceled', 'failed', 'returned', 'delivered'], true)) return;
        $state = $this->status($customer);
        $operation = DB::table('customer_checkout_operations')->where('id', $order->customer_checkout_operation_id)->first();
        $reasons = [];
        $cashOnDelivery = $operation?->qualified_at && !$operation?->first_exception
            && in_array($order->payment_method, ['cash_on_delivery','cash'], true);
        if ($order->payment_status !== 'paid' && !$cashOnDelivery) $reasons[] = $order->activation_status === 'offline_payment_rejected' ? 'order_payment_rejected' : 'order_payment_review';
        if ($state['administratively_frozen'] || $order->is_pause || $order->commerce_flow_status === 'under_investigation') $reasons[] = 'administrative_freeze';
        if (!$state['account_activated']) $reasons[] = 'account_activation';
        if (!$state['subscription']['active'] && !$operation?->qualified_at) $reasons[] = 'monthly_subscription';
        if (Schema::hasTable('post_purchase_invoices') && DB::table('post_purchase_invoices')->where('order_id', $order->id)
            ->whereNotIn('status', ['paid', 'cancelled'])->whereColumn('total_amount', '>', 'paid_amount')->exists()) $reasons[] = 'outstanding_invoice';
        $updates = ['customer_pending_reasons' => json_encode($reasons), 'customer_fulfillment_status' => $reasons ? 'pending_requirements' : 'preparing'];
        if (!$reasons) $updates['customer_eligible_at'] = now();
        // Keep vendor assignment, access restrictions and fulfillment routing intact.
        DB::table('orders')->where('id', $order->id)->whereNull('customer_eligible_at')->update($updates);
    }

    public function message(array $reasons): string
    {
        $labels = ['administrative_freeze' => 'الحساب أو الطلب موقوف إداريًا. تواصل مع الدعم',
            'account_activation' => 'تفعيل الحساب', 'monthly_subscription' => 'دفع الاشتراك الشهري',
            'order_payment_rejected' => 'إعادة تقديم إثبات الدفع بعد رفضه', 'order_payment_review' => 'الدفع قيد التحقق', 'first_order_prepayment' => 'يلزم دفع قيمة الطلب الأول بالكامل',
            'invalid_checkout_operation' => 'تعذر العثور على عملية الشراء', 'order_canceled' => 'الطلب ملغي',
            'outstanding_invoice' => 'سداد الفاتورة المستحقة', 'pending_checkout_changed' => 'إتمام عملية الدفع المحفوظة قبل تغيير السلة. تواصل مع الدعم عند الحاجة'];
        if (!$reasons) return 'قيد التجهيز';
        if ($reasons === ['order_payment_review']) return $labels['order_payment_review'];
        return 'الطلب بانتظار '.implode(' و', array_map(fn ($reason) => $labels[$reason] ?? $reason, $reasons));
    }

    private function deny(array $reasons): never
    {
        throw ValidationException::withMessages(['customer_eligibility' => $this->message($reasons)]);
    }
}
