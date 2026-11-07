<?php
namespace App\Services\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Vendor liabilities and movements inside Company Financials; never initialises a wallet. */
class VendorFinancialsReadService
{
    public const BUCKETS = ['available','operating','pending','sales_total','pending_withdraw','withdrawn'];
    public const FLOWS = ['ledger','deposits','withdrawals','missing_references','duplicate_keys'];
    private array $missing = [];

    private function exists(string $table): bool
    {
        if (Schema::hasTable($table)) return true;
        $this->missing[] = $table; return false;
    }

    private function party(Builder $q, array $filters): Builder
    {
        if (!empty($filters['seller_id'])) $q->where('r.seller_id',$filters['seller_id']);
        // Vendor records never match a customer-account filter.
        if (!empty($filters['customer_id'])) $q->whereRaw('1 = 0');
        return $q;
    }

    private function period(Builder $q, array $filters): Builder
    {
        if (!empty($filters['from'])) $q->where('r.created_at','>=',$filters['from'].' 00:00:00');
        if (!empty($filters['to'])) $q->where('r.created_at','<',\Carbon\Carbon::parse($filters['to'])->addDay()->toDateString().' 00:00:00');
        return $q;
    }

    public function query(string $flow, array $filters, bool $current = false): Builder
    {
        $table = match($flow) { 'deposits'=>'seller_balance_deposits','withdrawals'=>'withdraw_requests','settlements'=>'seller_settlements',default=>'seller_ledger_entries' };
        if (!$this->exists($table)) return DB::query()->selectRaw('NULL as id, NULL as amount')->whereRaw('1 = 0');
        $q = $this->party(DB::table($table.' as r'),$filters);
        if (!$current) $this->period($q,$filters);
        if ($flow === 'deposits') $q->where('r.metadata->wallet_target','operating');
        if ($flow === 'withdrawals') $q->whereNotNull('r.seller_id');
        if (in_array($flow,['ledger','missing_references','duplicate_keys'])) {
            $q->whereIn('r.bucket',self::BUCKETS);
            if (!empty($filters['bucket'])) $q->where('r.bucket',$filters['bucket']);
            if (!empty($filters['direction'])) $q->where('r.direction',$filters['direction']);
            if (!empty($filters['event_type'])) $q->where('r.event_type','like','%'.$filters['event_type'].'%');
            if ($flow === 'missing_references') $q->where(fn($q)=>$q->whereNull('r.reference_code')->orWhere('r.reference_code',''))->whereNotIn('r.event_type',['legacy_opening_balance','legacy_wallet_sync']);
            if ($flow === 'duplicate_keys') {
                // Group counting is performed by the outer query, not COUNT() on a grouped builder.
                $duplicates = $this->party(DB::table($table.' as r'),$filters)->whereIn('r.bucket',self::BUCKETS)->whereNotNull('r.idempotency_key')->where('r.idempotency_key','<>','');
                $q->whereIn('r.idempotency_key',$duplicates->select('r.idempotency_key')->groupBy('r.idempotency_key')->havingRaw('COUNT(*) > 1'));
            }
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            match($flow) {
                'withdrawals'=>$q->where('r.approved',$filters['status']),
                'deposits','settlements'=>$q->where('r.status',$filters['status']),
                default=>$q,
            };
        }
        if (!empty($filters['method'])) $flow === 'deposits' ? $q->where('r.payment_method',$filters['method']) : $q->whereRaw('1 = 0');
        if ($flow === 'settlements' && !empty($filters['settlement_window'])) {
            match($filters['settlement_window']) {
                'overdue'=>$q->where('r.status','held')->where('r.due_at','<',now()),
                'due_soon'=>$q->where('r.status','held')->whereBetween('r.due_at',[now(),now()->addDays(5)]),
                'escalated'=>$q->whereNotNull('r.escalated_at'),
            };
        }
        if (!empty($filters['q'])) $q->where(function ($q) use ($filters,$flow) {
            $term='%'.$filters['q'].'%'; $number=ctype_digit($filters['q']) ? (int)$filters['q'] : -1;
            $q->where('r.id',$number)->orWhere('r.seller_id',$number);
            if (Schema::hasTable('sellers')) $q->orWhereExists(fn($names)=>$names->selectRaw('1')->from('sellers as sn')->whereColumn('sn.id','r.seller_id')->where(fn($n)=>$n->where('sn.f_name','like',$term)->orWhere('sn.l_name','like',$term)));
            if ($flow === 'settlements') $q->orWhere('r.order_id',$number);
            elseif ($flow === 'deposits') $q->orWhere('r.transaction_reference','like',$term);
            elseif ($flow !== 'withdrawals') $q->orWhere('r.reference_code','like',$term);
        });
        return $q;
    }

    public function summary(array $filters): array
    {
        $cards=[]; $currency=app(FinancialsService::class)->baseCurrency();
        // A balance is always an all-date credit-minus-debit position. Movement filters cannot turn it into an income total.
        foreach (self::BUCKETS as $bucket) {
            $scope=array_intersect_key($filters,array_flip(['seller_id','customer_id'])); $scope['bucket']=$bucket;
            $q=$this->query('ledger',$scope,true);
            $value=in_array('seller_ledger_entries',$this->missing) ? null : (clone $q)->selectRaw("COALESCE(SUM(CASE WHEN r.direction = 'credit' THEN r.amount ELSE -r.amount END),0) as value")->value('value');
            $cards[]=['key'=>$bucket,'value'=>$value,'count'=>$q->count(),'currency'=>$currency,'current'=>true,'filters'=>$scope+['tab'=>'vendor_accounts','register'=>'ledger','all_dates'=>1]];
        }
        foreach (['deposits'=>['pending','paid','rejected','reversed'],'withdrawals'=>['0','1','2'],'settlements'=>['held','disputed','released','reversed']] as $flow=>$states) foreach($states as $state) {
            $scope=array_intersect_key($filters,array_flip(['from','to','seller_id','customer_id','q'])); $scope['status']=$state;
            $q=$this->query($flow,$scope); $values=[];
            if (!in_array(match($flow){'deposits'=>'seller_balance_deposits','withdrawals'=>'withdraw_requests',default=>'seller_settlements'},$this->missing)) {
                // Deposit transaction currency stays separate; never add different currencies.
                $values=$flow === 'deposits' ? (clone $q)->select('r.currency_code as currency')->selectRaw('SUM(r.amount) as amount')->groupBy('r.currency_code')->get()->all() : [(object)['amount'=>(clone $q)->sum('r.amount'),'currency'=>$currency]];
            }
            $cards[]=['key'=>$flow.'_'.$state,'values'=>$values,'count'=>$q->count(),'current'=>false,'filters'=>$scope+['tab'=>$flow === 'settlements' ? 'settlements' : 'vendor_accounts','register'=>$flow]];
        }
        foreach(['overdue','due_soon','escalated'] as $window) {
            $scope=array_intersect_key($filters,array_flip(['from','to','seller_id','customer_id','q']));$scope['settlement_window']=$window;
            $q=$this->query('settlements',$scope);
            $cards[]=['key'=>$window,'value'=>null,'count'=>$q->count(),'current'=>false,'filters'=>$scope+['tab'=>'settlements']];
        }
        foreach(['missing_references','duplicate_keys'] as $flow) {
            $scope=array_intersect_key($filters,array_flip(['from','to','seller_id','customer_id','q']));$q=$this->query($flow,$scope);
            $count=$flow==='duplicate_keys' && !in_array('seller_ledger_entries',$this->missing) ? DB::query()->fromSub((clone $q)->select('r.idempotency_key')->distinct(),'duplicate_groups')->count() : $q->count();
            $cards[]=['key'=>$flow,'value'=>null,'count'=>$count,'current'=>false,'filters'=>$scope+['tab'=>'vendor_accounts','register'=>$flow]];
        }
        return $cards;
    }

    public function workspace(array $filters): array
    {
        $flow=($filters['tab'] ?? '') === 'settlements' ? 'settlements' : ($filters['register'] ?? 'ledger');
        $q=$this->query($flow,$filters, !empty($filters['all_dates']) && $flow === 'ledger');
        $fields=match($flow) {
            'deposits'=>['id','seller_id','amount','currency_code','status','payment_method','transaction_reference','created_at'],
            'withdrawals'=>['id','seller_id','amount','approved','created_at'],
            'settlements'=>['id','seller_id','order_id','amount','status','due_at','released_at','escalated_at','created_at'],
            default=>['id','seller_id','bucket','direction','amount','event_type','reference_code','idempotency_key','created_at'],
        };
        $rows=$this->missing ? new \Illuminate\Pagination\LengthAwarePaginator([],0,$filters['per_page'] ?? 20) : $q->select(array_map(fn($c)=>'r.'.$c,$fields))->orderByDesc('r.created_at')->orderByDesc('r.id')->paginate($filters['per_page'] ?? 20,['*'],'page',$filters['page'] ?? 1)->withQueryString();
        $names=Schema::hasTable('sellers') ? DB::table('sellers')->whereIn('id',$rows->pluck('seller_id'))->get(['id','f_name','l_name'])->keyBy('id') : collect();
        return ['flow'=>$flow,'rows'=>$rows,'vendorNames'=>$names,'vendorCards'=>$this->summary($filters),'missing'=>$this->missing];
    }
}
