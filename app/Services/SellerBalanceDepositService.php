<?php

namespace App\Services;

use App\Models\Seller;
use App\Models\SellerBalanceDeposit;
use DomainException;
use Illuminate\Support\Facades\DB;

class SellerBalanceDepositService
{
    public function __construct(private readonly SellerLedgerService $ledger)
    {
    }

    /** A verified external receipt, not an unexplained balance adjustment. */
    public function confirmAdministrativePayment(Seller $seller, string $amount, string $reference, string $requestKey, int $adminId, string $reason): SellerBalanceDeposit
    {
        $reference = trim($reference);
        if ($reference === '' || trim($reason) === '' || !is_numeric($amount) || (float) $amount <= 0) {
            throw new DomainException('مبلغ الشحن ومرجع التحويل والسبب مطلوبة.');
        }
        return DB::transaction(function () use ($seller, $amount, $reference, $requestKey, $adminId, $reason) {
            $this->ledger->lockSellerFinancialState((int) $seller->id);
            $key = 'admin-verified:'.hash('sha256', $seller->id.'|'.$requestKey);
            if ($existing = SellerBalanceDeposit::where('idempotency_key', $key)->first()) {
                if ($existing->transaction_reference !== $reference || (int) round($existing->amount * 100) !== (int) round((float) $amount * 100)) {
                    throw new DomainException('مفتاح الطلب مستخدم لشحن مختلف.');
                }
                if ($existing->status !== SellerBalanceDeposit::STATUS_PAID) throw new DomainException('هذا الطلب ليس شحنًا مؤكدًا حاليًا؛ راجع سجل الإيداع قبل إعادة الإرسال.');
                return $existing;
            }
            if (SellerBalanceDeposit::where('transaction_reference', $reference)->exists()) {
                throw new DomainException('مرجع التحويل مستخدم من قبل.');
            }
            $deposit = SellerBalanceDeposit::create([
                'seller_id' => $seller->id, 'amount' => $amount, 'currency_code' => 'EGP',
                'source' => SellerBalanceDeposit::SOURCE_OFFLINE, 'status' => SellerBalanceDeposit::STATUS_PENDING,
                'payment_method' => 'admin_verified_external', 'transaction_reference' => $reference,
                'payment_note' => $reason, 'metadata' => ['wallet_target' => 'operating', 'admin_assisted' => true],
                'idempotency_key' => $key,
            ]);
            return $this->markPaid($deposit, ['payment_method' => 'admin_verified_external',
                'transaction_id' => $reference, 'payment_amount' => $amount, 'currency_code' => 'EGP'], $adminId, $reason);
        }, 3);
    }

    public function createOffline(Seller $seller, array $data): SellerBalanceDeposit
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new DomainException('seller_balance_deposit_amount_invalid');
        }
        $walletTarget = $this->walletTarget($data['wallet_target'] ?? 'operating');
        unset($data['wallet_target']);
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $metadata['wallet_target'] = $walletTarget;
        return DB::transaction(function () use ($seller, $data, $amount, $metadata) {
        $deposit = SellerBalanceDeposit::create(array_merge($data, [
            'seller_id' => $seller->id,
            'amount' => $amount,
            'source' => SellerBalanceDeposit::SOURCE_OFFLINE,
            'status' => SellerBalanceDeposit::STATUS_PENDING,
            'metadata' => $metadata,
            'idempotency_key' => 'offline-deposit:'.sha1($seller->id.'|'.microtime(true).'|'.bin2hex(random_bytes(8))),
        ]));
        $this->notifyDeposit($deposit, 'pending', 'تم استلام طلب الإيداع، وجارٍ التحقق من عملية الدفع.');
        return $deposit;
        });
    }

    public function createDigital(Seller $seller, float $amount, string $currencyCode, string $paymentMethod, string $walletTarget = 'operating'): SellerBalanceDeposit
    {
        if ($amount <= 0) {
            throw new DomainException('seller_balance_deposit_amount_invalid');
        }
        $walletTarget = $this->walletTarget($walletTarget);
        return SellerBalanceDeposit::create([
            'seller_id' => $seller->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'source' => SellerBalanceDeposit::SOURCE_DIGITAL,
            'status' => SellerBalanceDeposit::STATUS_PENDING,
            'payment_method' => $paymentMethod,
            'metadata' => ['wallet_target' => $walletTarget],
            'idempotency_key' => 'digital-deposit:'.sha1($seller->id.'|'.microtime(true).'|'.bin2hex(random_bytes(8))),
        ]);
    }

    public function attachPaymentRequest(SellerBalanceDeposit $deposit, string $paymentRequestId): SellerBalanceDeposit
    {
        $deposit->update(['payment_request_id' => $paymentRequestId]);
        return $deposit->fresh();
    }

    public function markPaid(SellerBalanceDeposit $deposit, array $paymentData = [], ?int $adminId = null, ?string $note = null): SellerBalanceDeposit
    {
        return DB::transaction(function () use ($deposit, $paymentData, $adminId, $note) {
            $this->ledger->lockSellerFinancialState((int) $deposit->seller_id);
            $deposit = SellerBalanceDeposit::query()->lockForUpdate()->findOrFail($deposit->id);
            if ($deposit->status === SellerBalanceDeposit::STATUS_PAID) {
                return $deposit;
            }
            if (!in_array($deposit->status, [SellerBalanceDeposit::STATUS_PENDING], true)) {
                throw new DomainException('seller_balance_deposit_cannot_be_approved');
            }

            $paymentReference = $paymentData['transaction_id'] ?? $paymentData['reference'] ?? $deposit->payment_request_id;
            $walletTarget = $this->walletTarget(data_get($deposit->metadata, 'wallet_target', 'operating'));
            $bucket = $walletTarget === 'insurance'
                ? SellerLedgerService::ORDER_INSURANCE_CREDIT
                : SellerLedgerService::OPERATING;
            $eventType = $walletTarget === 'insurance'
                ? 'seller_insurance_credit_deposit'
                : 'operating_deposit';
            $debtAllocation = null;
            if ($bucket === SellerLedgerService::OPERATING && app(VendorDebtService::class)->available()) {
                $debtAllocation = app(VendorDebtService::class)->allocateDeposit($deposit);
            } else {
            $this->ledger->post(
                sellerId: (int) $deposit->seller_id,
                eventType: $eventType,
                groupKey: 'seller-balance-deposit:'.$deposit->id.':credit',
                movements: [['bucket' => $bucket, 'direction' => 'credit', 'amount' => (float) $deposit->amount]],
                referenceType: SellerBalanceDeposit::class,
                referenceId: (int) $deposit->id,
                metadata: ['source' => $deposit->source, 'payment_reference' => $paymentReference],
                createdBy: $adminId,
            );
            }

            $metadata = $deposit->metadata ?: [];
            if ($debtAllocation) $metadata['debt_allocation'] = array_intersect_key($debtAllocation, array_flip(['gross_amount','debt_recovered','net_wallet_credit','remaining_debt']));
            $metadata['payment'] = array_merge($paymentData, ['paid_at' => now()->toDateTimeString()]);
            $deposit->update([
                'status' => SellerBalanceDeposit::STATUS_PAID,
                'payment_method' => $paymentData['payment_method'] ?? $deposit->payment_method,
                'transaction_reference' => $paymentReference,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => $adminId ? now() : $deposit->reviewed_at,
                'review_note' => $note ?: $deposit->review_note,
                'paid_at' => now(),
                'metadata' => $metadata,
            ]);
            if (\Illuminate\Support\Facades\Schema::hasColumn('notifications', 'customer_event_key')) {
                $balance = $this->ledger->summary((int) $deposit->seller_id)[$bucket] ?? 0;
                $formatted = number_format(\App\Utils\Convert::default($balance), 2).' '.getCurrencyCode(type: 'default');
                $allocationMessage = $debtAllocation ? 'تم تأكيد الإيداع: إجمالي '.number_format((float)$debtAllocation['gross_amount'],2).'، سداد دين '.number_format((float)$debtAllocation['debt_recovered'],2).'، وصافي مدفوعات '.number_format((float)$debtAllocation['net_wallet_credit'],2).' ج.م. ' : 'تمت إضافة الرصيد بنجاح. ';
                $this->notifyDeposit($deposit, 'approve', $allocationMessage.'رصيدك الحالي: '.$formatted);
            }
            return $deposit->fresh();
        }, 3);
    }

    public function reject(SellerBalanceDeposit $deposit, int $adminId, string $reason): SellerBalanceDeposit
    {
        if (trim($reason) === '') {
            throw new DomainException('seller_balance_deposit_rejection_reason_required');
        }
        return DB::transaction(function () use ($deposit, $adminId, $reason) {
        $this->ledger->lockSellerFinancialState((int) $deposit->seller_id);
            $deposit = SellerBalanceDeposit::query()->lockForUpdate()->findOrFail($deposit->id);
        if ($deposit->status === SellerBalanceDeposit::STATUS_REJECTED && $deposit->source === SellerBalanceDeposit::SOURCE_OFFLINE) return $deposit;
        if ($deposit->status !== SellerBalanceDeposit::STATUS_PENDING || $deposit->source !== SellerBalanceDeposit::SOURCE_OFFLINE) {
            throw new DomainException('seller_balance_deposit_cannot_be_rejected');
        }
        $deposit->update([
            'status' => SellerBalanceDeposit::STATUS_REJECTED,
            'rejection_reason' => trim($reason),
            'reviewed_by_admin_id' => $adminId,
            'reviewed_at' => now(),
        ]);
        $this->notifyDeposit($deposit, 'reject', 'عملية الإيداع فشلت، برجاء التحقق. السبب: '.trim($reason));
        return $deposit->fresh();
        });
    }

    private function notifyDeposit(SellerBalanceDeposit $deposit, string $state, string $message): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('notifications', 'customer_event_key')) return;
        DB::table('notifications')->insertOrIgnore([
            'seller_id' => $deposit->seller_id, 'customer_event_key' => 'wallet-deposit-vendor-'.$deposit->id.'-'.$state,
            'sent_by' => 'admin', 'sent_to' => 'seller', 'title' => 'تحديث إيداع المحفظة', 'description' => $message,
            'status' => 1, 'notification_count' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function reverse(SellerBalanceDeposit $deposit, int $adminId, string $reason): SellerBalanceDeposit
    {
        if (trim($reason) === '') {
            throw new DomainException('seller_balance_deposit_reversal_reason_required');
        }
        return DB::transaction(function () use ($deposit, $adminId, $reason) {
            $this->ledger->lockSellerFinancialState((int) $deposit->seller_id);
            $deposit = SellerBalanceDeposit::query()->lockForUpdate()->findOrFail($deposit->id);
            if ($deposit->status === SellerBalanceDeposit::STATUS_REVERSED && app(VendorDebtService::class)->depositReceipt($deposit->id)) return $deposit;
            if ($deposit->status !== SellerBalanceDeposit::STATUS_PAID) {
                throw new DomainException('seller_balance_deposit_cannot_be_reversed');
            }
            $walletTarget = $this->walletTarget(data_get($deposit->metadata, 'wallet_target', 'operating'));
            $bucket = $walletTarget === 'insurance'
                ? SellerLedgerService::ORDER_INSURANCE_CREDIT
                : SellerLedgerService::OPERATING;
            $eventType = $walletTarget === 'insurance'
                ? 'seller_insurance_credit_deposit_reversal'
                : 'operating_deposit_reversal';
            $debtReversal = $bucket === SellerLedgerService::OPERATING && app(VendorDebtService::class)->reverseDeposit($deposit, $adminId, trim($reason));
            if (!$debtReversal) {
            $this->ledger->assertSufficient((int) $deposit->seller_id, $bucket, (float) $deposit->amount);
            $this->ledger->post(
                sellerId: (int) $deposit->seller_id,
                eventType: $eventType,
                groupKey: 'seller-balance-deposit:'.$deposit->id.':reversal',
                movements: [['bucket' => $bucket, 'direction' => 'debit', 'amount' => (float) $deposit->amount]],
                referenceType: SellerBalanceDeposit::class,
                referenceId: (int) $deposit->id,
                metadata: ['reason' => trim($reason)],
                createdBy: $adminId,
            );
            }
            $metadata = $deposit->metadata ?: [];
            $metadata['reversal'] = ['reason' => trim($reason), 'admin_id' => $adminId, 'at' => now()->toDateTimeString()];
            $deposit->update(['status' => SellerBalanceDeposit::STATUS_REVERSED, 'reversed_at' => now(), 'reversed_by_admin_id' => $adminId, 'review_note' => trim($reason), 'metadata' => $metadata]);
            return $deposit->fresh();
        });
    }

    private function walletTarget(mixed $target): string
    {
        $target = (string) $target;
        if (! in_array($target, ['operating', 'insurance'], true)) {
            throw new DomainException('seller_balance_deposit_target_invalid');
        }
        return $target;
    }
}

