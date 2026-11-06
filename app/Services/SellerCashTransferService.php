<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Notification;
use App\Models\SellerLedgerEntry;
use App\Models\SellerWallet;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Transfers only available sales funds; configuration and ledger remain authoritative. */
class SellerCashTransferService
{
    public const SETTING = 'seller_sales_cash_transfer';

    public function settings(bool $lock = false): array
    {
        $query = BusinessSetting::query()->where('type', self::SETTING);
        if ($lock) $query->lockForUpdate();
        // Ambiguous configuration must never silently select an arbitrary fee.
        $rows = $query->limit(2)->get();
        if ($rows->count() !== 1) {
            return ['enabled' => false, 'type' => null, 'value' => null];
        }
        $row = $rows->first();
        $data = $row ? json_decode((string) $row->value, true) : null;
        if (!is_array($data) || !in_array($data['type'] ?? null, ['percent', 'fixed'], true)
            || !is_numeric($data['value'] ?? null) || !is_finite((float) $data['value'])
            || (float) $data['value'] < 0 || (($data['type'] ?? null) === 'percent' && (float) $data['value'] > 100)) {
            return ['enabled' => false, 'type' => null, 'value' => null];
        }
        return ['enabled' => (bool) ($data['enabled'] ?? false), 'type' => $data['type'], 'value' => (float) $data['value']];
    }

    public function quote(float $amount, ?array $settings = null): array
    {
        $settings ??= $this->settings();
        if (!$settings['enabled']) throw new DomainException('sales_cash_transfer_unavailable');
        if (!is_finite($amount) || $amount <= 0) throw new DomainException('sales_cash_transfer_invalid_amount');
        $amount = round($amount, 12);
        // Round the fee in the currency shown to the seller, then record the
        // equivalent in the ledger currency. Displayed amount = fee + net.
        $displayAmount = \App\Utils\Convert::default($amount);
        $displayFee = $settings['type'] === 'percent'
            ? round($displayAmount * $settings['value'] / 100, 2)
            : \App\Utils\Convert::default($settings['value']);
        $fee = round((float) \App\Utils\Convert::usd($displayFee), 12);
        if ($fee >= $amount) throw new DomainException('sales_cash_transfer_fee_too_high');
        return ['amount' => $amount, 'fee' => $fee, 'net' => round($amount - $fee, 12), 'fee_type' => $settings['type'], 'fee_value' => $settings['value']];
    }

    public function transfer(int $sellerId, float $amount, string $requestKey, array $expected, ?callable $validateCurrency = null): array
    {
        return DB::transaction(function () use ($sellerId, $amount, $requestKey, $expected, $validateCurrency) {
            $ledger = app(SellerLedgerService::class);
            $ledger->lockSellerFinancialState($sellerId);
            $group = 'sales-cash-transfer:'.$sellerId.':'.$requestKey;
            $existing = SellerLedgerEntry::query()->where('seller_id', $sellerId)->where('group_key', $group)->where('bucket', SellerLedgerService::AVAILABLE)->first();
            if ($existing) {
                $original = $existing->metadata['quote'] ?? [];
                if (abs((float) ($original['amount'] ?? 0) - $amount) > 0.000000001
                    || ($expected['fee_type'] ?? '') !== ($original['fee_type'] ?? null)
                    || abs((float) ($expected['fee_value'] ?? -1) - (float) ($original['fee_value'] ?? 0)) > 0.000000001
                    || abs((float) ($expected['fee'] ?? -1) - (float) ($original['fee'] ?? 0)) > 0.000000001) throw new DomainException('sales_cash_transfer_retry_mismatch');
                return $original;
            }
            if ($validateCurrency) $validateCurrency();
            if (!SellerLedgerEntry::where('seller_id', $sellerId)->exists()) throw new DomainException('sales_cash_transfer_ledger_unavailable');
            $quote = $this->quote($amount, $this->settings(lock: true));
            if (($expected['fee_type'] ?? '') !== $quote['fee_type'] || abs((float) ($expected['fee_value'] ?? -1) - $quote['fee_value']) > 0.000000001
                || abs((float) ($expected['fee'] ?? -1) - $quote['fee']) > 0.000000001) throw new DomainException('sales_cash_transfer_quote_changed');
            $summary = $ledger->summary($sellerId, initializeLegacy: false);
            if (($summary[SellerLedgerService::AVAILABLE] ?? 0) + 0.000000001 < $quote['amount']) throw new DomainException('sales_cash_transfer_insufficient');
            $wallet = SellerWallet::query()->where('seller_id', $sellerId)->lockForUpdate()->first();
            if (!$wallet || (float) $wallet->total_earning + 0.000000001 < $quote['amount']) throw new DomainException('sales_cash_transfer_reconciliation_required');
            $ledger->post($sellerId, 'sales_to_operating_transfer', $group, [
                ['bucket' => SellerLedgerService::AVAILABLE, 'direction' => 'debit', 'amount' => $quote['amount']],
                ['bucket' => SellerLedgerService::OPERATING, 'direction' => 'credit', 'amount' => $quote['net']],
            ], metadata: ['quote' => $quote], initializeLegacy: false);
            $ledger->withoutWalletSync(function () use ($wallet, $quote) {
                $wallet->total_earning = (float) $wallet->total_earning - $quote['amount'];
                $wallet->save();
            });
            if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'seller_id')) {
                Notification::create(['sent_by' => 'admin', 'sent_to' => 'seller', 'seller_id' => $sellerId,
                    'title' => 'تحويل الرصيد', 'description' => 'تم تحويل مبلغ من رصيد المبيعات إلى رصيد المدفوعات بعد خصم الرسوم.', 'notification_count' => 1, 'status' => 1]);
            }
            return $quote;
        });
    }
}
