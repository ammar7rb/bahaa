<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RetireCustomerSecurityDeposits extends Command
{
    protected $signature = 'customers:retire-security-deposits {--apply}';
    protected $description = 'Archive customer deposits without deleting purchase funds or vendor records';

    public function handle(): int
    {
        $tables = ['customer_insurance_ledger_entries','customer_insurance_balances','order_insurances','customer_activation_invoices','post_purchase_invoices','customer_balance_deposits'];
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;
            $query = DB::table($table);
            if ($table === 'customer_balance_deposits') $query->where('wallet_type','insurance');
            $this->line($table.': '.$query->count());
        }
        if (!$this->option('apply')) { $this->info('Audit only. No financial changes.'); return self::SUCCESS; }
        DB::transaction(function () use ($tables) {
            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) continue;
                $query = DB::table($table);
                if ($table === 'customer_balance_deposits') $query->where('wallet_type','insurance');
                foreach ($query->lockForUpdate()->get() as $row) {
                    $id = (string) ($row->id ?? $row->customer_id);
                    if (DB::table('customer_financial_archives')->where('source_table',$table)->where('source_id',$id)->exists()) continue;
                    DB::table('customer_financial_archives')->insert([
                        'source_table'=>$table,'source_id'=>$id,'snapshot'=>json_encode($row,JSON_UNESCAPED_UNICODE),
                        'reason'=>'customer_security_deposit_retired','archived_at'=>now(),
                    ]);
                    // Keep original paid totals/references as accounting evidence.
                    // Only outstanding insurance is removed from collectible dues.
                    if (in_array($table,['customer_activation_invoices','post_purchase_invoices'],true)) {
                        $paid = ($row->payment_status ?? '') === 'paid' || $row->status === 'paid';
                        $metadata = json_decode($row->metadata ?? '{}',true) ?: [];
                        $metadata['customer_deposit_retired'] = true;
                        $metadata['archived_insurance_amount'] = $row->insurance_amount;
                        $update = ['insurance_amount'=>0,'metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE),'updated_at'=>now()];
                        if (!$paid) {
                            $total = max(0, (float)$row->total_amount - (float)$row->insurance_amount);
                            $update['total_amount'] = $total;
                            if ($total <= 0) $update['status'] = 'cancelled';
                        }
                        if ($table === 'post_purchase_invoices') $update['insurance_snapshot'] = json_encode(['enabled'=>false,'amount'=>0,'retired'=>true]);
                        DB::table($table)->where('id',$row->id)->update($update);
                    } elseif ($table === 'customer_balance_deposits') {
                        // Pending legacy receipts still need a matching decision.
                        if ($row->status === 'pending') DB::table($table)->where('id',$row->id)->update(['status'=>'legacy_review','updated_at'=>now()]);
                    } elseif ($table === 'order_insurances') {
                        DB::table($table)->where('id',$row->id)->update(['status'=>'archived','updated_at'=>now()]);
                    }
                }
            }
            foreach (['customer_order_insurance_status','customer_monthly_insurance_status'] as $key) {
                DB::table('business_settings')->updateOrInsert(['type'=>$key],['value'=>'0','updated_at'=>now()]);
            }
        });
        clearWebConfigCacheKeys();
        $this->info('Customer insurance removed from active collection. Financial snapshots retained; purchase and vendor balances untouched.');
        return self::SUCCESS;
    }
}
