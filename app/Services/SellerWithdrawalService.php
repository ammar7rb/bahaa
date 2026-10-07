<?php

namespace App\Services;

use App\Models\SellerWallet;
use App\Models\WithdrawRequest;
use DomainException;
use Illuminate\Support\Facades\DB;

/** One reservation/processing contract shared by web, app and administration. */
class SellerWithdrawalService
{
    public function __construct(private readonly SellerLedgerService $ledger, private readonly SellerWithdrawalMethodService $methods) {}

    public function submit(int $sellerId, int $methodId, float $amount, ?string $requestKey = null): WithdrawRequest
    {
        if (!is_finite($amount) || $amount <= 0) throw new DomainException('invalid_withdraw_request');
        return DB::transaction(function () use ($sellerId, $methodId, $amount, $requestKey) {
            $this->ledger->lockSellerFinancialState($sellerId);
            if ($requestKey && ($existing = WithdrawRequest::where('seller_id', $sellerId)->where('request_key', $requestKey)->first())) {
                if ((int) $existing->vendor_withdraw_method_info_id !== $methodId || abs((float) $existing->amount - $amount) > 0.000001) throw new DomainException('withdrawal_retry_details_mismatch');
                return $existing;
            }
            $method = $this->methods->assertUsable($methodId, $sellerId);
            $this->ledger->assertSufficient($sellerId, SellerLedgerService::AVAILABLE, $amount);
            $snapshot = array_merge($method->method_info ?: [], ['method_name' => $method->method_name, 'method_type' => $method->method_type]);
            $withdrawal = WithdrawRequest::create([
                'seller_id' => $sellerId, 'amount' => $amount, 'approved' => 0, 'request_method_type' => 'vendor',
                'withdrawal_method_id' => $method->withdraw_method_id, 'vendor_withdraw_method_info_id' => $method->id,
                'withdrawal_method_fields' => $snapshot, 'request_key' => $requestKey,
            ]);
            $this->ledger->post($sellerId, 'seller_withdrawal_reserve', 'withdrawal:'.$withdrawal->id.':reserve', [
                ['bucket' => SellerLedgerService::AVAILABLE, 'direction' => 'debit', 'amount' => $amount],
                ['bucket' => SellerLedgerService::PENDING_WITHDRAW, 'direction' => 'credit', 'amount' => $amount],
            ], WithdrawRequest::class, $withdrawal->id);
            $this->syncLegacy($sellerId, -$amount, $amount);
            return $withdrawal;
        });
    }

    public function finish(int $id, int $status, ?int $sellerId = null, ?string $note = null): WithdrawRequest
    {
        if (!in_array($status, [1, 2, 3], true)) throw new DomainException('invalid_withdraw_request');
        return DB::transaction(function () use ($id, $status, $sellerId, $note) {
            // Seller lock is always acquired before the request lock, as in submit.
            $identity = WithdrawRequest::whereKey($id)->whereNull('delivery_man_id')->when($sellerId !== null, fn ($q) => $q->where('seller_id', $sellerId))->firstOrFail();
            $this->ledger->lockSellerFinancialState((int) $identity->seller_id);
            $withdrawal = WithdrawRequest::lockForUpdate()->findOrFail($id);
            if ($withdrawal->approved === $status) return $withdrawal;
            if ($withdrawal->approved !== 0) throw new DomainException('withdrawal_already_processed');
            $amount = (float) $withdrawal->amount;
            $this->ledger->assertSufficient((int) $withdrawal->seller_id, SellerLedgerService::PENDING_WITHDRAW, $amount);
            $this->ledger->post((int) $withdrawal->seller_id, 'seller_withdrawal_processed', 'withdrawal:'.$id.':processed', [
                ['bucket' => SellerLedgerService::PENDING_WITHDRAW, 'direction' => 'debit', 'amount' => $amount],
                ['bucket' => $status === 1 ? SellerLedgerService::WITHDRAWN : SellerLedgerService::AVAILABLE, 'direction' => 'credit', 'amount' => $amount],
            ], WithdrawRequest::class, $id);
            $this->syncLegacy((int) $withdrawal->seller_id, $status === 1 ? 0 : $amount, -$amount, $status === 1 ? $amount : 0);
            $withdrawal->update(['approved' => $status, 'transaction_note' => $note]);
            if (in_array($status, [1, 2], true)) {
                DB::afterCommit(function () use ($withdrawal, $status) {
                    $seller = $withdrawal->seller;
                    if ($seller?->cm_firebase_token) {
                        event(new \App\Events\WithdrawStatusUpdateEvent(key: 'withdraw_request_status_message', type: 'seller', lang: $seller->app_language ?? getDefaultLanguage(), status: $status, fcmToken: $seller->cm_firebase_token));
                    }
                });
            }
            return $withdrawal;
        });
    }

    private function syncLegacy(int $sellerId, float $available, float $pending, float $withdrawn = 0): void
    {
        $this->ledger->withoutWalletSync(function () use ($sellerId, $available, $pending, $withdrawn) {
            $wallet = SellerWallet::firstOrCreate(['seller_id' => $sellerId]);
            $wallet->total_earning = (float) $wallet->total_earning + $available;
            $wallet->pending_withdraw = (float) $wallet->pending_withdraw + $pending;
            $wallet->withdrawn = (float) $wallet->withdrawn + $withdrawn;
            $wallet->save();
        });
    }
}
