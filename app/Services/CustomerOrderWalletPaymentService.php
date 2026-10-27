<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CustomerOrderWalletPaymentService
{
    /** Return the receipt for repeated payment of the same generated order group. */
    public function debit(int $customerId, float $amount, array $orderIds, string $method): WalletTransaction
    {
        $orderIds = array_values(array_unique(array_map('intval', $orderIds)));
        sort($orderIds);
        if (!$orderIds || !is_finite($amount) || $amount < 0) $this->deny('تعذر تحديد مبلغ الطلب أو مرجعه.');
        $amount = round($amount, 3);
        $reference = 'ow-'.substr(hash('sha256', $customerId.':'.implode(',', $orderIds)), 0, 32);
        return DB::transaction(function () use ($customerId, $amount, $orderIds, $method, $reference) {
            $customer = User::lockForUpdate()->findOrFail($customerId);
            $prior = WalletTransaction::where('user_id', $customerId)->where('transaction_id', $reference)->first();
            if (!$prior && Schema::hasColumn('wallet_transactions', 'order_ids')) {
                // Recognize receipts recorded before deterministic references were introduced.
                $prior = WalletTransaction::where('user_id', $customerId)->where('transaction_type', 'order_place')->whereNotNull('order_ids')->get()
                    ->first(fn ($row) => (bool) array_intersect(array_map('intval', $row->order_ids ?? []), $orderIds));
            }
            if ($prior) {
                if ($prior->order_ids) {
                    $priorIds = array_map('intval', $prior->order_ids); sort($priorIds);
                    if ($priorIds !== $orderIds) $this->deny('يوجد طلب مدفوع ضمن المجموعة. راجع الإدارة دون دفع جديد.');
                }
                if (round((float) $prior->debit, 3) !== $amount) $this->deny('الطلب مدفوع بالفعل بمبلغ مختلف. راجع الإدارة دون دفع جديد.');
                return $prior;
            }
            $orders = Order::where('customer_id', $customerId)->where('is_guest', 0)->whereIn('id', $orderIds)->lockForUpdate()->get();
            if ($orders->count() !== count($orderIds) || $orders->contains(fn ($order) => $order->payment_status !== 'paid'
                || !in_array($order->payment_method, ['pay_by_wallet', 'wallet', 'wallet_balance'], true))) $this->deny('لا يمكن خصم قيمة هذه الطلبات من المحفظة.');
            if (!$customer->is_active || $customer->account_deleted_at) $this->deny('الحساب موقوف. تواصل مع الدعم.');
            if (round((float) $customer->wallet_balance, 3) < $amount) $this->deny('رصيد المحفظة لا يكفي لدفع الطلب.');
            $balance = round((float) $customer->wallet_balance - $amount, 3);
            $customer->update(['wallet_balance' => $balance]);
            $attributes = ['user_id'=>$customerId, 'transaction_id'=>$reference, 'reference'=>'order payment',
                'transaction_type'=>'order_place', 'payment_method'=>$method, 'credit'=>0, 'debit'=>$amount, 'admin_bonus'=>0, 'balance'=>$balance];
            if (Schema::hasColumn('wallet_transactions', 'order_ids')) $attributes['order_ids'] = $orderIds;
            return WalletTransaction::create($attributes);
        }, 3);
    }

    private function deny(string $message): never
    {
        throw ValidationException::withMessages(['wallet' => $message]);
    }
}
