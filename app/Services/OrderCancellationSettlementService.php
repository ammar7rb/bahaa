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
    /** A withdrawal date must not erase customer funds. */
    public function expireCustomerCredits(int $customerId): void
    {
    }

    public function expireCredits(int $limit = 100, ?int $customerId = null): int
    {
        return 0;
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
            if ($cancellationEvent?->actor_type !== 'customer'
                || (int) $cancellationEvent->actor_id !== (int) $order->customer_id) {
                $this->deny('يلزم وجود إلغاء موثق من العميل قبل رد الرصيد.');
            }
            foreach (['order_admin_purchase_refunds', 'order_refund_ledgers', 'refund_requests'] as $table) {
                if (DB::table($table)->where('order_id', $orderId)->exists()) $this->deny('يوجد مسار استرداد آخر لهذا الطلب.');
            }
            $customer = User::query()->lockForUpdate()->findOrFail($order->customer_id);
            $walletBefore = (float) $customer->wallet_balance;
            $gross = round((float) ($order->init_order_amount ?: $order->order_amount), 3);
            if ((float) $order->paid_amount > 0) $gross = min($gross, round((float) $order->paid_amount, 3));
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
                'reason' => trim($reason), 'canceled_at' => $cancellationEvent->created_at,
                'gross_amount' => $gross, 'fee_amount' => $fee, 'discount_amount' => $discount,
                'refund_amount' => $refund, 'financial_reference' => $reference,
                'credit_expires_at' => $expiresAt, 'reviewed_by_admin_id' => $adminId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $order->forceFill(['customer_cancel_request_status' => 'approved',
                'commerce_flow_status' => 'cancelled', 'commerce_flow_status_updated_at' => now()])->save();
            $this->eventForOrder($orderId, $adminId, 'customer_cancellation_refunded', [
                'reason' => trim($reason), 'gross' => $gross, 'fees' => $fee,
                'discounts' => $discount, 'refunded' => $refund,
                'credit_expires_at' => $expiresAt->toIso8601String(), 'wallet_transaction_id' => $receipt->id,
                'financial_before' => ['customer_wallet' => $walletBefore],
                'financial_after' => ['customer_wallet' => (float) $customer->fresh()->wallet_balance],
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
            $mapping = Schema::hasTable('vendor_fulfillment_request_items')
                ? DB::table('vendor_fulfillment_request_items')->where('order_detail_id', $item->order_detail_id)->first() : null;
            if ($mapping) {
                $request = DB::table('vendor_fulfillment_requests')->where('id', $mapping->request_id)->lockForUpdate()->first();
                $event = DB::table('vendor_fulfillment_events')->where('request_id', $mapping->request_id)
                    ->where('event_type', 'canceled')->orderByDesc('id')->first();
                $eventData = $event ? json_decode((string) $event->metadata, true) : null;
                if (!$request || $request->status !== 'canceled' || (int) $request->order_id !== (int) $item->order_id || (int) $request->seller_id !== (int) $item->seller_id
                    || ($eventData['actor'] ?? null) !== 'seller') $this->deny('لا يوجد إلغاء موثق من هذا التاجر لهذه الحصة.');
                $charge = $request->penalty_charge_id
                    ? DB::table('vendor_debt_charges')->where('id', $request->penalty_charge_id)->where('seller_id', $item->seller_id)->first() : null;
                if ($request->penalty_charge_id && !$charge) $this->deny('سجل غرامة التاجر غير مكتمل.');
                if (($fee > 0 || $charge) && DB::table('vendor_fulfillment_request_items')->where('request_id', $request->id)->count() !== 1) {
                    $this->deny('الغرامة تخص عدة منتجات؛ يلزم مراجعة توزيعها قبل تسجيل رسوم حصة واحدة.');
                }
                if ($fee > 0 && DB::table('order_cancellation_settlements')->where('financial_reference', 'fulfillment-cancellation-'.$request->id)->exists()) {
                    $this->deny('تم تسجيل رسوم هذا الجزء سابقًا.');
                }
                $financialBefore = null;
                if ($charge && abs($fee - (float) $charge->amount) > 0.0001) {
                    $ledger = app(SellerLedgerService::class);
                    $ledger->lockSellerFinancialState((int) $item->seller_id);
                    $financialBefore = ['operating_balance'=>(float)($ledger->summary((int)$item->seller_id, initializeLegacy:false)[SellerLedgerService::OPERATING] ?? 0),
                        'outstanding_debt'=>app(VendorDebtService::class)->outstanding((int)$item->seller_id),
                        'original_charge'=>(float)$charge->amount, 'charge_outstanding'=>(float)($charge->outstanding ?? 0)];
                }
                if (!$charge && $fee > 0) {
                    $ledger = app(SellerLedgerService::class);
                    $ledger->lockSellerFinancialState((int) $item->seller_id);
                    $financialBefore = ['operating_balance'=>(float)($ledger->summary((int)$item->seller_id, initializeLegacy:false)[SellerLedgerService::OPERATING] ?? 0),
                        'outstanding_debt'=>app(VendorDebtService::class)->outstanding((int)$item->seller_id)];
                    $charge = app(VendorDebtService::class)->assess((int) $item->seller_id, 'cancellation_penalty',
                        'fulfillment_request', (string) $request->id, 'fulfillment-cancellation-'.$request->id, $fee,
                        ['order_id'=>$item->order_id, 'work_item_id'=>$item->id, 'reason'=>trim($reason), 'source'=>'admin_review'], $adminId);
                    DB::table('vendor_fulfillment_requests')->where('id', $request->id)->update(['penalty_charge_id'=>$charge->id, 'updated_at'=>now()]);
                }
                $assessed = $charge ? round((float) $charge->amount, 3) : 0.0;
                $adjustment = null;
                if ($charge && $fee > $assessed + 0.0001) {
                    $adjustment = app(VendorDebtService::class)->assess((int) $item->seller_id, 'cancellation_penalty',
                        'order_fee_adjustment', (string) $item->id, 'order-fee-adjustment:'.$item->id, round($fee - $assessed, 3),
                        ['order_id'=>$item->order_id, 'work_item_id'=>$item->id, 'reason'=>trim($reason), 'original_charge_id'=>$charge->id], $adminId);
                } elseif ($charge && $fee < $assessed - 0.0001) {
                    $charge = app(VendorDebtService::class)->reduceUnpaidCharge((int) $item->seller_id, (int) $charge->id,
                        round($assessed - $fee, 3), $adminId, trim($reason));
                    $assessed = $fee;
                }
                $debited = $charge ? round((float) $charge->wallet_collected, 3) : 0.0;
                $debt = $charge ? round((float) $charge->debt_assessed, 3) : 0.0;
                if ($charge && $fee < (float) $charge->amount && Schema::hasTable('vendor_penalty_refunds')) {
                    $debited = round((float) DB::table('vendor_debt_repayments')->where('charge_id', $charge->id)->whereNull('reversed_at')->sum('amount')
                        - (float) DB::table('vendor_penalty_refunds')->where('charge_id', $charge->id)->sum('amount'), 3);
                    $debt = round((float) $charge->outstanding, 3);
                }
                if ($adjustment) {
                    $debited += (float) $adjustment->wallet_collected;
                    $debt += (float) $adjustment->debt_assessed;
                    $assessed = $fee;
                }
                DB::table('order_cancellation_settlements')->insert([
                    'operation_key' => $key, 'order_id' => $item->order_id,
                    'order_work_item_id' => $workItemId, 'seller_id' => $item->seller_id,
                    'canceled_by' => 'seller', 'actor_id' => $item->seller_id,
                    'reason' => trim($reason), 'canceled_at' => $request->canceled_at ?? $event->created_at,
                    'fee_amount' => $assessed, 'seller_debited_amount' => $debited, 'seller_debt_amount' => $debt,
                    'financial_reference' => 'fulfillment-cancellation-'.$request->id,
                    'reviewed_by_admin_id' => $adminId, 'created_at' => now(), 'updated_at' => now(),
                ]);
                OrderWorkItemEvent::query()->create([
                    'work_item_id' => $item->id, 'order_id' => $item->order_id,
                    'order_detail_id' => $item->order_detail_id, 'actor_id' => $adminId,
                    'actor_type' => 'admin', 'event_key' => 'seller_cancellation_fee_reviewed',
                    'status_before' => $item->status, 'status_after' => $item->status,
                    'snapshot' => ['reason' => trim($reason), 'fee' => $assessed, 'debited' => $debited,
                        'debt' => $debt, 'vendor_debt_charge_id' => $charge?->id, 'adjustment_charge_id' => $adjustment?->id,
                        'financial_before'=>$financialBefore,
                        'financial_after'=>$financialBefore === null ? ['recorded_charge'=>$assessed, 'collected'=>$debited, 'debt'=>$debt]
                            : ['operating_balance'=>(float)($ledger->summary((int)$item->seller_id, initializeLegacy:false)[SellerLedgerService::OPERATING] ?? 0),
                                'outstanding_debt'=>app(VendorDebtService::class)->outstanding((int)$item->seller_id)]], 'created_at' => now(),
                ]);
                return DB::table('order_cancellation_settlements')->where('operation_key', $key)->first();
            }
            $sellerCancellation = OrderLogisticsEvent::query()->where('order_id', $item->order_id)
                ->where('order_status', 'canceled')->where('actor_type', 'seller')
                ->where('actor_id', $item->seller_id)->latest('id')->first();
            if (!$sellerCancellation) $this->deny('لا يوجد إلغاء موثق من هذا التاجر لهذا الطلب.');
            $ledger = app(SellerLedgerService::class);
            $ledger->lockSellerFinancialState((int) $item->seller_id);
            $available = max(0, (float) ($ledger->summary((int) $item->seller_id, initializeLegacy:false)[SellerLedgerService::OPERATING] ?? 0));
            $debtBefore = app(VendorDebtService::class)->outstanding((int) $item->seller_id);
            $charge = $fee > 0 ? app(VendorDebtService::class)->assess((int) $item->seller_id, 'cancellation_penalty',
                'order_work_item', (string) $item->id, 'order-cancel-fee:'.$item->id, $fee,
                ['order_id'=>$item->order_id, 'work_item_id'=>$item->id, 'reason'=>trim($reason), 'source'=>'admin_review'], $adminId) : null;
            $debited = $charge ? (float) $charge->wallet_collected : 0.0;
            $debt = $charge ? (float) $charge->debt_assessed : 0.0;
            DB::table('order_cancellation_settlements')->insert([
                'operation_key' => $key, 'order_id' => $item->order_id,
                'order_work_item_id' => $workItemId, 'seller_id' => $item->seller_id,
                'canceled_by' => 'seller', 'actor_id' => $item->seller_id,
                'reason' => trim($reason), 'canceled_at' => $sellerCancellation->created_at ?? now(),
                'fee_amount' => $fee, 'seller_debited_amount' => $debited, 'seller_debt_amount' => $debt,
                'financial_reference' => $charge ? 'vendor-debt-charge:'.$charge->id : 'order-cancel-fee:'.$workItemId,
                'reviewed_by_admin_id' => $adminId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            OrderWorkItemEvent::query()->create([
                'work_item_id' => $item->id, 'order_id' => $item->order_id,
                'order_detail_id' => $item->order_detail_id, 'actor_id' => $adminId,
                'actor_type' => 'admin', 'event_key' => 'seller_cancellation_fee_assessed',
                'status_before' => $item->status, 'status_after' => $item->status,
                'snapshot' => ['reason' => trim($reason), 'fee' => $fee, 'debited' => $debited, 'debt' => $debt,
                    'financial_before' => ['available' => $available, 'outstanding_debt' => $debtBefore],
                        'financial_after' => ['available' => (float) ($ledger->summary((int) $item->seller_id, initializeLegacy:false)[SellerLedgerService::OPERATING] ?? 0),
                        'outstanding_debt' => app(VendorDebtService::class)->outstanding((int) $item->seller_id)]],
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
