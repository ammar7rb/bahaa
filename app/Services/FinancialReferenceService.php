<?php

namespace App\Services;

use App\Models\CustomerInsuranceLedgerEntry;
use App\Models\SellerLedgerEntry;
use App\Models\SellerBalanceDeposit;
use App\Models\WithdrawRequest;

/** Display identities only; never replace gateway references or ledger hash inputs. */
class FinancialReferenceService
{
    public static function sellerEntry(SellerLedgerEntry $entry): string
    {
        if ($entry->reference_type === SellerBalanceDeposit::class) {
            return AdministrativeReferenceService::format($entry->bucket === SellerLedgerService::ORDER_INSURANCE_CREDIT ? 'seller_insurance_deposit' : 'seller_balance_deposit', (int) $entry->reference_id);
        }
        if ($entry->reference_type === WithdrawRequest::class) {
            return AdministrativeReferenceService::format('seller_payment', (int) $entry->reference_id);
        }
        if ($entry->direction === 'credit' && $entry->bucket === SellerLedgerService::ORDER_INSURANCE_CREDIT && in_array($entry->event_type, ['seller_order_insurance_credit_released', 'seller_insurance_release', 'seller_insurance_reverse_confiscation'], true)) {
            return AdministrativeReferenceService::format('seller_insurance_payment', (int) $entry->id);
        }
        return 'WLT-' . $entry->id;
    }

    public static function customerInsuranceEntry(CustomerInsuranceLedgerEntry $entry): string
    {
        if ($entry->entry_type === 'deposit') {
            return AdministrativeReferenceService::format('customer_insurance_deposit', (int) (data_get($entry->metadata, 'deposit_id') ?: $entry->id));
        }
        if ((float) $entry->credit > 0 && in_array($entry->entry_type, ['matured_credit', 'admin_governance_credit', 'insurance_payment_reversal', 'purchase_reversal'], true)) {
            return AdministrativeReferenceService::format('customer_insurance_payment', (int) $entry->id);
        }
        return (string) $entry->reference;
    }
}
