<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerWalletWithdrawalService
{
    public function feePercent(): float
    {
        return max(0, min(100, (float) (BusinessSetting::where('type', 'customer_withdrawal_fee_percent')->value('value') ?? 10)));
    }

    public function days(): int
    {
        return max(15, min(30, (int) (BusinessSetting::where('type', 'customer_withdrawal_days')->value('value') ?? 30)));
    }

    public function request(int $customerId, float $amount, float $expectedFee, string $key): object
    {
        if (!is_finite($amount) || $amount <= 0) {
            $this->invalid('أدخل مبلغًا صحيحًا للسحب.');
        }

        return DB::transaction(function () use ($customerId, $amount, $expectedFee, $key) {
            $customer = User::lockForUpdate()->findOrFail($customerId);
            $existing = DB::table('customer_wallet_withdrawals')->where('customer_id', $customerId)->where('request_key', $key)->first();
            $amount = round($amount, 3);
            if ($existing) {
                if (abs((float) $existing->amount - $amount) > 0.0001 || abs((float) $existing->fee_percent - $expectedFee) > 0.0001) {
                    $this->invalid('مرجع الطلب مستخدم لعملية أخرى.');
                }
                return $existing;
            }
            $feePercent = $this->feePercent();
            if (abs($feePercent - $expectedFee) > 0.0001) {
                $this->invalid('تغيرت نسبة الرسوم. حدّث الصفحة وراجع الصافي قبل التأكيد.');
            }
            if (!$customer->is_active || $customer->account_deleted_at || $amount > round((float) $customer->wallet_balance, 3)) {
                $this->invalid('الحساب غير متاح للسحب أو الرصيد غير كافٍ.');
            }
            $fee = round($amount * $feePercent / 100, 3);
            $net = round($amount - $fee, 3);
            if ($net <= 0) {
                $this->invalid('صافي السحب يجب أن يكون أكبر من صفر.');
            }
            $id = DB::table('customer_wallet_withdrawals')->insertGetId([
                'customer_id' => $customerId, 'request_key' => $key, 'amount' => $amount,
                'fee_percent' => $feePercent, 'fee_amount' => $fee, 'net_amount' => $net,
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $customer->wallet_balance = round((float) $customer->wallet_balance - $amount, 3);
            $customer->save();
            $this->ledger($customer, $id, $amount, false);
            return DB::table('customer_wallet_withdrawals')->find($id);
        }, 3);
    }

    public function review(int $id, int $adminId, string $decision, ?string $note, ?string $reference): object
    {
        return DB::transaction(function () use ($id, $adminId, $decision, $note, $reference) {
            $initial = DB::table('customer_wallet_withdrawals')->find($id);
            abort_unless($initial, 404);
            $customer = User::lockForUpdate()->findOrFail($initial->customer_id);
            $row = DB::table('customer_wallet_withdrawals')->where('id', $id)->lockForUpdate()->first();
            if ($decision === 'approve' && $row->status === 'pending') {
                $updates = ['status' => 'approved', 'approved_at' => now(), 'due_at' => now()->addDays($this->days())];
            } elseif ($decision === 'reject' && in_array($row->status, ['pending', 'approved'], true)) {
                if (!trim((string) $note)) {
                    $this->invalid('اكتب سبب الرفض.');
                }
                $customer->wallet_balance = round((float) $customer->wallet_balance + (float) $row->amount, 3);
                $customer->save();
                $this->ledger($customer, $id, (float) $row->amount, true);
                $updates = ['status' => 'rejected'];
            } elseif ($decision === 'complete' && $row->status === 'approved') {
                if (!trim((string) $reference)) {
                    $this->invalid('سجّل مرجع التحويل بعد الصرف الفعلي للعميل.');
                }
                if (now()->lt(\Illuminate\Support\Carbon::parse($row->due_at))) {
                    $this->invalid('موعد الصرف المعتمد لم يحِن بعد.');
                }
                if (DB::table('customer_wallet_withdrawals')->where('reference', trim($reference))->where('id', '!=', $id)->exists()) {
                    $this->invalid('مرجع الصرف مسجل بالفعل.');
                }
                $updates = ['status' => 'completed', 'completed_at' => now(), 'reference' => trim($reference)];
            } else {
                $this->invalid('حالة طلب السحب تغيرت. حدّث الصفحة.');
            }
            DB::table('customer_wallet_withdrawals')->where('id', $id)->update($updates + [
                'admin_id' => $adminId, 'admin_note' => $note, 'updated_at' => now(),
            ]);
            app(CustomerPaymentNotificationService::class)->notify($customer->id,
                'wallet-withdrawal-'.$id.'-'.$updates['status'],
                match ($updates['status']) {
                    'approved' => 'تم اعتماد طلب السحب #'.$id.'. موعد الصرف '.$updates['due_at']->format('Y-m-d').'. ستتواصل الإدارة لتنسيق وسيلة الصرف.',
                    'rejected' => 'تم رفض طلب السحب #'.$id.' وإعادة المبلغ كاملًا للمحفظة. '.$note,
                    default => 'تم تسجيل صرف طلب السحب #'.$id.'. مرجع العملية '.$reference,
                });
            return DB::table('customer_wallet_withdrawals')->find($id);
        }, 3);
    }

    private function ledger(User $customer, int $id, float $amount, bool $credit): void
    {
        WalletTransaction::create([
            'user_id' => $customer->id, 'transaction_id' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'customer-withdrawal-'.$id.($credit ? '-return' : '-hold'),
            'transaction_type' => $credit ? 'withdrawal_return' : 'withdrawal_hold', 'payment_method' => 'purchase_wallet',
            'credit' => $credit ? $amount : 0, 'debit' => $credit ? 0 : $amount,
            'admin_bonus' => 0, 'balance' => $customer->wallet_balance,
        ]);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['withdrawal' => $message]);
    }
}
