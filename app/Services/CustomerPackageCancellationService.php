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
            $refund = OrderAdminPurchaseRefund::create([
                'order_id' => $order->id, 'customer_id' => $customerId, 'amount' => $amount,
                // This status is deliberately not the auto-crediting scheduled status.
                'status' => 'customer_requested', 'due_at' => now(), 'reason' => self::REASON,
            ]);
            if ($order->customer_cancel_request_status !== 'pending') {
                \App\Models\SupportTicket::create([
                    'customer_id' => $customerId, 'subject' => 'طلب إلغاء الطلب #'.$orderId,
                    'description' => 'طلب العميل #'.$customerId.' إلغاء الطلب المدفوع المعلق لاختيار باقة ورد قيمته إلى المحفظة. راجع طلبات رد المشتريات لاتخاذ القرار.',
                    'type' => 'problem_report', 'priority' => 'normal', 'status' => 'open', 'attachment' => [],
                ]);
            }
            $order->forceFill(['customer_cancel_request_status' => 'pending'])->save();
            app(CustomerPaymentNotificationService::class)->notify($customerId, 'order-status-'.$orderId.'-package-cancel-requested',
                'تم إرسال طلب إلغاء الطلب #'.$orderId.' ورد قيمته إلى الإدارة للمراجعة. لم يُلغَ الطلب بعد.');
            return $refund;
        }, 3);
    }

    public function approve(int $refundId, int $adminId, int $days): OrderAdminPurchaseRefund
    {
        return DB::transaction(function () use ($refundId, $adminId, $days) {
            if ($adminId <= 0 || $days < 15 || $days > 30) throw ValidationException::withMessages(['refund' => 'تعذر تحديد مسؤول المراجعة أو مهلة الرد.']);
            $initial = OrderAdminPurchaseRefund::findOrFail($refundId);
            User::whereKey($initial->customer_id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($initial->order_id)->lockForUpdate()->firstOrFail();
            $refund = OrderAdminPurchaseRefund::lockForUpdate()->findOrFail($refundId);
            if ($refund->reason !== self::REASON || $refund->status !== 'customer_requested') {
                throw ValidationException::withMessages(['refund' => 'طلب الرد غير متاح للاعتماد.']);
            }
            if ($order->payment_status !== 'paid') throw ValidationException::withMessages(['refund' => 'لم تعد حالة الدفع تسمح بالإلغاء ورد المبلغ.']);
            if ($order->order_status !== 'canceled') {
                if ($order->order_status !== 'pending' || $order->customer_eligible_at
                    || !in_array('monthly_subscription', $order->customer_pending_reasons ?? [], true)) {
                    throw ValidationException::withMessages(['refund' => 'تغيرت حالة الطلب. راجع حالته قبل اعتماد الإلغاء.']);
                }
                app(CustomerCancellationStockService::class)->restore($order);
                $order->forceFill([
                    'order_status' => 'canceled', 'customer_fulfillment_status' => 'canceled',
                    'customer_cancel_request_status' => 'approved',
                    'commerce_flow_status' => 'purchase_refund_pending', 'commerce_flow_status_updated_at' => now(),
                    'operational_blocked_by' => 'customer_cancellation', 'operational_block_reason' => self::REASON,
                ])->save();
            }
            $refund->update([
                'status' => 'wallet_approved',
                'due_at' => now()->addDays($days),
                'admin_id' => $adminId,
                'refund_method' => 'purchase_wallet',
            ]);
            app(CustomerPaymentNotificationService::class)->notify(
                $refund->customer_id,
                'order-status-'.$refund->order_id.'-package-refund-approved',
                'تم اعتماد رد مبلغ الطلب #'.$refund->order_id.' إلى محفظة حسابك. موعد الرد '.$refund->due_at->format('Y-m-d').'.',
            );
            return $refund;
        }, 3);
    }

    public function reject(int $refundId, int $adminId, string $reason): OrderAdminPurchaseRefund
    {
        if ($adminId <= 0 || !trim($reason)) throw ValidationException::withMessages(['reason' => 'اكتب سبب رفض الإلغاء.']);
        return DB::transaction(function () use ($refundId, $adminId, $reason) {
            $initial = OrderAdminPurchaseRefund::findOrFail($refundId);
            User::whereKey($initial->customer_id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($initial->order_id)->lockForUpdate()->firstOrFail();
            $refund = OrderAdminPurchaseRefund::lockForUpdate()->findOrFail($refundId);
            if ($refund->reason !== self::REASON || $refund->status !== 'customer_requested' || $order->order_status === 'canceled') {
                throw ValidationException::withMessages(['refund' => 'لا يمكن رفض هذا الطلب في حالته الحالية.']);
            }
            $refund->update(['status' => 'rejected', 'admin_id' => $adminId, 'admin_note' => trim($reason)]);
            $order->forceFill(['customer_cancel_request_status' => 'rejected'])->save();
            app(CustomerPaymentNotificationService::class)->notify($refund->customer_id,
                'order-status-'.$order->id.'-package-cancel-rejected',
                'لم تتم الموافقة على إلغاء الطلب #'.$order->id.'. السبب: '.trim($reason).'. يمكنك متابعة طلبك من حسابك.');
            return $refund->fresh();
        }, 3);
    }

    public function complete(int $refundId, int $adminId, string $method, ?string $reference, ?string $note): OrderAdminPurchaseRefund
    {
        if ($method !== 'purchase_wallet') throw ValidationException::withMessages(['method' => 'اختر طريقة الرد.']);
        return DB::transaction(function () use ($refundId, $adminId, $method, $reference, $note) {
            $initial = OrderAdminPurchaseRefund::whereKey($refundId)->firstOrFail();
            User::whereKey($initial->customer_id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($initial->order_id)->lockForUpdate()->firstOrFail();
            $row = OrderAdminPurchaseRefund::whereKey($refundId)->lockForUpdate()->firstOrFail();
            if ($row->reason !== self::REASON) throw ValidationException::withMessages(['refund' => 'هذا الطلب له مسار استرداد آخر.']);
            if ($row->status === 'completed') return $row;
            if ($row->status !== 'wallet_approved') throw ValidationException::withMessages(['refund' => 'حالة طلب الاسترداد لا تسمح بالتنفيذ.']);
            if ($order->payment_status !== 'paid' || $order->order_status !== 'canceled') throw ValidationException::withMessages(['refund' => 'تعذر التحقق من حالة الطلب المدفوع الملغي.']);
            if ((Schema::hasTable('order_refund_ledgers') && DB::table('order_refund_ledgers')->where('order_id', $order->id)->exists())
                || (Schema::hasTable('refund_requests') && DB::table('refund_requests')->where('order_id', $order->id)->exists())) {
                throw ValidationException::withMessages(['refund' => 'يوجد مسار استرداد آخر لهذا الطلب. راجع السجلات المالية قبل التنفيذ.']);
            }
            if ($row->due_at->isFuture()) {
                throw ValidationException::withMessages(['refund' => 'موعد رد المبلغ لم يحِن بعد.']);
            }
            $reference = 'customer-cancel-order-'.$order->id;
            $transaction = app(CustomerPurchaseWalletPolicyService::class)->creditPurchaseRefundBaseCurrency(
                (int) $row->customer_id, (float) $row->amount, $reference,
            );
            if ((int) $transaction->user_id !== (int) $row->customer_id
                || $transaction->transaction_type !== 'order_refund'
                || abs((float) $transaction->credit - (float) $row->amount) > 0.0001
                || (float) $transaction->debit !== 0.0) {
                throw ValidationException::withMessages(['refund' => 'مرجع رد المبلغ مرتبط بحركة غير مطابقة.']);
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
