<?php

namespace App\Services\Admin;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only projections. A missing allocation is NULL, never an invented zero. */
class CompanyFinancialsService
{
    public const TABS = ['incoming', 'sales', 'packages', 'shipping', 'invoice_dues', 'shipping_dues', 'returns', 'vendor_accounts', 'settlements', 'vendor_debts'];
    private array $columns = [];
    private array $missing = [];
    private const FIELDS = ['id','source','order_id','seller_id','customer_id','account_type','date','amount','currency','status','method','reference','name','commission','company_merchandise','deduction','company_shipping','vendor_shipping','vendor_cost','shipping_cost','due_at','settlement_status','settlement_amount','starts_at','expires_at','current_price','subscription_status','confirmed','fulfilled','allocation','merchandise_sale','procurement','merchandise_margin','pricing_id'];

    public function __construct(private readonly FinancialsService $financials) {}

    private function has(string $table, string $column): bool
    {
        $this->columns[$table] ??= Schema::getColumnListing($table);
        return in_array($column, $this->columns[$table], true);
    }

    private function col(string $table, string $column, string $alias = 'r'): string
    {
        return $this->has($table, $column) ? "$alias.$column" : 'NULL';
    }

    private function projection(string $table, string $source, array $expressions): ?Builder
    {
        if (!$this->has($table, 'id')) { $this->missing[] = $source; return null; }
        $q = DB::table($table.' as r');
        $expressions += ['id'=>'r.id', 'source'=>"'$source'", 'date'=>$this->col($table,'created_at'), 'currency'=>DB::getPdo()->quote($this->financials->baseCurrency())];
        foreach (self::FIELDS as $field) $q->selectRaw(($expressions[$field] ?? 'NULL').' as '.$field);
        return $q;
    }

    private function orders(string $tab): ?Builder
    {
        $c = fn ($name) => $this->col('orders', $name);
        $review = $c('customer_initial_payment_review_status');
        $confirmed = "CASE WHEN r.payment_status = 'paid' AND ($review IS NULL OR $review = 'approved') THEN 1 ELSE 0 END";
        if ($this->has('orders','edited_status') && $this->has('order_edit_histories','order_due_amount')) {
            $confirmed = "CASE WHEN r.payment_status = 'paid' AND ($review IS NULL OR $review = 'approved') AND NOT (r.edited_status IS NOT NULL AND r.edited_status = 1 AND EXISTS (SELECT 1 FROM order_edit_histories eh WHERE eh.order_id = r.id AND eh.id = (SELECT MAX(last_edit.id) FROM order_edit_histories last_edit WHERE last_edit.order_id = r.id) AND eh.order_due_payment_status = 'unpaid' AND eh.order_due_amount > 0)) THEN 1 ELSE 0 END";
        }
        $settlement = $this->has('seller_settlements','order_id');
        // One order, one latest persisted settlement: never multiply rows with a many-to-many join.
        $s = fn ($field) => $settlement && $this->has('seller_settlements',$field)
            ? '(SELECT ss.'.$field.' FROM seller_settlements ss WHERE ss.order_id = r.id AND ss.seller_id = r.seller_id ORDER BY ss.id DESC LIMIT 1)' : 'NULL';
        $tx = fn ($field) => $this->has('order_transactions',$field)
            ? '(SELECT ot.'.$field." FROM order_transactions ot WHERE ot.order_id = r.id AND ot.seller_is = r.seller_is AND ot.seller_id = r.seller_id ORDER BY ot.id DESC LIMIT 1)" : 'NULL';
        $shipping = "CASE WHEN r.shipping_responsibility = 'sellerwise_shipping' THEN COALESCE(".$c('shipping_seller_entitlement').','.$c('seller_shipping_allocation').','.$tx('delivery_charge').') ELSE 0 END';
        if (!$this->has('orders','shipping_responsibility')) $shipping = 'NULL';
        // Mirrors legacy wallet recognition, not net profit or a delivery-cost allocation.
        $companyShipping = $this->has('orders','shipping_responsibility') && $this->has('orders','is_shipping_free')
            ? "CASE WHEN r.is_shipping_free = 0 AND (r.shipping_responsibility = 'inhouse_shipping' OR r.seller_is = 'admin') THEN r.shipping_cost ELSE 0 END" : 'NULL';
        $priced = $this->has('order_vendor_pricings','procurement_amount');
        $p = fn ($field) => $priced ? '(SELECT vp.'.$field.' FROM order_vendor_pricings vp WHERE vp.order_id = r.id AND vp.seller_id = r.seller_id LIMIT 1)' : 'NULL';
        $pricingId = $p('id');
        $procurement = $p('procurement_amount');
        $sale = $p('merchandise_sale');
        $q = $this->projection('orders','orders', [
            'pricing_id'=>$pricingId, 'procurement'=>$procurement, 'merchandise_sale'=>$sale,
            'merchandise_margin'=>"CASE WHEN $pricingId IS NOT NULL THEN $sale - $procurement END",
            'order_id'=>'r.id','seller_id'=>"CASE WHEN r.seller_is = 'seller' THEN r.seller_id END",'customer_id'=>$c('customer_id'),
            'amount'=>$tab === 'invoice_dues' ? "CASE WHEN $pricingId IS NOT NULL THEN $procurement ELSE ".$tx('seller_amount').' END' : $c('order_amount'), 'status'=>$c('payment_status'),
            'method'=>$c('payment_method'),'reference'=>$c('transaction_ref'),'name'=>$c('order_status'),
            'commission'=>"CASE WHEN r.seller_is = 'seller' AND $pricingId IS NULL THEN ".$c('admin_commission').' END',
            'company_merchandise'=>"CASE WHEN r.seller_is = 'admin' THEN ".$c('order_amount').' - '.$c('total_tax_amount').' - '.$c('shipping_cost').' END',
            'company_shipping'=>$companyShipping,'vendor_shipping'=>$shipping,'vendor_cost'=>$c('shipping_seller_cost'),
            'shipping_cost'=>$this->has('orders','is_shipping_free') ? 'CASE WHEN r.is_shipping_free = 1 THEN 0 ELSE COALESCE('.$c('shipping_customer_cost').','.$c('shipping_cost').') END' : $c('shipping_cost'),
            'due_at'=>$tab === 'shipping_dues' ? $c('shipping_settlement_due_at') : 'COALESCE('.$s('due_at').','.$c('sales_settlement_due_at').')',
            'settlement_status'=>$s('status'),'settlement_amount'=>$s('amount'),'confirmed'=>$confirmed,
            'fulfilled'=>"CASE WHEN r.order_status = 'delivered' THEN 1 ELSE 0 END",'allocation'=>"'unallocated'",
        ]);
        if ($q && in_array($tab,['invoice_dues','shipping_dues'])) $q->where('r.seller_is','seller');
        if ($q && $tab === 'shipping_dues' && $this->has('orders','shipping_responsibility')) $q->where('r.shipping_responsibility','sellerwise_shipping');
        return $q;
    }

    private function packages(bool $vendor): ?Builder
    {
        $table = $vendor ? 'seller_package_subscriptions' : 'customer_subscription_payments';
        $c = fn ($name) => $this->col($table,$name);
        $catalog = $vendor ? 'seller_packages' : 'customer_purchase_packages';
        $package = $c($vendor ? 'seller_package_id' : 'package_id');
        $price = $this->has($catalog,'package_price') ? "(SELECT cp.package_price FROM $catalog cp WHERE cp.id = $package LIMIT 1)" : 'NULL';
        $status = $c($vendor ? 'payment_status' : 'status');
        $start = $vendor ? 'COALESCE('.$c('starts_at').','.$c('started_at').','.$c('activated_at').')' : 'NULL';
        $expiry = $c($vendor ? 'expires_at' : 'subscription_expires_at');
        $subscriptionStatus = $vendor ? $c('status') : 'NULL';
        if (!$vendor && $this->has($table,'target_subscription_id') && $this->has('customer_monthly_subscriptions','starts_at')) {
            $start = '(SELECT cs.starts_at FROM customer_monthly_subscriptions cs WHERE cs.id = r.target_subscription_id AND cs.customer_id = r.customer_id LIMIT 1)';
            // Upgrading truncates the replaced subscription's actual validity. Catalog or
            // payment snapshots must not make that replaced entitlement appear active.
            if ($this->has('customer_monthly_subscriptions','expires_at')) $expiry = 'COALESCE((SELECT cs.expires_at FROM customer_monthly_subscriptions cs WHERE cs.id = r.target_subscription_id AND cs.customer_id = r.customer_id LIMIT 1),'.$expiry.')';
            $now = DB::getPdo()->quote(now()->toDateTimeString());
            $subscriptionStatus = "CASE WHEN $status != 'approved' THEN $status WHEN $expiry IS NULL THEN 'unknown' WHEN $expiry <= $now THEN 'expired' WHEN $start > $now THEN 'scheduled' ELSE 'active' END";
        }
        return $this->projection($table,$vendor ? 'vendor_packages' : 'customer_packages',[
            'seller_id'=>$vendor ? $c('seller_id') : 'NULL','customer_id'=>$vendor ? 'NULL' : $c('customer_id'),
            'account_type'=>$vendor ? "'vendor'" : "'customer'",'date'=>'COALESCE('.$c($vendor ? 'activated_at' : 'approved_at').','.$c('created_at').')',
            'amount'=>$c($vendor ? 'paid_package_price' : 'quoted_amount'), 'currency'=>$vendor ? DB::getPdo()->quote($this->financials->baseCurrency()) : $c('currency_code'),
            'status'=>$status,'method'=>$c('payment_method'),'reference'=>$c('payment_reference'),'name'=>$c($vendor ? 'package_name' : 'terms_name'),
            'starts_at'=>$start,'expires_at'=>$expiry,'current_price'=>$price,
            'subscription_status'=>$subscriptionStatus,'confirmed'=>"CASE WHEN $status = '".($vendor ? 'paid' : 'approved')."' THEN 1 ELSE 0 END",
        ]);
    }

    private function returns(): ?Builder
    {
        // refund_transactions is the payment record; requests themselves are not additional payments.
        $table = 'refund_transactions';
        $c = fn ($name) => $this->col($table,$name);
        $seller = $this->has('orders','seller_is') ? "(SELECT o.seller_id FROM orders o WHERE o.id = r.order_id AND o.seller_is = 'seller')" : 'NULL';
        $customer = $this->has('orders','customer_id') ? '(SELECT o.customer_id FROM orders o WHERE o.id = r.order_id)' : 'NULL';
        return $this->projection($table,'refunds',[
            'order_id'=>$c('order_id'),'seller_id'=>$seller,'customer_id'=>$customer,'amount'=>$c('amount'),
            'status'=>$c('payment_status'),'method'=>$c('payment_method'),'reference'=>$c('refund_id'),
            'allocation'=>$c('paid_by'),'confirmed'=>"CASE WHEN r.payment_status = 'paid' THEN 1 ELSE 0 END",
            'subscription_status'=>$this->has('refund_requests','status') && $this->has($table,'refund_id') ? '(SELECT rr.status FROM refund_requests rr WHERE rr.id = r.refund_id)' : 'NULL',
        ]);
    }

    private function additionalReturns(): array
    {
        $queries = [];
        foreach ([
            ['order_admin_purchase_refunds','refund_admin','amount','status','completed','completed_at','reference'],
            ['order_refund_ledgers','refund_ledger','purchase_amount','status','available','created_at','idempotency_key'],
            ['order_edit_histories','refund_edits','order_return_amount','order_return_payment_status','returned','updated_at','order_return_transaction_ref'],
            ['refund_requests','refund_requests','amount','status','refunded','updated_at','id'],
        ] as [$table,$source,$amount,$status,$confirmed,$date,$reference]) {
            if (!$this->has($table,'id')) continue;
            $c = fn ($field) => $this->col($table,$field);
            $query = $this->projection($table,$source,[
                'order_id'=>$c('order_id'),'amount'=>$c($amount),'date'=>'COALESCE('.$c($date).','.$c('created_at').')','status'=>$c($status),'reference'=>$c($reference),
                'customer_id'=>'(SELECT o.customer_id FROM orders o WHERE o.id = r.order_id)',
                'seller_id'=>"(SELECT o.seller_id FROM orders o WHERE o.id = r.order_id AND o.seller_is = 'seller')",
                'confirmed'=>$source === 'refund_requests' ? '0' : "CASE WHEN ".$c($status)." = '$confirmed' THEN 1 ELSE 0 END",'allocation'=>"'unallocated'",
                'due_at'=>$c('due_at'),
            ]);
            if ($source === 'refund_edits') $query->where('r.'.$amount,'>',0);
            // A request with a payment transaction is represented by that transaction only.
            if ($source === 'refund_requests' && $this->has('refund_transactions','refund_id')) $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('refund_transactions as rt')->whereColumn('rt.refund_id','r.id'));
            $queries[] = $query;
        }
        return $queries;
    }

    public function query(string $tab, array $filters): Builder
    {
        $queries = $tab === 'packages' ? array_filter([$this->packages(false),$this->packages(true)])
            : ($tab === 'returns' ? array_filter([$this->returns(),...$this->additionalReturns()]) : array_filter([$this->orders($tab)]));
        if (!$queries) {
            $empty = DB::query();
            foreach (self::FIELDS as $field) $empty->selectRaw('NULL as '.$field);
            $union = $empty->whereRaw('1 = 0');
        } else {
            $union = array_shift($queries);
            foreach ($queries as $query) $union->unionAll($query);
        }
        $q = DB::query()->fromSub($union,'f');
        if (!empty($filters['from'])) $q->where('date','>=',$filters['from'].' 00:00:00');
        if (!empty($filters['to'])) $q->where('date','<',Carbon::parse($filters['to'])->addDay()->toDateString().' 00:00:00');
        foreach (['seller_id','customer_id','status','method','account_type','settlement_status'] as $field) {
            if (!empty($filters[$field])) $q->where($field,$filters[$field]);
        }
        if (!empty($filters['flow'])) $q->where('source',$filters['flow']);
        if (!empty($filters['due'])) {
            $today = now()->toDateString();
            match ($filters['due']) {
                'upcoming' => $q->where('due_at','>=',Carbon::parse($today)->addDay()->toDateString()),
                'due' => $q->where('due_at','>=',$today)->where('due_at','<',Carbon::parse($today)->addDay()->toDateString()),
                'overdue' => $q->where('due_at','<',$today),
            };
            $q->whereIn('settlement_status',['held','disputed']);
        }
        if (!empty($filters['q'])) $q->where(function ($q) use ($filters) {
            $term = '%'.$filters['q'].'%';
            $q->where('reference','like',$term)->orWhere('name','like',$term);
            if (ctype_digit($filters['q'])) $q->orWhere('id',(int)$filters['q'])->orWhere('order_id',(int)$filters['q']);
        });
        if (!empty($filters['metric'])) {
            $definition = $this->definitions($tab)[$filters['metric']] ?? null;
            abort_unless($definition,422);
            $q->whereNotNull($definition[0])->whereRaw($definition[1]);
        }
        return $q;
    }

    private function definitions(string $tab): array
    {
        return match ($tab) {
            'sales' => ['merchandise_sale'=>['merchandise_sale','confirmed = 1 AND fulfilled = 1'], 'procurement'=>['procurement','confirmed = 1 AND fulfilled = 1'], 'merchandise_margin'=>['merchandise_margin','confirmed = 1 AND fulfilled = 1'], 'commission'=>['commission','confirmed = 1 AND fulfilled = 1'], 'inhouse_revenue'=>['company_merchandise','confirmed = 1 AND fulfilled = 1'], 'collections'=>['amount','confirmed = 1'], 'unconfirmed'=>['amount','confirmed = 0']],
            'packages' => ['customer_packages'=>['amount',"confirmed = 1 AND account_type = 'customer'"], 'vendor_packages'=>['amount',"confirmed = 1 AND account_type = 'vendor'"]],
            'shipping' => ['vendor_shipping_cost'=>['vendor_cost','confirmed = 1 AND fulfilled = 1'],'charged'=>['shipping_cost','1 = 1'],'shipping_collected'=>['shipping_cost','confirmed = 1'],'shipping_company'=>['company_shipping','confirmed = 1 AND fulfilled = 1'],'shipping_vendor'=>['vendor_shipping','confirmed = 1 AND fulfilled = 1']],
            'invoice_dues' => ['invoice_entitlement'=>['amount','confirmed = 1 AND fulfilled = 1'],'held'=>['settlement_amount',"settlement_status IN ('held','disputed')"],'released'=>['settlement_amount',"settlement_status = 'released'"]],
            'shipping_dues' => ['shipping_vendor'=>['vendor_shipping','confirmed = 1 AND fulfilled = 1']],
            'returns' => ['refund_paid'=>['amount',"confirmed = 1 AND source = 'refunds'"],'refund_pending'=>['amount',"confirmed = 0 AND source = 'refunds'"],
                'refund_admin'=>['amount',"confirmed = 1 AND source = 'refund_admin'"],'refund_ledger'=>['amount',"confirmed = 1 AND source = 'refund_ledger'"],'refund_edits'=>['amount',"confirmed = 1 AND source = 'refund_edits'"]],
        };
    }

    public function totals(string $tab, array $filters): array
    {
        $q = $this->query($tab,$filters);
        $out = [];
        foreach ($this->definitions($tab) as $key=>[$column,$condition]) {
            $out[$key] = (clone $q)->whereRaw($condition)->whereNotNull($column)->select('currency')->selectRaw("SUM($column) as amount, COUNT(*) as records")->groupBy('currency')->get();
        }
        return $out;
    }

    public function workspace(array $filters): array
    {
        $tab = $filters['tab'] ?? 'incoming';
        if ($tab === 'vendor_debts') return app(VendorDebtReadService::class)->workspace($filters);
        if ($tab === 'overview') $tab = 'incoming';
        if ($tab === 'incoming') return app(FinancialActivityReadService::class)->workspace($filters) + compact('tab') + ['baseCurrency'=>$this->financials->baseCurrency()];
        if (in_array($tab,['vendor_accounts','settlements'])) return app(VendorFinancialsReadService::class)->workspace($filters) + compact('tab','filters') + ['totals'=>[],'records'=>null,'statuses'=>collect(),'methods'=>collect(),'baseCurrency'=>$this->financials->baseCurrency()];
        $totals = [];
        foreach ($tab === 'overview' ? ['sales','packages','shipping','invoice_dues','returns'] : [$tab] as $category) $totals[$category] = $this->totals($category,$filters);
        $records = $tab === 'overview' ? null : $this->query($tab,$filters)->orderByDesc('date')->orderBy('source')->orderByDesc('id')->paginate($filters['per_page'] ?? 20,['*'],'page',$filters['page'] ?? 1)->withQueryString();
        $names = ['vendor'=>collect(),'customer'=>collect()];
        if ($records) foreach (['vendor'=>['sellers','seller_id'],'customer'=>['users','customer_id']] as $type=>[$table,$key]) {
            if ($this->has($table,'f_name') && $this->has($table,'l_name')) $names[$type] = DB::table($table)->whereIn('id',$records->pluck($key)->filter()->unique())->get(['id','f_name','l_name'])->keyBy('id');
        }
        $options = $this->query($tab === 'overview' ? 'sales' : $tab,array_intersect_key($filters,array_flip(['from','to','seller_id','customer_id'])));
        $statuses = (clone $options)->whereNotNull('status')->distinct()->orderBy('status')->pluck('status');
        $methods = (clone $options)->whereNotNull('method')->distinct()->orderBy('method')->pluck('method');
        return compact('tab','filters','records','totals','names','statuses','methods') + ['vendorCards'=>$tab === 'overview' ? app(VendorFinancialsReadService::class)->summary($filters) : []] + ['missing'=>array_unique($this->missing),'baseCurrency'=>$this->financials->baseCurrency()];
    }
}
