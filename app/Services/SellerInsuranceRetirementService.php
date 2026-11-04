<?php

namespace App\Services;

use App\Models\SellerBalanceDeposit;
use App\Models\SellerLedgerEntry;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Retire funded vendor insurance without deleting the original financial evidence. */
class SellerInsuranceRetirementService
{
    public function __construct(private readonly SellerLedgerService $ledger) {}

    public function settle(SellerBalanceDeposit $source, string $authorization, ?int $adminId = null): SellerBalanceDeposit
    {
        if (trim($authorization) === '') {
            throw new DomainException('seller_insurance_retirement_authorization_required');
        }
        return DB::transaction(function () use ($source, $authorization, $adminId) {
            $this->ledger->lockSellerFinancialState((int) $source->seller_id);
            $source = SellerBalanceDeposit::query()->lockForUpdate()->findOrFail($source->id);
            if ($source->status !== SellerBalanceDeposit::STATUS_PAID || data_get($source->metadata, 'wallet_target') !== 'insurance') {
                throw new DomainException('seller_insurance_retirement_source_invalid');
            }
            $group = 'vendor-insurance-retirement:balance-deposit:'.$source->id;
            $funding = SellerLedgerEntry::where('group_key', 'seller-balance-deposit:'.$source->id.':credit')->get();
            if ($funding->count() !== 1 || (int) $funding[0]->seller_id !== (int) $source->seller_id
                || $funding[0]->bucket !== SellerLedgerService::ORDER_INSURANCE_CREDIT || $funding[0]->direction !== 'credit'
                || $funding[0]->reference_type !== SellerBalanceDeposit::class || (int) $funding[0]->reference_id !== (int) $source->id
                || (float) $funding[0]->amount !== (float) $source->amount || (float) $source->amount <= 0) {
                throw new DomainException('seller_insurance_retirement_funding_mismatch');
            }
            $existing = SellerLedgerEntry::where('group_key', $group)->get();
            if ($existing->isNotEmpty()) {
                if ($existing->count() !== 2) throw new DomainException('seller_insurance_retirement_incomplete');
                foreach ([SellerLedgerService::ORDER_INSURANCE_CREDIT => 'debit', SellerLedgerService::OPERATING => 'credit'] as $bucket => $direction) {
                    $line = $existing->first(fn ($entry) => $entry->bucket === $bucket && $entry->direction === $direction);
                    if (!$line || (int) $line->seller_id !== (int) $source->seller_id || (float) $line->amount !== (float) $source->amount
                        || $line->reference_type !== SellerBalanceDeposit::class || (int) $line->reference_id !== (int) $source->id) {
                        throw new DomainException('seller_insurance_retirement_identity_mismatch');
                    }
                }
                if (data_get($source->metadata, 'insurance_retirement.group_key') !== $group) {
                    throw new DomainException('seller_insurance_retirement_metadata_mismatch');
                }
                return $source;
            }
            if (data_get($source->metadata, 'insurance_retirement')) throw new DomainException('seller_insurance_retirement_incomplete');
            $this->ledger->assertSufficient((int) $source->seller_id, SellerLedgerService::ORDER_INSURANCE_CREDIT, (float) $source->amount);
            $this->ledger->post(
                sellerId: (int) $source->seller_id,
                eventType: 'vendor_insurance_retirement_to_operating',
                groupKey: $group,
                movements: [
                    ['bucket' => SellerLedgerService::ORDER_INSURANCE_CREDIT, 'direction' => 'debit', 'amount' => $source->amount, 'reporting_category' => 'insurance'],
                    ['bucket' => SellerLedgerService::OPERATING, 'direction' => 'credit', 'amount' => $source->amount, 'reporting_category' => 'operating'],
                ],
                referenceType: SellerBalanceDeposit::class,
                referenceId: (int) $source->id,
                metadata: ['authorization' => $authorization, 'currency_code' => $source->currency_code, 'original_funding_entry_id' => $funding[0]->id],
                createdBy: $adminId,
            );
            $metadata = $source->metadata ?: [];
            $metadata['insurance_retirement'] = ['group_key' => $group, 'destination' => 'operating', 'amount' => $source->amount,
                'currency_code' => $source->currency_code, 'authorization' => $authorization, 'at' => now()->toIso8601String()];
            $source->update(['metadata' => $metadata]);
            return $source->fresh();
        });
    }
}
