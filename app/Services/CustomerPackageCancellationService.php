<?php
namespace App\Services;

use App\Models\Order;
use App\Models\OrderAdminPurchaseRefund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CustomerPackageCancellationService
{
    private const REASON = 'customer_canceled_waiting_for_package';

    public function request(int $customerId, int $orderId): OrderAdminPurchaseRefund
    {
        return DB::transaction(function () use ($customerId, $orderId) {
            User::whereKey($customerId)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($orderId)->where('customer_id', $customerId)->where('is_guest', 0)->lockForUpdate()->firstOrFail();
            $existing = OrderAdminPurchaseRefund::where('order_id', $orderId)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->reason !== self::REASON) throw ValidationException::withMessages(['order' => 'يوجد طلب استرداد آخر لهذا الطلب. تواصل مع الإدارة.']);
                return $existing;
            }
            $reasons = $order->customer_pending_reasons ?? [];
            if (!$order->customer_checkout_operation_id || $order->payment_status !== 'paid' || $order->customer_eligible_at || $order->order_status !== 'pending'
                || !in_array('monthly_subscription', $reasons, true)) {
                throw ValidationException::withMessages(['order' => 'يمكن إلغاء الطلب المدفوع المعلق لاختيار باقة فقط.']);
            }
            if (Schema::hasTable('customer_monthly_subscriptions')) {
                $activePackage = \App\Models\CustomerMonthlySubscription::where('customer_id', $customerId)
                    ->where('starts_at', '<=', now())->where('expires_at', '>', now())
                    ->when(app(CustomerMonthlyAllowanceService::class)->enabled(), fn ($query) => $query->whereNotNull('package_id'))->exists();
                if ($activePackage) throw ValidationException::withMessages(['order' => 'الباقة سارية بالفعل. حدّث حالة الطلب أو تواصل مع الدعم.']);
            }
            if (Schema::hasTable('refund_requests') && DB::table('refund_requests')->where('order_id', $orderId)->exists()) {
                throw ValidationException::withMessages(['order' => 'يوجد طلب استرداد آخر لهذا الطلب. تواصل مع الإدارة.']);
            }
            if (Schema::hasTable('order_refund_ledgers') && DB::table('order_refund_ledgers')->where('order_id', $orderId)->exists()) {
                throw ValidationException::withMessages(['order' => 'يوجد استرداد مسجل لهذا الطلب. تواصل مع الإدارة.']);
            }
            $amount = (float) ($order->init_order_amount ?: $order->order_amount);
            if ($amount <= 0) throw ValidationException::withMessages(['order' => 'تعذر تحديد المبلغ المدفوع. تواصل مع الإدارة.']);
            app(CustomerCancellationStockService::class)->restore($order);
            $refund = OrderAdminPurchaseRefund::create([
                'order_id' => $order->id, 'customer_id' => $customerId, 'amount' => $amount,
                // This status is deliberately not the auto-crediting scheduled status.
                'status' => 'customer_requested', 'due_at' => now(), 'reason' => self::REASON,
            ]);
            $order->forceFill([
                'order_status' => 'canceled', 'customer_fulfillment_status' => 'canceled',
                'commerce_flow_status' => 'purchase_refund_pending', 'commerce_flow_status_updated_at' => now(),
                'operational_blocked_by' => 'customer_cancellation', 'operational_block_reason' => self::REASON,
            ])->save();
            app(CustomerPaymentNotificationService::class)->notify($customerId, 'order-status-'.$orderId.'-package-cancel-requested',
                'تم إلغاء الطلب #'.$orderId.' وطلب رد المبلغ. ستراجع الإدارة طلب الرد وتحدد موعده عند الاعتماد.');
            return $refund;
        }, 3);
    }

    public function approve(int $refundId, int $adminId, int $days): OrderAdminPurchaseRefund
    {
        return DB::transaction(function () use ($refundId, $adminId, $days) {
            $refund = OrderAdminPurchaseRefund::lockForUpdate()->findOrFail($refundId);
            if ($refund->reason !== self::REASON || $refund->status !== 'customer_requested') {
                throw ValidationException::withMessages(['refund' => 'طلب الرد غير متاح للاعتماد.']);
            }
            $refund->update([
                'status' => 'external_approved',
                'due_at' => now()->addDays($days),
                'admin_id' => $adminId,
                'refund_method' => 'external',
            ]);
            app(CustomerPaymentNotificationService::class)->notify(
                $refund->customer_id,
                'order-status-'.$refund->order_id.'-package-refund-approved',
                'تم اعتماد رد مبلغ الطلب #'.$refund->order_id.' عبر وسيلة الدفع. آخر موعد للرد '.$refund->due_at->format('Y-m-d').'.',
            );
            return $refund;
        }, 3);
    }

    public function complete(int $refundId, int $adminId, string $method, ?string $reference, ?string $note): OrderAdminPurchaseRefund
    {
        if ($method !== 'external') throw ValidationException::withMessages(['method' => 'اختر طريقة الرد.']);
        if ($method === 'external' && trim((string) $reference) === '') throw ValidationException::withMessages(['reference' => 'أدخل مرجع عملية الرد المنفذة.']);
        return DB::transaction(function () use ($refundId, $adminId, $method, $reference, $note) {
            $initial = OrderAdminPurchaseRefund::whereKey($refundId)->firstOrFail();
            User::whereKey($initial->customer_id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($initial->order_id)->lockForUpdate()->firstOrFail();
            $row = OrderAdminPurchaseRefund::whereKey($refundId)->lockForUpdate()->firstOrFail();
            if ($row->reason !== self::REASON) throw ValidationException::withMessages(['refund' => 'هذا الطلب له مسار استرداد آخر.']);
            if ($row->status === 'completed') return $row;
            if ($row->status !== 'external_approved') throw ValidationException::withMessages(['refund' => 'حالة طلب الاسترداد لا تسمح بالتنفيذ.']);
            if ($order->payment_status !== 'paid' || $order->order_status !== 'canceled') throw ValidationException::withMessages(['refund' => 'تعذر التحقق من حالة الطلب المدفوع الملغي.']);
            if ((Schema::hasTable('order_refund_ledgers') && DB::table('order_refund_ledgers')->where('order_id', $order->id)->exists())
                || (Schema::hasTable('refund_requests') && DB::table('refund_requests')->where('order_id', $order->id)->exists())) {
                throw ValidationException::withMessages(['refund' => 'يوجد مسار استرداد آخر لهذا الطلب. راجع السجلات المالية قبل التنفيذ.']);
            }
            if ($method === 'external' && OrderAdminPurchaseRefund::where('reference', $reference)->where('id', '!=', $row->id)->exists()) {
                throw ValidationException::withMessages(['reference' => 'مرجع التحويل مستخدم في طلب استرداد آخر.']);
            }
            $row->update(['status' => 'completed', 'completed_at' => now(), 'admin_id' => $adminId,
                'refund_method' => $method, 'reference' => $reference, 'admin_note' => $note]);
            $order->forceFill(['commerce_flow_status' => 'cancelled', 'commerce_flow_status_updated_at' => now(),
                'operational_blocked_by' => null, 'operational_block_reason' => 'purchase_refund_completed'])->save();
            app(CustomerPaymentNotificationService::class)->notify((int) $row->customer_id, 'order-status-'.$order->id.'-package-refund-completed',
                'تم تسجيل رد ثمن الطلب #'.$order->id.'.');
            return $row->fresh();
        }, 3);
    }
}
