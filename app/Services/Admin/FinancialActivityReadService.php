<?php
namespace App\Services\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Canonical payment records, not a sum of gateway requests and their wallet/order postings. */
class FinancialActivityReadService
{
    public const INTERNAL_METHODS = ['wallet','wallet_balance','customer_wallet','purchase_wallet','pay_by_wallet','operating_balance','available_balance','payments_balance'];
    private const FIELDS = ['id','source','account_type','account_id','order_id','amount','currency','status','review','method','reference','date','category','movement','confirmed','direction','balance','bonus','bucket','operation'];
    private array $columns=[];
    private array $missing=[];

    private function has(string $table,string $column): bool
    {
        $this->columns[$table] ??= Schema::getColumnListing($table);
        return in_array($column,$this->columns[$table],true);
    }
    private function c(string $table,string $column): string { return $this->has($table,$column) ? 'r.'.$column : 'NULL'; }
    private function literal(string $value): string { return DB::getPdo()->quote($value); }
    private function base(): string { return $this->literal(app(FinancialsService::class)->baseCurrency()); }
    private function row(string $table,string $source,string $type,array $fields): ?Builder
    {
        if (!$this->has($table,'id')) { $this->missing[]=$source; return null; }
        $fields += ['id'=>'r.id','source'=>$this->literal($source),'account_type'=>$this->literal($type),'date'=>$this->c($table,'created_at'),'currency'=>$this->base(),'category'=>$this->literal($source),'direction'=>"'in'",'confirmed'=>'0'];
        $q=DB::table($table.' as r');
        foreach(self::FIELDS as $key)$q->selectRaw(($fields[$key] ?? 'NULL').' as '.$key);
        return $q;
    }
    private function movement(string $method, bool $order=false,string $orderKey='r.id'): string
    {
        $list=implode(',',array_map(fn($m)=>$this->literal($m),self::INTERNAL_METHODS));
        $cash='';
        if($order) {
            $collector=$this->has('order_transactions','received_by') ? '(SELECT ot.received_by FROM order_transactions ot WHERE ot.order_id = '.$orderKey.' ORDER BY ot.id DESC LIMIT 1)' : 'NULL';
            $cash="WHEN $collector = 'seller' THEN 'vendor_collected' WHEN $method IN ('cash_on_delivery','cash') AND ($collector IS NULL OR $collector NOT IN ('admin','inhouse')) THEN 'unclassified'";
        }
        return "CASE WHEN $method IN ($list) THEN 'internal' $cash WHEN $method IS NULL OR $method = '' OR $method IN ('funding_intent','manual_settlement') THEN 'unclassified' ELSE 'external' END";
    }

    private function payments(): array
    {
        $queries=[];
        foreach([
            ['customer_balance_deposits','deposits','customer','customer_id','amount','status','paid','method_name','reviewed_at','id'],
            ['seller_balance_deposits','vendor_deposits','vendor','seller_id','amount','status','paid','payment_method','paid_at','transaction_reference'],
            ['customer_subscription_payments','customer_packages','customer','customer_id','quoted_amount','status','approved','payment_method','approved_at','payment_reference'],
            ['seller_package_subscriptions','vendor_packages','vendor','seller_id','paid_package_price','payment_status','paid','payment_method','activated_at','payment_reference'],
            ['vendor_extra_ad_purchases','extra_ads','vendor','seller_id','total_price','status','paid','payment_method','paid_at','payment_reference'],
        ] as [$table,$source,$type,$owner,$amount,$status,$paid,$method,$date,$ref]) {
            if(!$this->has($table,'id')) { $this->missing[]=$source; continue; }
            $c=fn($key)=>$this->c($table,$key);
            $movement=in_array($source,['deposits','vendor_deposits']) ? "'external'" : $this->movement($c($method));
            if($source === 'extra_ads' && $this->has('seller_ledger_entries','reference_type') && $this->has('seller_ledger_entries','reference_id')) {
                $refType=$this->literal(\App\Models\VendorExtraAdPurchase::class);
                $movement="CASE WHEN EXISTS (SELECT 1 FROM seller_ledger_entries le WHERE le.seller_id = r.seller_id AND le.reference_type = $refType AND le.reference_id = r.id AND le.bucket = 'operating' AND le.direction = 'debit') THEN 'internal' ELSE $movement END";
            }
            $q=$this->row($table,$source,$type,[
                'account_id'=>$c($owner),'amount'=>$c($amount),'status'=>$c($status),'method'=>$c($method),'reference'=>$c($ref),
                'date'=>'COALESCE('.$c($date).','.$c('created_at').')',
                'currency'=>$this->has($table,'currency_code') ? $c('currency_code') : ($this->has($table,'currency') ? $c('currency') : $this->base()),
                'movement'=>$movement,
                'confirmed'=>"CASE WHEN ".$c($status)." = '$paid' THEN 1 ELSE 0 END",
            ]);
            if($source === 'deposits')$q->where('r.wallet_type','purchase');
            if($source === 'vendor_deposits')$q->where('r.metadata->wallet_target','operating');
            $queries[]=$q;
        }
        if($this->has('orders','id')) {
            $c=fn($key)=>$this->c('orders',$key);
            $review=$c('customer_initial_payment_review_status');
            $hasEdits=$this->has('order_edit_histories','order_due_amount');
            $edited=$hasEdits ? 'EXISTS (SELECT 1 FROM order_edit_histories eh WHERE eh.order_id = r.id)' : '0 = 1';
            // The original price is immutable on current orders. Never use the edited total
            // AND separately add edit payments; old records lacking original terms stay unknown.
            $amount='CASE WHEN '.$c('init_order_amount').' > 0 THEN '.$c('init_order_amount')." WHEN $edited THEN NULL ELSE ".$c('order_amount').' END';
            $queries[]=$this->row('orders','orders','customer',[
                'account_id'=>$c('customer_id'),'order_id'=>'r.id','amount'=>$amount,'status'=>$c('payment_status'),'review'=>$review,'method'=>$c('payment_method'),'reference'=>$c('transaction_ref'),
                'confirmed'=>"CASE WHEN r.payment_status = 'paid' AND ($review IS NULL OR $review = 'approved') THEN 1 ELSE 0 END",'movement'=>$this->movement($c('payment_method'),true),
            ]);
            if($hasEdits) {
                $table='order_edit_histories';$c=fn($key)=>$this->c($table,$key);
                $edit=$this->row($table,'order_edits','customer',[
                    'account_id'=>'(SELECT o.customer_id FROM orders o WHERE o.id = r.order_id)','order_id'=>'r.order_id','amount'=>$c('order_due_amount'),
                    'status'=>$c('order_due_payment_status'),'method'=>$c('order_due_payment_method'),'reference'=>$c('order_due_transaction_ref'),
                    // An edit's cash collector is not established by the original order's collector.
                    'date'=>'COALESCE('.$c('updated_at').','.$c('created_at').')','movement'=>"CASE WHEN ".$c('order_due_payment_method')." IN ('cash_on_delivery','cash') THEN 'unclassified' ELSE ".$this->movement($c('order_due_payment_method')).' END',
                    'confirmed'=>"CASE WHEN ".$c('order_due_payment_status')." = 'paid' THEN 1 ELSE 0 END",
                ]);
                $queries[]=$edit->where('r.order_due_amount','>',0);
            }
        } else $this->missing[]='orders';
        if($this->has('wallet_transactions','id')) {
            $table='wallet_transactions';$c=fn($key)=>$this->c($table,$key);
            $fund=$this->row($table,'wallet_funding','customer',[
                'account_id'=>$c('user_id'),'amount'=>$c('credit'),'balance'=>$c('balance'),'bonus'=>$c('admin_bonus'),'method'=>$c('payment_method'),'reference'=>$c('reference'),'status'=>"'recorded'",'confirmed'=>'1',
                'movement'=>"CASE WHEN r.transaction_type = 'add_fund_by_admin' THEN 'adjustment' ELSE ".$this->movement($c('payment_method')).' END',
            ])->whereIn('r.transaction_type',['add_fund','add_fund_by_admin'])->where('r.credit','>',0);
            // Explicit ownership/reference only. No amount/timestamp matching.
            if($this->has('customer_balance_deposits','id') && $this->has('customer_balance_deposits','wallet_type')) {
                $concat=DB::getDriverName()==='sqlite' ? "'customer-offline-deposit-' || cd.id" : "CONCAT('customer-offline-deposit-', cd.id)";
                $fund->whereNotExists(fn($q)=>$q->selectRaw('1')->from('customer_balance_deposits as cd')->whereColumn('cd.customer_id','r.user_id')->where('cd.wallet_type','purchase')->whereRaw('r.reference = '.$concat));
            }
            $queries[]=$fund;
        }
        return array_filter($queries);
    }

    public function query(array $filters=[],bool $statement=false): Builder
    {
        $queries=$this->payments();
        if($statement) $queries=[...$queries,...$this->journal($filters['account_type'])];
        if(!$queries) { $union=DB::query();foreach(self::FIELDS as $field)$union->selectRaw('NULL as '.$field);$union->whereRaw('1 = 0'); }
        else { $union=array_shift($queries);foreach($queries as $q)$union->unionAll($q); }
        $q=DB::query()->fromSub($union,'a');
        foreach(['account_type','account_id','category','method','movement','status'] as $field)if(isset($filters[$field]) && $filters[$field]!=='')$q->where($field,$filters[$field]);
        if(!empty($filters['customer_id']))$q->where('account_type','customer')->where('account_id',$filters['customer_id']);
        if(!empty($filters['seller_id']))$q->where('account_type','vendor')->where('account_id',$filters['seller_id']);
        if(!empty($filters['from']))$q->where('date','>=',$filters['from'].' 00:00:00');
        if(!empty($filters['to']))$q->where('date','<',\Carbon\Carbon::parse($filters['to'])->addDay()->toDateString().' 00:00:00');
        if(($filters['confirmation'] ?? '') === 'confirmed')$q->where('confirmed',1);
        if(($filters['confirmation'] ?? '') === 'unconfirmed')$q->where('confirmed',0);
        if(!empty($filters['q']))$q->where(function($q)use($filters){
            $term='%'.$filters['q'].'%';$id=ctype_digit($filters['q']) ? (int)$filters['q'] : -1;
            $q->where('id',$id)->orWhere('account_id',$id)->orWhere('order_id',$id)->orWhere('reference','like',$term);
            foreach(['customer'=>'users','vendor'=>'sellers'] as $type=>$table)if($this->has($table,'f_name'))$q->orWhere(function($q)use($term,$type,$table){$q->where('account_type',$type)->whereExists(fn($n)=>$n->selectRaw('1')->from($table.' as person')->whereColumn('person.id','a.account_id')->where(fn($p)=>$p->where('person.f_name','like',$term)->orWhere('person.l_name','like',$term)));});
        });
        return $q;
    }

    private function journal(string $type): array
    {
        $out=[];
        if($type==='customer' && $this->has('wallet_transactions','id')) {
            $c=fn($key)=>$this->c('wallet_transactions',$key);
            // Every wallet posting remains visible as a ledger effect, alongside its
            // canonical payment if present. It is never another external collection.
            $out[]=$this->row('wallet_transactions','wallet','customer',[
                'account_id'=>$c('user_id'),'amount'=>'CASE WHEN r.debit > 0 THEN r.debit ELSE r.credit END','direction'=>"CASE WHEN r.debit > 0 THEN 'debit' ELSE 'credit' END",
                'balance'=>$c('balance'),'bonus'=>$c('admin_bonus'),'operation'=>$c('transaction_type'),'status'=>"'recorded'",'method'=>$c('payment_method'),'reference'=>$c('reference'),'movement'=>"'ledger'",'confirmed'=>'1',
            ]);
        }
        if($type==='vendor' && $this->has('seller_ledger_entries','id')) {
            $c=fn($key)=>$this->c('seller_ledger_entries',$key);
            $out[]=$this->row('seller_ledger_entries','vendor_ledger','vendor',[
                'account_id'=>$c('seller_id'),'amount'=>$c('amount'),'direction'=>$c('direction'),'bucket'=>$c('bucket'),'operation'=>$c('event_type'),'status'=>"'recorded'",'reference'=>$c('reference_code'),'movement'=>"'ledger'",'confirmed'=>'1',
            ])->whereIn('r.bucket',VendorFinancialsReadService::BUCKETS);
            foreach(['seller_settlements'=>'settlements','withdraw_requests'=>'withdrawals'] as $table=>$source)if($this->has($table,'id')) {
                $c=fn($key)=>$this->c($table,$key);
                $out[]=$this->row($table,$source,'vendor',['account_id'=>$c('seller_id'),'amount'=>$c('amount'),'order_id'=>$c('order_id'),'status'=>$c($source==='withdrawals' ? 'approved' : 'status'),'movement'=>"'ledger'",'confirmed'=>'1']);
            }
        }
        if($type==='customer' && $this->has('refund_transactions','id')) {
            $c=fn($key)=>$this->c('refund_transactions',$key);
            $out[]=$this->row('refund_transactions','refunds','customer',[
                'account_id'=>'(SELECT o.customer_id FROM orders o WHERE o.id = r.order_id)','order_id'=>$c('order_id'),'amount'=>$c('amount'),'status'=>$c('payment_status'),'method'=>$c('payment_method'),
                'reference'=>$c('refund_id'),'movement'=>"'refund'",'direction'=>"'out'",'confirmed'=>"CASE WHEN r.payment_status = 'paid' THEN 1 ELSE 0 END",
            ]);
        }
        if($type==='customer')foreach([
            ['order_admin_purchase_refunds','refund_admin','amount','status','completed_at'],
            ['order_refund_ledgers','refund_ledger','purchase_amount','status','created_at'],
            ['order_edit_histories','refund_edits','order_return_amount','order_return_payment_status','updated_at'],
        ] as [$table,$source,$amount,$status,$date])if($this->has($table,'id')) {
            $c=fn($key)=>$this->c($table,$key);
            $row=$this->row($table,$source,'customer',[
                'account_id'=>'(SELECT o.customer_id FROM orders o WHERE o.id = r.order_id)','order_id'=>$c('order_id'),'amount'=>$c($amount),'status'=>$c($status),'date'=>'COALESCE('.$c($date).','.$c('created_at').')','movement'=>"'refund'",'direction'=>"'out'",'confirmed'=>"CASE WHEN ".$c($status)." IN ('completed','available','returned') THEN 1 ELSE 0 END",'method'=>$source==='refund_edits' ? $c('order_return_payment_method') : 'NULL',
            ]);
            if($source==='refund_edits')$row->where('r.order_return_amount','>',0);
            $out[]=$row;
        }
        return $out;
    }

    public function workspace(array $filters,bool $statement=false): array
    {
        $q=$this->query($filters,$statement);
        $records=(clone $q)->orderByDesc('date')->orderBy('source')->orderByDesc('id')->paginate($filters['per_page'] ?? 20,['*'],'page',$filters['page'] ?? 1)->withQueryString();
        $names=[];
        foreach(['customer'=>'users','vendor'=>'sellers'] as $type=>$table)$names[$type]=$this->has($table,'f_name') ? DB::table($table)->whereIn('id',$records->where('account_type',$type)->pluck('account_id'))->get(['id','f_name','l_name'])->keyBy('id') : collect();
        $totals=[];
        foreach(['external','internal','unclassified','vendor_collected'] as $movement)$totals[$movement]=(clone $q)->where('confirmed',1)->where('movement',$movement)->whereNotNull('amount')->select('currency')->selectRaw('SUM(amount) as amount, COUNT(*) as records')->groupBy('currency')->get();
        $options=$this->query(array_intersect_key($filters,array_flip(['account_type','account_id'])), $statement);
        return compact('filters','records','names','totals') + ['missing'=>array_unique($this->missing),'methods'=>(clone $options)->whereNotNull('method')->distinct()->orderBy('method')->pluck('method'),'categories'=>(clone $options)->distinct()->orderBy('category')->pluck('category'),'unknownAmounts'=>(clone $q)->whereNull('amount')->count()];
    }
}
