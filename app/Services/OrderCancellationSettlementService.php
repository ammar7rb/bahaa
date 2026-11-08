<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderLogisticsEvent;
use App\Models\OrderWorkItem;
use App\Models\OrderWorkItemEvent;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderCancellationSettlementService
{
    /** Expire only the unspent part of each cancellation credit. Refund credit is spent first. */
    public function expireCredits(int $limit = 100, ?int $customerId = null): int
    {
        if (!Schema::hasTable('order_cancellation_settlements')) return 0;
        $ids = DB::table('order_cancellation_settlements')->where('canceled_by', 'customer')
            ->whereNull('credit_expired_at')->where('credit_expires_at', '<=', now())
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->orderBy('id')->limit($limit)->pluck('id');
        $processed = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$processed): void {
                $target = DB::table('order_cancellation_settlements')->where('id', $id)->first();
                if (!$target || $target->credit_expired_at) return;
                $user = User::query()->lockForUpdate()->findOrFail($target->customer_id);
                $target = DB::table('order_cancellation_settlements')->where('id', $id)->lockForUpdate()->first();
                if (!$target || $target->credit_expired_at) return;
                $grants = DB::table('order_cancellation_settlements')->where('customer_id', $target->customer_id)
                    ->where('canceled_by', 'customer')->where('created_at', '<=', $target->credit_expires_at)
                    ->orderBy('created_at')->orderBy('id')->get();
                $balances = $grants->mapWithKeys(fn ($grant) => [$grant->id => round((float) $grant->refund_amount, 3)])->all();
                $receiptIds = $grants->mapWithKeys(function ($grant) use ($target) {
                    $id = WalletTransaction::query()->where('user_id', $target->customer_id)
                        ->where('reference', $grant->financial_reference)->value('id');
                    if (!$id) throw new \RuntimeException('Cancellation credit wallet receipt is missing for settlement '.$grant->id);
                    return [$grant->id => (int) $id];
                })->all();
                $debits = WalletTransaction::query()->where('user_id', $target->customer_id)
                    ->where('debit', '>', 0)->where('transaction_type', '!=', 'cancellation_credit_expiry')
                    ->where('id', '>', min($receiptIds))
                    ->where('created_at', '<=', $target->credit_expires_at)
                    ->orderBy('created_at')->orderBy('id')->get(['id', 'debit', 'created_at']);
                foreach ($debits as $debit) {
                    $remaining = round((float) $debit->debit, 3);
                    foreach ($grants as $grant) {
                        if ($remaining <= 0) break;
                        if ($receiptIds[$grant->id] >= $debit->id || $grant->credit_expires_at <= $debit->created_at) continue;
                        $used = min($remaining, $balances[$grant->id]);
                        $balances[$grant->id] = round($balances[$grant->id] - $used, 3);
                        $remaining = round($remaining - $used, 3);
                    }
                }
                $expired = $balances[$target->id];
                if ($expired > round((float) $user->wallet_balance, 3) + 0.0001) {
                    throw new \RuntimeException('Cancellation credit expiry exceeds wallet balance for customer '.$user->id);
                }
                if ($expired > 0) {
                    $user->wallet_balance = round((float) $user->wallet_balance - $expired, 3);
                    $user->save();
                    WalletTransaction::query()->create([
                        'user_id' => $user->id, 'transaction_id' => 'cancel-credit-expiry-'.$id,
                        'reference' => 'admin-cancel-order-'.$target->order_id,
                        'transaction_type' => 'cancellation_credit_expiry', 'payment_method' => 'purchase_wallet',
                        'credit' => 0, 'debit' => $expired, 'admin_bonus' => 0,
                        'balance' => $user->wallet_balance,
                    ]);
                }
                DB::table('order_cancellation_settlements')->where('id', $id)->update([
                    'credit_expired_at' => now(), 'credit_expired_amount' => $expired, 'updated_at' => now(),
                ]);
                $processed++;
            }, 3);
        }
        return $processed;
    }

    public function refundCustomer(int $orderId, float $refund, float $fee, float $discount, CarbonInterface $expiresAt, string $reason, int $adminId): object
    {
        $this->assertMoney($refund, $fee, $discount, $adminId, $reason);
        if ($refund <= 0 || $expiresAt->isPast()) $this->deny('حدد مبلغ رد موجبًا وتاريخ صلاحية لاحقًا.');
        return DB::transaction(function () use ($orderId, $refund, $fee, $discount, $expiresAt, $reason, $adminId) {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $key = 'customer:'.$orderId;
            $prior = DB::table('order_cancellation_settlements')->where('operation_key', $key)->first();
            if ($prior) $this->deny('تمت معالجة رد هذا الطلب من قبل.');
            if ($order->is_guest || !$order->customer_id || $order->payment_status !== 'paid' || $order->order_status !== 'canceled') {
                $this->deny('رد الرصيد متاح للطلب المدفوع والملغي المرتبط بحساب عميل فقط.');
            }
            $cancellationEvent = OrderLogisticsEvent::query()->where('order_id', $orderId)
                ->where('order_status', 'canceled')->latest('id')->first();
            if ($cancellationEvent?->actor_type === 'seller') {
                $this->deny('إلغاء التاجر ينتقل إلى إعادة تعيين المنتج؛ راجع رسومه بدل رد قيمة الطلب.');
            }
            foreach (['order_admin_purchase_refunds', 'order_refund_ledgers', 'refund_requests'] as $table) {
                if (DB::table($table)->where('order_id', $orderId)->exists()) $this->deny('يوجد مسار استرداد آخر لهذا الطلب.');
            }
            User::query()->lockForUpdate()->findOrFail($order->customer_id);
            $gross = round((float) ($order->init_order_amount ?: $order->order_amount), 3);
            if ($gross <= 0 || round($refund + $fee + $discount, 3) > $gross + 0.0001) {
                $this->deny('مجموع الرد والرسوم والخصم يتجاوز قيمة الطلب المدفوعة.');
            }
            $reference = 'admin-cancel-order-'.$orderId;
            $receipt = app(CustomerPurchaseWalletPolicyService::class)->creditPurchaseRefundBaseCurrency((int) $order->customer_id, $refund, $reference);
            if ((int) $receipt->user_id !== (int) $order->customer_id
                || $receipt->transaction_type !== 'order_refund'
                || abs((float) $receipt->credit - $refund) > 0.0001
                || (float) $receipt->debit !== 0.0) {
                $this->deny('مرجع الرصيد مرتبط بحركة غير مطابقة.');
            }
            DB::table('order_cancellation_settlements')->insert([
                'operation_key' => $key, 'order_id' => $orderId, 'customer_id' => $order->customer_id,
                'canceled_by' => 'customer', 'actor_id' => $order->customer_id,
                'reason' => trim($reason), 'canceled_at' => $order->updated_at ?? now(),
                'gross_amount' => $gross, 'fee_amount' => $fee, 'discount_amount' => $discount,
                'refund_amount' => $refund, 'financial_reference' => $reference,
                'credit_expires_at' => $expiresAt, 'reviewed_by_admin_id' => $adminId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->eventForOrder($orderId, $adminId, 'customer_cancellation_refunded', [
                'reason' => trim($reason), 'gross' => $gross, 'fees' => $fee,
                'discounts' => $discount, 'refunded' => $refund,
                'credit_expires_at' => $expiresAt->toIso8601String(), 'wallet_transaction_id' => $receipt->id,
            ]);
            return DB::table('order_cancellation_settlements')->where('operation_key', $key)->first();
        }, 3);
    }

    public function chargeSeller(int $workItemId, float $fee, string $reason, int $adminId): object
    {
        $this->assertMoney($fee, 0, 0, $adminId, $reason);
        return DB::transaction(function () use ($workItemId, $fee, $reason, $adminId) {
            $item = OrderWorkItem::query()->lockForUpdate()->findOrFail($workItemId);
            $key = 'seller:'.$workItemId;
            $prior = DB::table('order_cancellation_settlements')->where('operation_key', $key)->first();
            if ($prior) $this->deny('تمت معالجة رسوم إلغاء هذه الحصة من قبل.');
            if ($item->status !== 'canceled' || !$item->seller_id) $this->deny('اختر حصة ملغاة مرتبطة بتاجر.');
            $sellerCancellation = OrderLogisticsEvent::query()->where('order_id', $item->order_id)
                ->where('order_status', 'canceled')->where('actor_type', 'seller')
                ->where('actor_id', $item->seller_id)->latest('id')->first();
            if (!$sellerCancellation) $this->deny('لا يوجد إلغاء موثق من هذا التاجر لهذا الطلب.');
            $ledger = app(SellerLedgerService::class);
            $ledger->lockSellerFinancialState((int) $item->seller_id);
            $available = max(0, (float) ($ledger->summary((int) $item->seller_id)[SellerLedgerService::AVAILABLE] ?? 0));
            $debited = min(round($available, 3), $fee);
            $debt = round($fee - $debited, 3);
            if ($debited > 0) $ledger->post((int) $item->seller_id, 'order_cancellation_fee', 'order-cancel-fee:'.$workItemId,
                [['bucket' => SellerLedgerService::AVAILABLE, 'direction' => 'debit', 'amount' => $debited]],
                Order::class, (int) $item->order_id,
                ['order_id' => $item->order_id, 'work_item_id' => $workItemId, 'reason' => trim($reason)], $adminId);
            DB::table('order_cancellation_settlements')->insert([
                'operation_key' => $key, 'order_id' => $item->order_id,
                'order_work_item_id' => $workItemId, 'seller_id' => $item->seller_id,
                'canceled_by' => 'seller', 'actor_id' => $item->seller_id,
                'reason' => trim($reason), 'canceled_at' => $sellerCancellation->created_at ?? now(),
                'fee_amount' => $fee, 'seller_debited_amount' => $debited, 'seller_debt_amount' => $debt,
                'financial_reference' => 'order-cancel-fee:'.$workItemId,
                'reviewed_by_admin_id' => $adminId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            OrderWorkItemEvent::query()->create([
                'work_item_id' => $item->id, 'order_id' => $item->order_id,
                'order_detail_id' => $item->order_detail_id, 'actor_id' => $adminId,
                'actor_type' => 'admin', 'event_key' => 'seller_cancellation_fee_assessed',
                'status_before' => $item->status, 'status_after' => $item->status,
                'snapshot' => ['reason' => trim($reason), 'fee' => $fee, 'debited' => $debited, 'debt' => $debt],
                'created_at' => now(),
            ]);
            return DB::table('order_cancellation_settlements')->where('operation_key', $key)->first();
        }, 3);
    }

    private function eventForOrder(int $orderId, int $adminId, string $key, array $snapshot): void
    {
        $items = OrderWorkItem::query()->where('order_id', $orderId)->get();
        foreach ($items as $item) OrderWorkItemEvent::query()->create([
            'work_item_id' => $item->id, 'order_id' => $orderId,
            'order_detail_id' => $item->order_detail_id, 'actor_id' => $adminId,
            'actor_type' => 'admin', 'event_key' => $key,
            'status_before' => $item->status, 'status_after' => $item->status,
            'snapshot' => $snapshot, 'created_at' => now(),
        ]);
    }

    private function assertMoney(float $amount, float $fee, float $discount, int $adminId, string $reason): void
    {
        foreach ([$amount, $fee, $discount] as $value) {
            if (!is_finite($value) || $value < 0 || $value > 10000000 || round($value, 3) !== $value) $this->deny('أدخل مبالغ موجبة بدقة حتى ثلاثة أرقام عشرية.');
        }
        if ($adminId <= 0 || trim($reason) === '') $this->deny('اكتب سبب الإلغاء وحدد مسؤول المراجعة.');
    }

    private function deny(string $message): never
    {
        throw ValidationException::withMessages(['cancellation' => $message]);
    }
}
