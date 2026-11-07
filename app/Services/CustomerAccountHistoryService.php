<?php
namespace App\Services;

use Illuminate\Support\Facades\{DB,Schema};

/** Read-only projection of original ledgers, including records preceding this workspace. */
class CustomerAccountHistoryService
{
    public function paginate(?int $customerId, array $filters=[], bool $financial=true)
    {
        $queries=[];
        $add=function(string $table,array $map,array $constants=[],?callable $scope=null)use(&$queries){
            if(!Schema::hasTable($table))return;
            $columns=Schema::getColumnListing($table);$q=DB::table($table);
            foreach(['customer_id','created_at','operation','reason','admin_id','source','financial_reference','amount','currency_code','allowance_credit','allowance_debit','allowance_balance','details','support_reference','status','record_id','record_type'] as $alias){
                $column=$map[$alias]??null;
                if(is_array($column))$q->selectRaw($column[0].' as '.$alias);
                elseif($column && in_array($column,$columns,true))$q->addSelect($column.' as '.$alias);
                elseif(array_key_exists($alias,$constants))$q->selectRaw('? as '.$alias,[$constants[$alias]]);
                else $q->selectRaw('NULL as '.$alias);
            }
            if($scope)$scope($q,$columns);
            $queries[]=$q;
        };
        $add('users',['customer_id'=>'id','created_at'=>'created_at','record_id'=>'id'],['operation'=>'account_registered','record_type'=>'account','source'=>'account_record','status'=>'recorded'],fn($q)=>$q->where('id','>',0)->whereNotNull('created_at'));
        $add('customer_account_actions',array_combine(['customer_id','created_at','operation','reason','admin_id','source','financial_reference','amount','details','support_reference'],['customer_id','created_at','operation','reason','admin_id','source','financial_reference','amount','details','support_reference'])+['record_id'=>'id'],['record_type'=>'admin_action','status'=>'recorded'],fn($q)=>$financial?$q:$q->where('operation','like','account_%'));
        $add('customer_profile_changes',['customer_id'=>'customer_id','created_at'=>'created_at','operation'=>'action','source'=>'actor_type','record_id'=>'id'],['record_type'=>'profile_change','status'=>'recorded']);
        if($financial){
            $add('wallet_transactions',['customer_id'=>'user_id','created_at'=>'created_at','reason'=>'transaction_type','source'=>'payment_method','financial_reference'=>'reference','amount'=>['credit - debit'],'record_id'=>'id'],['operation'=>'wallet_transaction','record_type'=>'wallet','status'=>'committed']);
            $add('customer_subscription_payments',['customer_id'=>'customer_id','created_at'=>'created_at','operation'=>'purchase_kind','reason'=>'terms_name','admin_id'=>'reviewed_by','source'=>'payment_method','financial_reference'=>'payment_reference','amount'=>'quoted_amount','currency_code'=>'currency_code','status'=>'status','record_id'=>'id'],['operation'=>'subscription_payment','record_type'=>'subscription_payment']);
            $add('customer_monthly_subscriptions',['customer_id'=>'customer_id','created_at'=>'starts_at','reason'=>'package_name','amount'=>'package_price','record_id'=>'id'],['operation'=>'historical_subscription','source'=>'historical','record_type'=>'subscription'],fn($q,$columns)=>in_array('payment_id',$columns,true)?$q->whereNull('payment_id'):$q);
            $add('customer_purchase_package_subscriptions',['customer_id'=>'customer_id','created_at'=>'created_at','reason'=>'package_name','source'=>'payment_method','financial_reference'=>'payment_reference','amount'=>'paid_package_price','status'=>'payment_status','record_id'=>'id'],['operation'=>'historical_subscription','record_type'=>'legacy_subscription']);
            $add('customer_balance_deposits',['customer_id'=>'customer_id','created_at'=>'created_at','reason'=>'wallet_type','admin_id'=>'reviewed_by_admin_id','source'=>'method_name','financial_reference'=>'payment_reference','amount'=>'amount','status'=>'status','record_id'=>'id'],['operation'=>'funding_request','record_type'=>'deposit']);
            $add('customer_account_debts',['customer_id'=>'customer_id','created_at'=>'created_at','reason'=>'reason','admin_id'=>'created_by_admin_id','amount'=>'amount','record_id'=>'id'],['operation'=>'debt_created','record_type'=>'debt','source'=>'admin','status'=>'recorded']);
            $add('customer_debt_repayments',['customer_id'=>'customer_id','created_at'=>'created_at','financial_reference'=>'funding_reference','amount'=>'amount','record_id'=>'id'],['operation'=>'debt_repayment','record_type'=>'repayment','source'=>'confirmed_funding','status'=>'committed']);
            $add('customer_account_debts',['customer_id'=>'customer_id','created_at'=>'archived_at','reason'=>'reason','admin_id'=>'archived_by_admin_id','amount'=>'amount','record_id'=>'id'],['operation'=>'debt_archived','record_type'=>'debt_archive','source'=>'admin','status'=>'settled'],fn($q)=>$q->whereNotNull('archived_at'));
            $add('customer_account_debts',['customer_id'=>'customer_id','created_at'=>'settled_at','reason'=>'reason','amount'=>'paid_amount','record_id'=>'id'],['operation'=>'debt_settled','record_type'=>'debt_settlement','source'=>'confirmed_funding','status'=>'settled'],fn($q)=>$q->whereNotNull('settled_at'));
            $add('orders',['customer_id'=>'customer_id','created_at'=>'created_at','reason'=>'order_status','source'=>'payment_method','financial_reference'=>'id','amount'=>'order_amount','status'=>'payment_status','record_id'=>'id'],['operation'=>'order_created','record_type'=>'order'],fn($q,$columns)=>in_array('is_guest',$columns,true)?$q->where('is_guest',0):$q);
            foreach(['refund_requests','order_admin_purchase_refunds'] as $table)$add($table,['customer_id'=>'customer_id','created_at'=>'created_at','reason'=>'reason','financial_reference'=>'order_id','amount'=>'amount','status'=>'status','record_id'=>'id'],['operation'=>'refund_request','record_type'=>$table,'source'=>'original_refund']);
            $add('customer_legacy_payment_receipts',['customer_id'=>'customer_id','created_at'=>'received_at','financial_reference'=>'reference','amount'=>'amount','currency_code'=>'currency_code','record_id'=>'id'],['operation'=>'historical_payment','record_type'=>'legacy_payment','source'=>'historical','status'=>'reconciliation']);
            $add('customer_purchase_limit_transactions',['customer_id'=>'customer_id','created_at'=>'created_at','reason'=>'note','admin_id'=>'created_by_admin_id','financial_reference'=>'reference','allowance_credit'=>'credit','allowance_debit'=>'debit','allowance_balance'=>'balance_after','record_id'=>'id'],['operation'=>'credit_allowance_event','record_type'=>'purchase_allowance','source'=>'purchase_allowance','status'=>'recorded']);
            $add('customer_wallet_withdrawals',['customer_id'=>'customer_id','created_at'=>'created_at','reason'=>'admin_note','admin_id'=>'admin_id','financial_reference'=>'reference','amount'=>'amount','status'=>'status','record_id'=>'id'],['operation'=>'withdrawal_request','record_type'=>'withdrawal','source'=>'original_withdrawal']);
            $add('customer_insurance_ledger_entries',['customer_id'=>'customer_id','created_at'=>'created_at','reason'=>'entry_type','financial_reference'=>'reference','amount'=>['credit - debit'],'record_id'=>'id'],['operation'=>'historical_insurance_entry','record_type'=>'historical_insurance','source'=>'historical','status'=>'recorded']);
        }
        $union=array_shift($queries);foreach($queries as $query)$union->unionAll($query);
        $query=DB::query()->fromSub($union,'customer_history')->when($customerId,fn($q)=>$q->where('customer_id',$customerId));
        if(!$customerId && !empty($filters['q']))$query->whereIn('customer_id',app(AdminAccountDirectoryService::class)->query(['type'=>'customer','q'=>$filters['q']])->select('accounts.id'));
        if(!empty($filters['operation'])){
            $operation=$filters['operation'];
            $query->where(fn($q)=>$q->where('operation',$operation)->when(str_starts_with($operation,'subscription_'),fn($q)=>$q->orWhere('operation',substr($operation,13))));
        }
        foreach(['from'=>'>=','to'=>'<='] as $key=>$comparison)if(!empty($filters[$key]))$query->whereDate('created_at',$comparison,$filters[$key]);
        return $query->orderByDesc('created_at')->orderBy('record_type')->orderByDesc('record_id')->paginate(20,['*'],'actions_page')->withQueryString();
    }
}
