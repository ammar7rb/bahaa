<?php

namespace App\Services;

use App\Models\CustomerBalanceDeposit;
use App\Models\CustomerInsuranceLedgerEntry;
use App\Models\User;
use App\Models\WalletTransaction;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerBalanceDepositService
{
    public function receiptResponse(CustomerBalanceDeposit $deposit): \Illuminate\Http\Response
    {
        $bytes = \Illuminate\Support\Facades\Crypt::decryptString(\Illuminate\Support\Facades\Storage::disk('local')->get($deposit->payment_proof));
        return response($bytes, 200, [
            'Content-Type' => getimagesizefromstring($bytes)['mime'] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function review(CustomerBalanceDeposit $deposit, string $decision, int $adminId, ?string $note): CustomerBalanceDeposit
    {
        if (!in_array($decision, ['approve', 'reject'], true) || $adminId <= 0) {
            throw new DomainException('invalid_deposit_decision');
        }
        return DB::transaction(function () use ($deposit, $decision, $adminId, $note) {
            // All account financial workflows lock the customer before their source record.
            User::query()->lockForUpdate()->findOrFail($deposit->customer_id);
            $deposit = CustomerBalanceDeposit::query()->lockForUpdate()->findOrFail($deposit->id);
            if (!in_array($deposit->status, ['pending','legacy_review'],true)) {
                throw new DomainException('deposit_already_reviewed');
            }
            if ($decision === 'reject' && !trim((string) $note)) {
                throw new DomainException('deposit_rejection_note_required');
            }
            if ($decision === 'approve') {
                $customer = User::query()->lockForUpdate()->findOrFail($deposit->customer_id);
                if (!is_finite($deposit->amount) || $deposit->amount <= 0) throw new DomainException('invalid_deposit_amount');
                $reference = 'customer-offline-deposit-' . $deposit->id;
                if ($deposit->wallet_type === 'insurance') {
                    app(CustomerLegacyPaymentService::class)->record($reference,(int)$customer->id,(float)$deposit->submitted_amount,$deposit->currency_code,[
                        'deposit_id'=>$deposit->id,'base_amount'=>$deposit->amount,'reviewed_by'=>$adminId,
                    ]);
                } elseif ($deposit->wallet_type === 'purchase') {
                    $allocation = app(CustomerAccountDebtService::class)->allocate((int)$customer->id, $deposit->amount, $reference);
                    // Amounts are stored in the same base currency as wallet_balance.
                    $customer->wallet_balance = ((int)round((float)$customer->wallet_balance*1000)+(int)round($allocation['net']*1000))/1000;
                    $customer->save();
                    WalletTransaction::query()->create([
                        'user_id' => $customer->id, 'transaction_id' => $deposit->transaction_number,
                        'transaction_type' => 'add_fund', 'reference' => $reference, 'payment_method' => 'offline',
                        'credit' => $allocation['net'], 'debit' => 0, 'admin_bonus' => 0, 'balance' => $customer->wallet_balance,
                    ]);
                } else {
                    throw new DomainException('invalid_deposit_wallet');
                }
            }
            $deposit->update(['status' => $decision === 'approve' ? 'paid' : 'rejected', 'reviewed_by_admin_id' => $adminId, 'review_note' => $note, 'reviewed_at' => now()]);
            if ($deposit->wallet_type === 'purchase') {
                $message = $decision === 'approve'
                    ? (($allocation['recovered'] ?? 0) > 0
                        ? 'تم قبول شحن '.number_format($allocation['gross'],3).'؛ تم سداد '.number_format($allocation['recovered'],3).' من المديونية وإضافة '.number_format($allocation['net'],3).' إلى محفظتكم. المديونية المتبقية '.number_format($allocation['remaining_debt'],3).'.'
                        : 'تم قبول طلب شحن المحفظة وإضافة مبلغ '.number_format($deposit->amount,3).' إلى رصيدكم بنجاح.')
                    : 'تم رفض اعتماد عملية شحن المحفظة. السبب: '.trim((string) $note).' يرجى مراجعة بيانات التحويل أو التواصل مع الدعم الفني.';
                app(CustomerPaymentNotificationService::class)->notify((int) $deposit->customer_id, 'wallet-deposit-'.$deposit->id.'-'.$decision, $message);
            }
            return $deposit;
        });
    }
}
