<?php

namespace App\Services;

use App\Models\CustomerInsuranceLedgerEntry;
use App\Models\OrderInsurance;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Separate, non-withdrawable balance created only by matured order insurance.
 * It never mutates users.wallet_balance, which remains the platform wallet.
 */
class CustomerInsuranceBalanceService
{
    public function summary(int $customerId): array
    {
        // Historical balances are read-only; no new incentive credits.
        $available = (float) CustomerInsuranceLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->selectRaw('COALESCE(SUM(credit - debit), 0) as balance')
            ->value('balance');

        $held = (float) OrderInsurance::query()
            ->where('customer_id', $customerId)
            ->where('status', 'held')
            ->sum('amount');

        $nextMaturity = OrderInsurance::query()
            ->where('customer_id', $customerId)
            ->where('status', 'held')
            ->whereNotNull('matures_at')
            ->orderBy('matures_at')
            ->value('matures_at');

        // Older refunds use the v3 balance table; deposits and matured order
        // insurance use the immutable ledger. Both remain insurance-only.
        $refundBalance = \Illuminate\Support\Facades\Schema::hasTable('customer_insurance_balances')
            ? DB::table('customer_insurance_balances')->where('customer_id', $customerId)->first() : null;
        return [
            'available_balance' => round(max(0, $available) + (float) ($refundBalance->available_amount ?? 0), 2),
            'held_balance' => round(max(0, $held) + (float) ($refundBalance->held_amount ?? 0), 2),
            'next_maturity_at' => $nextMaturity,
            'withdrawable' => false,
            'allowed_uses' => ['insurance_payment'],
        ];
    }

    public function recordHold(OrderInsurance $insurance): void
    {
        throw new DomainException('تم إيقاف تأمين العملاء.');
    }

    public function releaseDue(?int $limit = null): int
    {
        return 0; // Historical funds remain archived for reconciliation.
    }

    /** @deprecated Insurance balance can no longer pay for products. */
    public function reserveForPurchase(int $customerId, float $requestedAmount, string $reference, array $metadata = []): float
    {
        throw new DomainException('customer_insurance_balance_is_restricted_to_insurance_payment');
    }

    public function debitForInsurance(OrderInsurance $insurance, string $reference, array $metadata = []): float
    {
        throw new DomainException('تم إيقاف تأمين العملاء.');
    }

    public function debitForGovernance(int $customerId, float $amount, string $reference, array $metadata = []): float
    {
        throw new DomainException('تم إيقاف تأمين العملاء. تتم مطابقة المبالغ القديمة من الأرشيف.');
    }

    public function creditFromGovernance(int $customerId, float $amount, string $reference, array $metadata = []): float
    {
        throw new DomainException('تم إيقاف تأمين العملاء. تتم مطابقة المبالغ القديمة من الأرشيف.');
    }


    public function reversePurchaseDebit(int $customerId, string $debitReference, string $reason): void
    {
        // Historical reversal only. New purchase debits are forbidden above.
        DB::transaction(function () use ($customerId, $debitReference, $reason): void {
            $debit = CustomerInsuranceLedgerEntry::query()
                ->where('customer_id', $customerId)
                ->where('reference', $debitReference)
                ->lockForUpdate()
                ->first();
            if (!$debit || $debit->debit <= 0) {
                return;
            }

            CustomerInsuranceLedgerEntry::query()->firstOrCreate(
                ['reference' => $debitReference . '-reversal'],
                [
                    'customer_id' => $customerId,
                    'entry_type' => 'purchase_reversal',
                    'credit' => $debit->debit,
                    'debit' => 0,
                    'metadata' => ['reason' => $reason, 'reverses' => $debitReference],
                ]
            );
        });
    }

    public function recentEntries(int $customerId, int $limit = 20): Collection
    {
        return CustomerInsuranceLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
