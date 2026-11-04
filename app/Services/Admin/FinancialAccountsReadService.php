<?php
namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialAccountsReadService
{
    private array $columns=[];
    private function has(string $table,string $column): bool { $this->columns[$table] ??= Schema::getColumnListing($table);return in_array($column,$this->columns[$table],true); }

    public function accounts(array $filters): \Illuminate\Pagination\LengthAwarePaginator
    {
        $queries=[];
        foreach(['customer'=>'users','vendor'=>'sellers'] as $type=>$table)if($this->has($table,'id')) {
            $q=DB::table($table)->where('id','>',0)->select(['id','f_name','l_name']);
            $q->selectRaw("'$type' as account_type")->selectRaw(($this->has($table,'phone') ? 'phone' : 'NULL').' as phone');
            $q->selectRaw(($type==='customer' && $this->has($table,'wallet_balance') ? 'wallet_balance' : 'NULL').' as wallet_balance');
            $queries[]=$q;
        }
        if(!$queries)return new \Illuminate\Pagination\LengthAwarePaginator([],0,20);
        $union=array_shift($queries);foreach($queries as $q)$union->unionAll($q);
        $q=DB::query()->fromSub($union,'accounts');
        if(!empty($filters['account_type']))$q->where('account_type',$filters['account_type']);
        if(!empty($filters['account_id']))$q->where('id',$filters['account_id']);
        if(!empty($filters['q']))$q->where(function($q)use($filters){$term='%'.$filters['q'].'%';$q->where('f_name','like',$term)->orWhere('l_name','like',$term)->orWhere('phone','like',$term);if(ctype_digit($filters['q']))$q->orWhere('id',(int)$filters['q']);});
        $page=$q->orderByDesc('id')->orderBy('account_type')->paginate($filters['per_page'] ?? 20,['*'],'page',$filters['page'] ?? 1)->withQueryString();
        $customerIds=$page->where('account_type','customer')->pluck('id');$vendorIds=$page->where('account_type','vendor')->pluck('id');
        $customerPackages=collect();$vendorPackages=collect();$balances=collect();$legacy=collect();
        if($customerIds->isNotEmpty() && $this->has('customer_monthly_subscriptions','package_id')) {
            $fields=array_values(array_filter(['id','customer_id','package_id','package_name','package_price','starts_at','expires_at','granted_allowance','additional_allowance','committed_allowance'],fn($c)=>$this->has('customer_monthly_subscriptions',$c)));
            $customerPackages=DB::table('customer_monthly_subscriptions')->whereIn('customer_id',$customerIds)->whereNotNull('package_id')->select($fields)
                ->orderByRaw('CASE WHEN starts_at <= ? AND expires_at > ? THEN 0 ELSE 1 END',[now(),now()])->orderByDesc('id')->get()->unique('customer_id')->keyBy('customer_id');
        }
        if($vendorIds->isNotEmpty()) {
            if($this->has('seller_package_subscriptions','id')) {
                $fields=array_values(array_filter(['id','seller_id','package_name','paid_package_price','status','payment_status','starts_at','started_at','activated_at','expires_at'],fn($c)=>$this->has('seller_package_subscriptions',$c)));
                $vendorPackages=DB::table('seller_package_subscriptions')->whereIn('seller_id',$vendorIds)->select($fields)->orderByRaw("CASE WHEN status = 'active' AND expires_at > ? THEN 0 ELSE 1 END",[now()])->orderByDesc('id')->get()->unique('seller_id')->keyBy('seller_id');
            }
            if($this->has('seller_ledger_entries','bucket'))$balances=DB::table('seller_ledger_entries')->whereIn('seller_id',$vendorIds)->select('seller_id','bucket')->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) as balance")->groupBy('seller_id','bucket')->get()->groupBy('seller_id');
            // Same read-only fallback as SellerLedgerService::summary(..., false).
            if($this->has('seller_wallets','total_earning'))$legacy=DB::table('seller_wallets')->whereIn('seller_id',$vendorIds)->get(['seller_id','total_earning'])->keyBy('seller_id');
        }
        $page->getCollection()->transform(function($account)use($customerPackages,$vendorPackages,$balances,$legacy){
            $account->payments_balance=null;$account->allowance=null;$account->remaining=null;$account->package_name=null;$account->package_price=null;$account->package_status=null;$account->expires_at=null;
            $package=$account->account_type==='customer' ? $customerPackages->get($account->id) : $vendorPackages->get($account->id);
            if($account->account_type==='vendor') {
                $ledger=$balances->get($account->id);
                $account->wallet_balance=$ledger ? ($ledger->firstWhere('bucket','available')->balance ?? 0) : max(0,(float)($legacy->get($account->id)->total_earning ?? 0));
                $account->payments_balance=$ledger ? ($ledger->firstWhere('bucket','operating')->balance ?? 0) : 0;
            }
            if($package) {
                $account->package_name=($package->package_name ?? null) ?: __('financial-activity.unnamed_package');$account->package_price=$package->package_price ?? $package->paid_package_price ?? null;$account->expires_at=$package->expires_at ?? null;
                $account->package_status=$account->account_type==='vendor' ? ($package->status ?? null) : (!$account->expires_at || !isset($package->starts_at) ? 'unknown' : (\Carbon\Carbon::parse($account->expires_at)->isPast() ? 'expired' : (\Carbon\Carbon::parse($package->starts_at)->isFuture() ? 'scheduled' : 'active')));
                if($account->account_type==='customer' && $account->package_status==='active' && isset($package->granted_allowance,$package->committed_allowance)){$account->allowance=(float)($package->granted_allowance ?? 0)+(float)($package->additional_allowance ?? 0);$account->remaining=max(0,$account->allowance-(float)($package->committed_allowance ?? 0));}
            }
            return $account;
        });
        return $page;
    }

    public function account(string $type,int $id): object
    {
        abort_unless(in_array($type,['customer','vendor']),404);
        $account=$this->accounts(['account_type'=>$type,'account_id'=>$id])->first();abort_unless($account,404);return $account;
    }
}
