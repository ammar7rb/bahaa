<?php

namespace App\Console\Commands;

use App\Models\SellerBalanceDeposit;
use App\Models\SellerLedgerEntry;
use App\Models\SellerOrderInsurance;
use App\Services\SellerBalanceDepositService;
use App\Services\SellerInsuranceRetirementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RetireVendorInsurance extends Command
{
    protected $signature = 'vendor:retire-insurance {manifest : Reviewed settlement JSON} {--apply : Apply atomically; otherwise read only}';
    protected $description = 'Preview or apply explicitly authorized vendor insurance settlements';

    public function handle(): int
    {
        $plan = json_decode(file_get_contents($this->argument('manifest')), true, 512, JSON_THROW_ON_ERROR);
        if (($plan['destination'] ?? null) !== 'operating' || empty($plan['authorization']) || empty($plan['deposits'])) {
            throw new RuntimeException('Invalid approved settlement manifest');
        }
        $report = DB::transaction(function () use ($plan) {
            $report = [];
            $seen = [];
            foreach ($plan['deposits'] as $item) {
                if (isset($seen[$item['id']])) throw new RuntimeException('Duplicate settlement source');
                $seen[$item['id']] = true;
                $source = SellerBalanceDeposit::query()->findOrFail($item['id']);
                $settled = data_get($source->metadata, 'insurance_retirement.destination') === 'operating';
                if ((int) $source->seller_id !== (int) $item['seller_id'] || (float) $source->amount !== (float) $item['amount']
                    || $source->currency_code !== $item['currency_code'] || data_get($source->metadata, 'wallet_target') !== 'insurance'
                    || (!$settled && $source->status !== $item['expected_status']) || ($settled && $source->status !== 'paid')) {
                    throw new RuntimeException('Settlement precondition changed for deposit '.$item['id']);
                }
                if ($source->status === 'pending' && empty($item['receipt_confirmed'])) throw new RuntimeException('Receipt confirmation required');
                $before = $this->balances((int) $source->seller_id);
                $funding = SellerLedgerEntry::where('group_key', 'seller-balance-deposit:'.$source->id.':credit')->get();
                if ($source->status === 'pending' && $funding->isNotEmpty()) throw new RuntimeException('Pending source already has funding entries');
                if ($source->status === 'paid' && ($funding->count() !== 1 || (int) $funding[0]->seller_id !== (int) $source->seller_id
                    || $funding[0]->bucket !== 'order_insurance_credit' || $funding[0]->direction !== 'credit'
                    || (float) $funding[0]->amount !== (float) $source->amount)) throw new RuntimeException('Paid source funding mismatch');
                if ($source->status === 'paid' && !$settled && (float) ($before['order_insurance_credit'] ?? 0) < (float) $source->amount) {
                    throw new RuntimeException('Insufficient historical insurance balance');
                }
                if ($this->option('apply')) {
                    // Real seller lock precedes the refreshed source checks inside the services.
                    app(\App\Services\SellerLedgerService::class)->lockSellerFinancialState((int) $source->seller_id);
                    $locked = SellerBalanceDeposit::query()->lockForUpdate()->findOrFail($source->id);
                    if ((int) $locked->seller_id !== (int) $source->seller_id || $locked->status !== $source->status || (float) $locked->amount !== (float) $source->amount
                        || $locked->currency_code !== $source->currency_code || $locked->metadata !== $source->metadata) {
                        throw new RuntimeException('Concurrent financial change; rerun preview');
                    }
                    if ($source->status === 'pending') {
                        $source = app(SellerBalanceDepositService::class)->markPaid($source,
                            ['payment_method' => $source->payment_method, 'receipt_confirmation' => $plan['authorization']],
                            null, $plan['authorization']);
                    }
                    app(SellerInsuranceRetirementService::class)->settle($source, $plan['authorization']);
                }
                $report[] = ['deposit_id' => $source->id, 'seller_id' => $source->seller_id, 'amount' => $source->amount,
                    'currency_code' => $source->currency_code, 'already_settled' => $settled, 'before' => $before,
                    'after' => $this->option('apply') ? $this->balances((int) $source->seller_id) : null];
            }
            foreach ($plan['unpaid_order_insurances'] ?? [] as $item) {
                $source = SellerOrderInsurance::query()->when($this->option('apply'), fn ($q) => $q->lockForUpdate())->findOrFail($item['id']);
                if ((int) $source->order_id !== (int) $item['order_id'] || (int) $source->seller_id !== (int) $item['seller_id']
                    || (float) $source->amount !== (float) $item['amount'] || $source->status !== 'expired' || $source->payment_status !== 'unpaid'
                    || $source->paid_at || (float) $source->confiscated_amount !== 0.0) throw new RuntimeException('Historical unpaid insurance changed');
                if ($this->option('apply') && !data_get($source->metadata, 'vendor_insurance_retirement')) {
                    $metadata = $source->metadata ?: [];
                    $metadata['vendor_insurance_retirement'] = ['at' => now()->toIso8601String(), 'authorization' => $plan['authorization'],
                        'uncollected_obligation_retired' => true, 'money_movement' => false];
                    $source->update(['metadata' => $metadata]);
                }
                $report[] = ['unpaid_insurance_id' => $source->id, 'order_id' => $source->order_id, 'historical_amount' => $source->amount,
                    'money_movement' => false, 'order_state_changed' => false];
            }
            return $report;
        });
        $this->line(json_encode(['applied' => (bool) $this->option('apply'), 'items' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }

    private function balances(int $sellerId): array
    {
        return SellerLedgerEntry::where('seller_id', $sellerId)->select('bucket')
            ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) AS balance")
            ->groupBy('bucket')->pluck('balance', 'bucket')->all();
    }
}
