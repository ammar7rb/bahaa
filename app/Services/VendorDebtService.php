<?php

namespace App\Services;

use App\Models\SellerBalanceDeposit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Internal financial primitive. The decision/slot service validates its business trigger before assessing a charge. */
class VendorDebtService
{
    public function __construct(private readonly SellerLedgerService $ledger) {}

    public function available(): bool
    {
        foreach (['vendor_debt_charges','vendor_deposit_allocations','vendor_debt_repayments','vendor_debt_events'] as $table) {
            if (!Schema::hasTable($table)) return false;
        }
        return true;
    }

    private function amount(mixed $value): BigDecimal
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) throw new DomainException('المبلغ المالي غير صحيح.');
        $amount = BigDecimal::of((string) $value)->toScale(8, RoundingMode::HalfUp);
        if ($amount->isGreaterThan('999999999999.99999999')) throw new DomainException('المبلغ يتجاوز الحد المالي.');
        return $amount;
    }

    private function canonical(array $data): array
    {
        if (!array_is_list($data)) ksort($data);
        foreach ($data as &$value) if (is_array($value)) $value = $this->canonical($value);
        return $data;
    }

    public function outstanding(int $sellerId): string
    {
        return $this->available() ? (string) $this->amount(DB::table('vendor_debt_charges')->where('seller_id', $sellerId)->sum('outstanding')) : '0.00000000';
    }

    /** An administrative debt records the full liability; it does not silently spend wallet money. */
    public function createAdministrativeDebt(int $sellerId, mixed $value, string $reason, string $requestKey, int $adminId): object
    {
        if (!$this->available()) throw new DomainException('دفتر ديون التاجر غير مهيأ.');
        $amount = $this->amount($value);
        $reason = trim($reason);
        if ($amount->isZero() || $reason === '' || strlen($reason) > 2000) throw new DomainException('المبلغ والسبب مطلوبان.');
        $key = hash('sha256', 'admin-manual|'.$sellerId.'|'.$requestKey);
        $fingerprint = hash('sha256', json_encode([$sellerId, (string) $amount, $reason], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($sellerId, $amount, $reason, $adminId, $key, $fingerprint, $requestKey) {
            $this->ledger->lockSellerFinancialState($sellerId);
            if ($existing = DB::table('vendor_debt_charges')->where('idempotency_key', $key)->first()) {
                if (!hash_equals($existing->fingerprint, $fingerprint)) throw new DomainException('مفتاح الطلب مستخدم لمديونية مختلفة.');
                return $existing;
            }
            $id = DB::table('vendor_debt_charges')->insertGetId([
                'seller_id' => $sellerId, 'kind' => 'admin_manual', 'source_type' => 'admin_manual',
                'source_id' => $requestKey, 'idempotency_key' => $key, 'fingerprint' => $fingerprint,
                'amount' => (string) $amount, 'wallet_collected' => '0', 'debt_assessed' => (string) $amount,
                'outstanding' => (string) $amount, 'rule_snapshot' => json_encode(['reason' => $reason], JSON_THROW_ON_ERROR),
                'created_by' => $adminId, 'assessed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($sellerId, 'charge_assessed', $amount, $id, null, 'charge:'.$id.':assessed');
            return DB::table('vendor_debt_charges')->find($id);
        }, 3);
    }

    private function event(int $sellerId, string $type, BigDecimal $amount, ?int $chargeId, ?int $depositId, string $key): void
    {
        DB::table('vendor_debt_events')->insert([
            'seller_id' => $sellerId, 'event_type' => $type, 'amount' => (string) $amount,
            'charge_id' => $chargeId, 'deposit_id' => $depositId, 'event_key' => hash('sha256', $key), 'created_at' => now(),
        ]);
    }

    public function assess(int $sellerId, string $kind, string $sourceType, string $sourceId, string $requestKey, mixed $value, array $rule, ?int $adminId = null): object
    {
        if (!$this->available()) throw new DomainException('دفتر ديون التاجر غير مهيأ.');
        if (!in_array($kind, ['cancellation_penalty', 'additional_slot'], true) || trim($sourceType) === '' || strlen($sourceType) > 40
            || trim($sourceId) === '' || strlen($sourceId) > 64 || trim($requestKey) === '' || strlen($requestKey) > 160 || !$rule) {
            throw new DomainException('مرجع الاستحقاق وقاعدته مطلوبان.');
        }
        $amount = $this->amount($value);
        if ($amount->isZero()) throw new DomainException('قيمة الاستحقاق يجب أن تكون موجبة.');
        $fingerprint = hash('sha256', json_encode($this->canonical([$kind, $sourceType, $sourceId, (string) $amount, $rule]), JSON_THROW_ON_ERROR));
        $key = hash('sha256', $sellerId.'|'.$requestKey);
        return DB::transaction(function () use ($sellerId, $kind, $sourceType, $sourceId, $amount, $rule, $adminId, $fingerprint, $key) {
            $this->ledger->lockSellerFinancialState($sellerId);
            $existing = DB::table('vendor_debt_charges')->where('seller_id', $sellerId)->where(fn ($query) => $query->where('idempotency_key', $key)
                ->orWhere(fn ($source) => $source->where('kind', $kind)->where('source_type', $sourceType)->where('source_id', $sourceId)))->first();
            if ($existing) {
                if (!hash_equals($existing->fingerprint, $fingerprint)) throw new DomainException('المرجع المالي سبق استخدامه بشروط مختلفة.');
                return $existing;
            }
            $available = $this->amount(max(0, $this->ledger->summary($sellerId, initializeLegacy: false)[SellerLedgerService::OPERATING] ?? 0));
            $paid = $available->isLessThan($amount) ? $available : $amount;
            $debt = $amount->minus($paid);
            $id = DB::table('vendor_debt_charges')->insertGetId([
                'seller_id' => $sellerId, 'kind' => $kind, 'source_type' => $sourceType, 'source_id' => $sourceId,
                'idempotency_key' => $key, 'fingerprint' => $fingerprint, 'amount' => (string) $amount,
                'wallet_collected' => (string) $paid, 'debt_assessed' => (string) $debt, 'outstanding' => (string) $debt,
                'rule_snapshot' => json_encode($rule, JSON_THROW_ON_ERROR), 'created_by' => $adminId,
                'assessed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($sellerId, 'charge_assessed', $amount, $id, null, 'charge:'.$id.':assessed');
            if (!$paid->isZero()) {
                $this->ledger->post($sellerId, 'vendor_charge_collected', 'vendor-charge:'.$id.':wallet',
                    [['bucket' => SellerLedgerService::OPERATING, 'direction' => 'debit', 'amount' => (float) (string) $paid]],
                    'vendor_debt_charge', $id, ['charge_id' => $id, 'kind' => $kind], $adminId);
                DB::table('vendor_debt_repayments')->insert([
                    'seller_id' => $sellerId, 'charge_id' => $id, 'collection_type' => 'wallet', 'amount' => (string) $paid,
                    'event_key' => hash('sha256', 'charge:'.$id.':wallet'), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->event($sellerId, 'wallet_collection', $paid, $id, null, 'charge:'.$id.':wallet-event');
            }
            return DB::table('vendor_debt_charges')->find($id);
        }, 3);
    }

    /** Read-only preview; approval always recalculates under the vendor financial lock. */
    public function preview(int $sellerId, mixed $gross): array
    {
        $gross = $this->amount($gross); $debt = $this->amount($this->outstanding($sellerId));
        $recovered = $gross->isLessThan($debt) ? $gross : $debt;
        $references = []; $remaining = $recovered;
        if ($this->available()) foreach (DB::table('vendor_debt_charges')->where('seller_id', $sellerId)->where('outstanding', '>', 0)->orderBy('assessed_at')->orderBy('id')->get() as $charge) {
            if ($remaining->isZero()) break;
            $owed = $this->amount($charge->outstanding); $paid = $remaining->isLessThan($owed) ? $remaining : $owed;
            $references[] = ['charge_id' => $charge->id, 'kind' => $charge->kind, 'amount' => (string) $paid];
            $remaining = $remaining->minus($paid);
        }
        return ['gross_amount' => (string) $gross, 'outstanding_debt' => (string) $debt, 'debt_recovered' => (string) $recovered,
            'net_wallet_credit' => (string) $gross->minus($recovered), 'remaining_debt' => (string) $debt->minus($recovered), 'references' => $references];
    }

    /** Called only by the confirmed deposit processor, inside its transaction, before marking paid. */
    public function allocateDeposit(SellerBalanceDeposit $deposit): array
    {
        if (!$this->available()) return $this->preview($deposit->seller_id, $deposit->amount);
        return DB::transaction(function () use ($deposit) {
            $this->ledger->lockSellerFinancialState($deposit->seller_id);
            $deposit = SellerBalanceDeposit::lockForUpdate()->findOrFail($deposit->id);
            if ($receipt = DB::table('vendor_deposit_allocations')->where('deposit_id', $deposit->id)->first()) return (array) $receipt;
            if ($deposit->status !== 'pending' || data_get($deposit->metadata, 'wallet_target', 'operating') !== 'operating') throw new DomainException('الإيداع غير مؤهل لتوزيع الدين.');
            $gross = $this->amount($deposit->amount); $remaining = $gross;
            $charges = DB::table('vendor_debt_charges')->where('seller_id', $deposit->seller_id)->where('outstanding', '>', 0)
                ->orderBy('assessed_at')->orderBy('id')->lockForUpdate()->get();
            if ($charges->isNotEmpty() && strtoupper($deposit->currency_code) !== 'EGP') throw new DomainException('يلزم تأكيد قيمة الإيداع بالجنيه قبل سداد الدين.');
            foreach ($charges as $charge) {
                if ($remaining->isZero()) break;
                $owed = $this->amount($charge->outstanding); $paid = $remaining->isLessThan($owed) ? $remaining : $owed;
                DB::table('vendor_debt_repayments')->insert([
                    'seller_id' => $deposit->seller_id, 'charge_id' => $charge->id, 'deposit_id' => $deposit->id,
                    'collection_type' => 'deposit', 'amount' => (string) $paid,
                    'event_key' => hash('sha256', 'deposit:'.$deposit->id.':charge:'.$charge->id), 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('vendor_debt_charges')->where('id', $charge->id)->update(['outstanding' => (string) $owed->minus($paid), 'updated_at' => now()]);
                $this->event($deposit->seller_id, 'deposit_recovery', $paid, $charge->id, $deposit->id, 'deposit:'.$deposit->id.':recovery:'.$charge->id);
                $remaining = $remaining->minus($paid);
            }
            $result = ['deposit_id' => $deposit->id, 'seller_id' => $deposit->seller_id, 'gross_amount' => (string) $gross,
                'debt_recovered' => (string) $gross->minus($remaining), 'net_wallet_credit' => (string) $remaining,
                'remaining_debt' => $this->outstanding($deposit->seller_id), 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()];
            DB::table('vendor_deposit_allocations')->insert($result);
            if (!$remaining->isZero()) $this->ledger->post($deposit->seller_id, 'operating_deposit', 'seller-balance-deposit:'.$deposit->id.':credit',
                [['bucket' => SellerLedgerService::OPERATING, 'direction' => 'credit', 'amount' => (float) (string) $remaining]],
                SellerBalanceDeposit::class, $deposit->id, ['gross_amount' => (string) $gross, 'debt_recovered' => $result['debt_recovered']], $deposit->reviewed_by_admin_id);
            $this->event($deposit->seller_id, 'deposit_allocated', $gross, null, $deposit->id, 'deposit:'.$deposit->id.':allocated');
            return $result;
        }, 3);
    }

    public function reverseDeposit(SellerBalanceDeposit $deposit, int $adminId, string $reason): bool
    {
        if (!$this->available()) return false;
        $allocation = DB::table('vendor_deposit_allocations')->where('deposit_id', $deposit->id)->lockForUpdate()->first();
        if (!$allocation) return false;
        if ($allocation->reversed_at) return true;
        $net = $this->amount($allocation->net_wallet_credit);
        if (!$net->isZero()) {
            if ($this->amount(max(0, $this->ledger->summary($deposit->seller_id, initializeLegacy: false)[SellerLedgerService::OPERATING] ?? 0))->isLessThan($net)) {
                throw new DomainException('رصيد المدفوعات لا يكفي لعكس صافي الإيداع؛ لم يتغير الدين أو الرصيد.');
            }
            $this->ledger->assertSufficient($deposit->seller_id, SellerLedgerService::OPERATING, (float) (string) $net);
            $this->ledger->post($deposit->seller_id, 'operating_deposit_reversal', 'seller-balance-deposit:'.$deposit->id.':reversal',
                [['bucket' => SellerLedgerService::OPERATING, 'direction' => 'debit', 'amount' => (float) (string) $net]],
                SellerBalanceDeposit::class, $deposit->id, ['reason' => $reason, 'debt_reinstated' => $allocation->debt_recovered], $adminId);
        }
        foreach (DB::table('vendor_debt_repayments')->where('deposit_id', $deposit->id)->whereNull('reversed_at')->lockForUpdate()->get() as $repayment) {
            $charge = DB::table('vendor_debt_charges')->where('id', $repayment->charge_id)->lockForUpdate()->first();
            $restored = $this->amount($charge->outstanding)->plus($repayment->amount);
            if ($restored->isGreaterThan($charge->debt_assessed)) throw new DomainException('عكس التوزيع يتطلب مراجعة مالية.');
            DB::table('vendor_debt_charges')->where('id', $charge->id)->update(['outstanding' => (string) $restored, 'updated_at' => now()]);
            DB::table('vendor_debt_repayments')->where('id', $repayment->id)->update(['reversed_at' => now(), 'updated_at' => now()]);
        }
        DB::table('vendor_deposit_allocations')->where('id', $allocation->id)->update(['reversed_at' => now(), 'updated_at' => now()]);
        $this->event($deposit->seller_id, 'deposit_allocation_reversed', $this->amount($allocation->gross_amount), null, $deposit->id, 'deposit:'.$deposit->id.':allocation-reversed');
        return true;
    }

    public function depositReceipt(int $depositId): ?array
    {
        if (!$this->available()) return null;
        $receipt = DB::table('vendor_deposit_allocations')->where('deposit_id', $depositId)->first();
        if (!$receipt) return null;
        return (array) $receipt + ['references' => DB::table('vendor_debt_repayments as r')->join('vendor_debt_charges as c', 'c.id', '=', 'r.charge_id')->where('r.deposit_id', $depositId)
            ->get(['r.charge_id', 'r.amount', 'r.reversed_at', 'c.kind', 'c.source_type', 'c.source_id'])->map(fn ($row) => (array) $row)->all()];
    }

    public function presentation(int $sellerId): array
    {
        $charges = $this->available() ? DB::table('vendor_debt_charges')->where('seller_id', $sellerId)->latest('id')->limit(50)->get()
            ->map(fn ($row) => ['id' => $row->id, 'kind' => $row->kind, 'amount' => $row->amount, 'wallet_collected' => $row->wallet_collected,
                'debt_assessed' => $row->debt_assessed, 'outstanding' => $row->outstanding, 'reference' => 'DEBT-'.$row->id,
                'assessed_at' => $row->assessed_at, 'rule' => array_intersect_key(json_decode($row->rule_snapshot, true) ?: [], array_flip(['mode','fixed_amount','percentage','base_amount','reason_code']))])->all() : [];
        return ['outstanding_debt' => $this->outstanding($sellerId), 'currency' => 'EGP', 'repayment_order' => 'oldest_first', 'charges' => $charges];
    }
}
