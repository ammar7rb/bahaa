<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Services\CustomerPurchaseEligibilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReconcileCustomerPurchaseEligibility extends Command
{
    protected $signature = 'customers:reconcile-purchase-eligibility {--apply}';
    protected $description = 'Reevaluate paid historical customer holds without changing payments or vendor restrictions.';

    public function handle(CustomerPurchaseEligibilityService $eligibility): int
    {
        $ids = Order::where('is_guest', 0)->where('payment_status','paid')->where('order_status','pending')
            ->whereNull('customer_checkout_operation_id')->where(function($q) {
                $q->whereIn('commerce_flow_status',['customer_insurance_pending','customer_insurance_under_review'])->orWhere('activation_status','activation_pending');
            })->pluck('id');
        $this->info('Historical customer holds: '.$ids->count());
        if (!$this->option('apply')) { $this->info('Audit only. No financial changes.'); return self::SUCCESS; }
        foreach ($ids as $id) {
            $customerId = (int) Order::findOrFail($id)->customer_id;
            DB::transaction(function() use ($customerId, $id, $eligibility) {
                if (!User::lockForUpdate()->find($customerId)) return;
                $order = Order::lockForUpdate()->findOrFail($id);
                if ($order->customer_checkout_operation_id || $order->payment_status !== 'paid' || $order->order_status !== 'pending') return;
                $operation = (string) Str::uuid();
                DB::table('customer_checkout_operations')->insert(['id'=>$operation,'customer_id'=>$customerId,
                    'cart_fingerprint'=>hash('sha256','historical:'.$id),'first_exception'=>false,'consumed_at'=>now(),
                    'order_ids'=>json_encode([$id]),'created_at'=>now(),'updated_at'=>now()]);
                $updates = ['customer_checkout_operation_id'=>$operation];
                if (in_array($order->commerce_flow_status,['customer_insurance_pending','customer_insurance_under_review'],true)) {
                    $updates['commerce_flow_status']='pending_admin_review';
                    foreach (['admin_order_review_status'=>'pending','commerce_flow_status_updated_at'=>now()] as $field=>$value)
                        if (Schema::hasColumn('orders',$field)) $updates[$field]=$value;
                }
                if (in_array($order->operational_blocked_by,['customer_insurance','customer_order_insurance'],true)) {
                    $updates['operational_blocked_by']=null; $updates['operational_block_reason']=null;
                }
                DB::table('orders')->where('id',$id)->update($updates);
                $eligibility->reevaluateCustomer($customerId);
            }, 3);
        }
        $this->info('Reconciliation completed; recorded payments and vendor states preserved.');
        return self::SUCCESS;
    }
}
