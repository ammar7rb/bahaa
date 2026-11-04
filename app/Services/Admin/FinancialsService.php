<?php

namespace App\Services\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read models only. Never calls operational services which may settle or debit funds. */
class FinancialsService
{
    private array $columns = [];

    public function sources(bool $customers = false): array
    {
        $sources = [
            'orders' => ['table'=>'orders', 'amount'=>'order_amount', 'status'=>'payment_status', 'method'=>'payment_method', 'customer'=>'customer_id', 'seller'=>'seller_id', 'date'=>'created_at', 'reference'=>'transaction_ref', 'kind'=>'order_status'],
            'customer_packages' => ['table'=>'customer_subscription_payments', 'amount'=>'quoted_amount', 'status'=>'status', 'method'=>'payment_method', 'customer'=>'customer_id', 'date'=>'approved_at', 'reference'=>'payment_reference', 'currency'=>'currency_code', 'kind'=>'purchase_kind'],
            'vendor_packages' => ['table'=>'seller_package_subscriptions', 'amount'=>'paid_package_price', 'status'=>'payment_status', 'method'=>'payment_method', 'seller'=>'seller_id', 'date'=>'activated_at', 'reference'=>'payment_reference', 'kind'=>'status'],
            'extra_ads' => ['table'=>'vendor_extra_ad_purchases', 'amount'=>'total_price', 'status'=>'status', 'method'=>'payment_method', 'seller'=>'seller_id', 'date'=>'paid_at', 'reference'=>'payment_reference'],
            'deposits' => ['table'=>'customer_balance_deposits', 'amount'=>'amount', 'status'=>'status', 'method'=>'method_name', 'customer'=>'customer_id', 'date'=>'reviewed_at'],
            'vendor_deposits' => ['table'=>'seller_balance_deposits', 'amount'=>'amount', 'status'=>'status', 'method'=>'payment_method', 'seller'=>'seller_id', 'date'=>'paid_at', 'reference'=>'transaction_reference', 'currency'=>'currency_code'],
            'wallet' => ['table'=>'wallet_transactions', 'amount'=>'debit', 'method'=>'payment_method', 'customer'=>'user_id', 'date'=>'created_at', 'reference'=>'reference', 'kind'=>'transaction_type'],
            'settlements' => ['table'=>'seller_settlements', 'amount'=>'amount', 'status'=>'status', 'seller'=>'seller_id', 'date'=>'created_at', 'reference'=>'order_id'],
            'refunds' => ['table'=>'refund_transactions', 'amount'=>'amount', 'status'=>'payment_status', 'method'=>'payment_method', 'date'=>'created_at', 'reference'=>'order_id'],
            'vendor_ledger' => ['table'=>'seller_ledger_entries', 'amount'=>'amount', 'seller'=>'seller_id', 'date'=>'created_at', 'reference'=>'reference_code', 'kind'=>'event_type'],
        ];
        return $customers ? array_intersect_key($sources, array_flip(['orders','customer_packages','deposits','wallet','refunds'])) : $sources;
    }

    private function columns(string $table): array
    {
        return $this->columns[$table] ??= Schema::getColumnListing($table);
    }

    private function has(string $table, array $columns): bool
    {
        return !array_diff($columns, $this->columns($table));
    }

    public function ready(string $source): bool
    {
        $d = $this->sources()[$source];
        $required = ['id', 'created_at', $d['amount']];
        foreach (['status','method','customer','seller','currency','kind'] as $key) {
            if (isset($d[$key])) $required[] = $d[$key];
        }
        if ($source === 'deposits') $required[] = 'wallet_type';
        if ($source === 'orders') $required[] = 'seller_is';
        if ($source === 'vendor_deposits') $required[] = 'metadata';
        if ($source === 'wallet') $required = [...$required, 'credit','admin_bonus','balance'];
        if ($source === 'vendor_ledger') $required = [...$required, 'direction','bucket'];
        if ($source === 'refunds') $required[] = 'order_id';
        return $this->has($d['table'], $required);
    }

    /** Storage currency, not the administrator's selected display currency. */
    public function baseCurrency(): string
    {
        return getWebConfig('currency_model') === 'multi_currency' ? 'USD' : getCurrencyCode();
    }

    private function dateExpression(array $d): string
    {
        $table = $d['table'];
        return $d['date'] !== 'created_at' && $this->has($table, [$d['date']])
            ? 'COALESCE('.$table.'.'.$d['date'].', '.$table.'.created_at)'
            : $table.'.created_at';
    }

    public function query(string $source, array $filters = [], bool $period = true): Builder
    {
        $d = $this->sources()[$source];
        $table = $d['table'];
        $q = DB::table($table);
        // Retired security deposits are history in their original module, not current wallet funding.
        if ($source === 'deposits') $q->where('wallet_type', 'purchase');
        if ($source === 'vendor_deposits') $q->where('metadata->wallet_target','operating');
        foreach (['customer','seller'] as $party) {
            if (!empty($filters[$party.'_id'])) {
                if (isset($d[$party])) {
                    $q->where($table.'.'.$d[$party], $filters[$party.'_id']);
                    if ($source === 'orders' && $party === 'seller') $q->where('seller_is','seller');
                } elseif ($source === 'refunds') {
                    $q->whereIn('order_id', DB::table('orders')->select('id')->where($party.'_id',$filters[$party.'_id'])
                        ->when($party === 'seller', fn ($orders) => $orders->where('seller_is','seller')));
                } else {
                    $q->whereRaw('1 = 0');
                }
            }
        }
        if ($period) {
            if (!empty($filters['from'])) $q->whereRaw($this->dateExpression($d).' >= ?', [$filters['from'].' 00:00:00']);
            if (!empty($filters['to'])) $q->whereRaw($this->dateExpression($d).' < ?', [\Carbon\Carbon::parse($filters['to'])->addDay()->toDateString().' 00:00:00']);
        }
        return $q;
    }

    public function records(string $source, array $filters): LengthAwarePaginator
    {
        if (!$this->ready($source)) return new LengthAwarePaginator([], 0, $filters['per_page'] ?? 20, $filters['page'] ?? 1, ['path'=>request()->url(), 'query'=>request()->query()]);
        $d = $this->sources()[$source];
        $q = $this->query($source, $filters, empty($filters['all_dates']));
        if (!empty($filters['bucket'])) $source === 'vendor_ledger' ? $q->where('bucket',$filters['bucket']) : $q->whereRaw('1 = 0');
        if (!empty($filters['metric'])) $this->scopeMetricRecords($q,$source,$filters['metric']);
        if (!empty($filters['status'])) {
            isset($d['status']) ? $q->where($d['status'], $filters['status']) : $q->whereRaw('1 = 0');
        }
        if (!empty($filters['method'])) {
            isset($d['method']) ? $q->where($d['method'], $filters['method']) : $q->whereRaw('1 = 0');
        }
        if (!empty($filters['kind'])) {
            isset($d['kind']) ? $q->where($d['kind'], $filters['kind']) : $q->whereRaw('1 = 0');
        }
        if (!empty($filters['review'])) {
            $source === 'orders' && $this->has('orders',['customer_initial_payment_review_status'])
                ? $q->where('customer_initial_payment_review_status',$filters['review']) : $q->whereRaw('1 = 0');
        }
        if (!empty($filters['q'])) {
            $q->where(function (Builder $q) use ($filters, $d) {
                $q->where('id', ctype_digit($filters['q']) ? $filters['q'] : -1);
                if (isset($d['reference']) && $this->has($d['table'], [$d['reference']])) $q->orWhere($d['reference'], 'like', '%'.$filters['q'].'%');
            });
        }
        $page = $q->orderByRaw($this->dateExpression($d).' DESC')->orderByDesc('id')->paginate($filters['per_page'] ?? 20,['*'],'page',$filters['page'] ?? null)->withQueryString();
        $rows = $page->getCollection()->map(fn ($row) => $this->present($source, $row));
        $names = [];
        foreach (['customer'=>'users','seller'=>'sellers'] as $party=>$table) {
            $ids = $rows->pluck($party.'_id')->filter()->unique();
            $names[$party] = $ids->isNotEmpty() && $this->has($table,['id','f_name','l_name'])
                ? DB::table($table)->whereIn('id',$ids)->get(['id','f_name','l_name'])->keyBy('id') : collect();
        }
        $page->setCollection($rows->map(function ($row) use ($names) {
            foreach (['customer','seller'] as $party) {
                $person = $names[$party]->get($row[$party.'_id']);
                $row[$party.'_name'] = $person ? trim($person->f_name.' '.$person->l_name) : null;
            }
            return $row;
        }));
        return $page;
    }

    private function scopeMetricRecords(Builder $q, string $source, string $metric): void
    {
        $definitions = [
            'customer_package_fees'=>['customer_packages','status',['approved']],
            'vendor_package_fees'=>['vendor_packages','payment_status',['paid']],
            'extra_ad_fees'=>['extra_ads','status',['paid']],
            'paid_orders'=>['orders','payment_status',['paid']], 'unpaid_orders'=>['orders','payment_status',['unpaid']],
            'executed_refunds'=>['refunds','payment_status',['paid']],
            'approved_deposits'=>['deposits','status',['paid']], 'pending_deposits'=>['deposits','status',['pending','legacy_review']],
            'approved_vendor_deposits'=>['vendor_deposits','status',['paid']], 'pending_vendor_deposits'=>['vendor_deposits','status',['pending']],
            'pending_packages'=>['customer_packages','status',['pending_review']],
            'pending_vendor_packages'=>['vendor_packages','payment_status',['unpaid']], 'pending_extra_ads'=>['extra_ads','status',['pending_review']],
            'wallet_purchases'=>['wallet','transaction_type',['order_place','due_payment_for_order']],
            'wallet_refunds'=>['wallet','transaction_type',['order_refund','return_order_amount_by_admin']],
            'loyalty_credits'=>['wallet','transaction_type',['loyalty_point']],
        ];
        if (isset($definitions[$metric])) {
            [$expected,$column,$values]=$definitions[$metric]; abort_unless($source === $expected,422);
            $q->whereIn($column,$values);
            if ($metric === 'paid_orders') $this->confirmedOrders($q);
            if ($metric === 'pending_vendor_packages') $q->where('status','pending_review');
            return;
        }
        if ($metric === 'wallet_bonuses') { abort_unless($source === 'wallet',422); $q->where('admin_bonus','<>',0); return; }
        if ($metric === 'pending_order_review') { abort_unless($source === 'orders',422); $q->where('payment_status','paid')->where('customer_initial_payment_review_status','pending'); return; }
        if (str_starts_with($metric,'vendor_') && in_array(substr($metric,7),['pending','available','operating','pending_withdraw'])) { abort_unless($source === 'vendor_ledger',422); $q->where('bucket',substr($metric,7)); return; }
        if (str_starts_with($metric,'settlement_') && in_array(substr($metric,11),['held','disputed','released','reversed'])) { abort_unless($source === 'settlements',422); $q->where('status',substr($metric,11)); return; }
        // Legacy company components remain accessible by their existing source; no new profit formula.
        $components=['commissions','inhouse_sales','shipping_customer_cost','shipping_seller_cost','shipping_seller_entitlement','refer_and_earn_discount','deliveryman_charge','company_discounts','company_free_shipping'];
        abort_unless($source === 'orders' && in_array($metric,$components),422);
        $this->confirmedOrders($q)->where('order_status','delivered');
        if ($metric === 'commissions') $q->where('seller_is','seller');
        if ($metric === 'inhouse_sales') $q->where('seller_is','admin');
        if ($metric === 'company_discounts') $q->where(fn($q)=>$q->where('coupon_discount_bearer','inhouse')->orWhere('seller_is','admin'));
        if ($metric === 'company_free_shipping') $q->where('free_delivery_bearer','admin')->where('is_shipping_free',1);
    }

    private function confirmedOrders(Builder $q): Builder
    {
        $q->where('payment_status','paid');
        if ($this->has('orders',['customer_initial_payment_review_status'])) $q->where(fn($q)=>$q->whereNull('customer_initial_payment_review_status')->orWhere('customer_initial_payment_review_status','approved'));
        if ($this->has('orders',['edited_status']) && $this->has('order_edit_histories',['order_id','order_due_amount','order_due_payment_status'])) {
            $q->whereRaw("NOT (orders.edited_status IS NOT NULL AND orders.edited_status = 1 AND EXISTS (SELECT 1 FROM order_edit_histories eh WHERE eh.order_id = orders.id AND eh.id = (SELECT MAX(le.id) FROM order_edit_histories le WHERE le.order_id = orders.id) AND eh.order_due_payment_status = 'unpaid' AND eh.order_due_amount > 0))");
        }
        return $q;
    }

    private function present(string $source, object $row): array
    {
        $d = $this->sources()[$source];
        return [
            'id'=>$row->id, 'source'=>$source,
            'reference'=>(isset($d['reference']) ? ($row->{$d['reference']} ?? null) : null) ?: '#'.$row->id,
            'customer_id'=>isset($d['customer']) ? $row->{$d['customer']} : null,
            'seller_id'=>isset($d['seller']) && !($source === 'orders' && $row->seller_is === 'admin') ? $row->{$d['seller']} : null,
            'amount'=>$row->{$d['amount']}, 'currency'=>$source === 'extra_ads' && property_exists($row,'currency') ? ($row->currency ?: '—') : (isset($d['currency']) ? ($row->{$d['currency']} ?: '—') : $this->baseCurrency()),
            'status'=>isset($d['status']) ? $row->{$d['status']} : 'recorded',
            'method'=>isset($d['method']) ? $row->{$d['method']} : null,
            'kind'=>isset($d['kind']) ? $row->{$d['kind']} : null,
            'date'=>$row->{$d['date']} ?? $row->created_at,
            'credit'=>$row->credit ?? null, 'bonus'=>$row->admin_bonus ?? null, 'balance'=>$row->balance ?? null,
            'direction'=>$row->direction ?? null, 'bucket'=>$row->bucket ?? null,
            'review'=>$source === 'orders' ? ($row->customer_initial_payment_review_status ?? null) : null,
        ];
    }

    public function workspace(array $filters, bool $customers): array
    {
        $sources = $this->sources($customers);
        $unavailable = array_keys(array_filter($sources, fn ($d, $key) => !$this->ready($key), ARRAY_FILTER_USE_BOTH));
        $source = $filters['source'];
        $options = ['statuses'=>[], 'methods'=>[], 'kinds'=>[], 'reviews'=>[]];
        if ($this->ready($source)) {
            $d = $sources[$source];
            foreach (['status'=>'statuses','method'=>'methods','kind'=>'kinds'] as $key=>$name) {
                if (isset($d[$key])) $options[$name] = $this->query($source, $filters)->whereNotNull($d[$key])->distinct()->orderBy($d[$key])->pluck($d[$key])->all();
            }
            if ($source === 'orders' && $this->has('orders',['customer_initial_payment_review_status'])) {
                $options['reviews'] = $this->query($source,$filters)->whereNotNull('customer_initial_payment_review_status')->distinct()->orderBy('customer_initial_payment_review_status')->pluck('customer_initial_payment_review_status')->all();
            }
        }
        $accounts = $customers && ($filters['tab'] ?? 'accounts') === 'accounts' ? $this->accounts($filters) : null;
        return compact('filters','customers','sources','unavailable','options','accounts') + [
            'records'=>$accounts ? null : $this->records($source, $filters),
            'groups'=>$this->metrics($filters, $customers), 'baseCurrency'=>$this->baseCurrency(),
        ];
    }

    /** Each metric has its own source and status; these figures must never be added into one cash/profit total. */
    public function metrics(array $filters, bool $customers): array
    {
        $groups = [];
        $add = function (string $group, string $key, string $source, string $column, callable $scope, bool $period = true, bool $currencyColumn = false) use (&$groups, $filters) {
            $table = $this->sources()[$source]['table'];
            $value = null;
            if ($this->ready($source) && $this->has($table, [$column])) {
                $query = $scope($this->query($source, $filters, $period));
                $value = $currencyColumn
                    ? $query->select('currency_code')->selectRaw('SUM('.$column.') AS total')->groupBy('currency_code')->get()->map(fn ($r) => ['amount'=>$r->total, 'currency'=>$r->currency_code ?: '—'])->all()
                    : [['amount'=>$query->sum($column), 'currency'=>$this->baseCurrency()]];
            }
            $groups[$group][] = compact('key','source','value','period');
        };
        if (!$customers) {
            $delivered = fn ($q) => $this->confirmedOrders($q)->where('order_status','delivered');
            if ($this->ready('orders') && $this->has('order_vendor_pricings',['order_id','seller_id','merchandise_sale','procurement_amount'])) {
                $q = $delivered($this->query('orders',$filters))->where('seller_is','seller');
                $expression = 'COALESCE((SELECT vp.merchandise_sale - vp.procurement_amount FROM order_vendor_pricings vp WHERE vp.order_id = orders.id AND vp.seller_id = orders.seller_id LIMIT 1), orders.admin_commission)';
                $groups['revenue'][] = ['key'=>'commissions','source'=>'orders','period'=>true,'value'=>[['amount'=>$q->selectRaw('COALESCE(SUM('.$expression.'),0) AS total')->value('total'),'currency'=>$this->baseCurrency()]]];
            } else $add('revenue','commissions','orders','admin_commission',fn ($q) => $delivered($q)->where('seller_is','seller'));
            // In-house merchandise sales exclude tax and shipping, but are not profit (COGS is not available).
            if ($this->ready('orders') && $this->has('orders',['total_tax_amount','shipping_cost'])) {
                $q = $delivered($this->query('orders',$filters))->where('seller_is','admin');
                $groups['revenue'][] = ['key'=>'inhouse_sales','source'=>'orders','period'=>true,'value'=>[['amount'=>$q->selectRaw('COALESCE(SUM(order_amount - total_tax_amount - shipping_cost),0) AS total')->value('total'),'currency'=>$this->baseCurrency()]]];
            }
        }
        $add($customers ? 'activity' : 'revenue','customer_package_fees','customer_packages','quoted_amount',fn ($q) => $q->where('status','approved'),true,true);
        if (!$customers) {
            $add('revenue','vendor_package_fees','vendor_packages','paid_package_price',fn ($q) => $q->where('payment_status','paid'));
            $add('revenue','extra_ad_fees','extra_ads','total_price',fn ($q) => $q->where('status','paid'));
        }
        $add('activity','paid_orders','orders','order_amount',fn ($q) => $this->confirmedOrders($q));
        $add('activity','unpaid_orders','orders','order_amount',fn ($q) => $q->where('payment_status','unpaid'));
        $add('activity','executed_refunds','refunds','amount',fn ($q) => $q->where('payment_status','paid'));
        $add('activity','approved_deposits','deposits','amount',fn ($q) => $q->where('status','paid'));
        if (!$customers) $add('activity','approved_vendor_deposits','vendor_deposits','amount',fn ($q) => $q->where('status','paid'),true,true);
        if ($customers) {
            $add('activity','wallet_purchases','wallet','debit',fn ($q) => $q->whereIn('transaction_type',['order_place','due_payment_for_order']));
            $add('activity','wallet_refunds','wallet','credit',fn ($q) => $q->whereIn('transaction_type',['order_refund','return_order_amount_by_admin']));
            $add('activity','wallet_bonuses','wallet','admin_bonus',fn ($q) => $q);
            $add('activity','loyalty_credits','wallet','credit',fn ($q) => $q->where('transaction_type','loyalty_point'));
        } else {
            foreach (['shipping_customer_cost','shipping_seller_cost','shipping_seller_entitlement','refer_and_earn_discount','deliveryman_charge'] as $column) {
                $add('costs',$column,'orders',$column,$delivered);
            }
            if ($this->has('orders',['discount_amount','coupon_discount_bearer','seller_is'])) {
                $add('costs','company_discounts','orders','discount_amount',fn ($q) => $delivered($q)->where(fn ($q) => $q->where('coupon_discount_bearer','inhouse')->orWhere('seller_is','admin')));
            }
            if ($this->has('orders',['extra_discount','free_delivery_bearer','is_shipping_free'])) {
                $add('costs','company_free_shipping','orders','extra_discount',fn ($q) => $delivered($q)->where('free_delivery_bearer','admin')->where('is_shipping_free',1));
            }
            foreach (['held','disputed','released','reversed'] as $status) {
                $add('settlements','settlement_'.$status,'settlements','amount',fn ($q) => $q->where('status',$status));
            }
            foreach (['pending','available','operating','pending_withdraw'] as $bucket) {
                if (!$this->ready('vendor_ledger')) {
                    $value = null;
                } else {
                    $value = [['amount'=>$this->query('vendor_ledger',$filters,false)->where('bucket',$bucket)
                        ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END),0) AS total")->value('total'), 'currency'=>$this->baseCurrency()]];
                }
                $groups['balances'][] = ['key'=>'vendor_'.$bucket,'source'=>'vendor_ledger','value'=>$value,'period'=>false];
            }
        }
        $balance = $this->has('users',['wallet_balance']) ? $this->accountQuery($filters, false)->sum('wallet_balance') : null;
        $groups['balances'][] = ['key'=>'customer_wallets','source'=>'wallet','period'=>false,'value'=>$balance === null ? null : [['amount'=>$balance,'currency'=>$this->baseCurrency()]]];
        $add('pending','pending_deposits','deposits','amount',fn ($q) => $q->whereIn('status',['pending','legacy_review']));
        $add('pending','pending_packages','customer_packages','quoted_amount',fn ($q) => $q->where('status','pending_review'),true,true);
        if ($this->has('orders',['customer_initial_payment_review_status'])) {
            $add('pending','pending_order_review','orders','order_amount',fn ($q) => $q->where('payment_status','paid')->where('customer_initial_payment_review_status','pending'));
        }
        if (!$customers) {
            $add('pending','pending_vendor_deposits','vendor_deposits','amount',fn ($q) => $q->where('status','pending'),true,true);
            $add('pending','pending_vendor_packages','vendor_packages','paid_package_price',fn ($q) => $q->where('payment_status','unpaid')->where('status','pending_review'));
            $add('pending','pending_extra_ads','extra_ads','total_price',fn ($q) => $q->where('status','pending_review'));
        }
        return $groups;
    }

    private function accountQuery(array $filters, bool $search = true): Builder
    {
        $q = DB::table('users')->where('id','>',0);
        if (!empty($filters['seller_id'])) $q->whereRaw('1 = 0');
        if (!empty($filters['customer_id'])) $q->where('id',$filters['customer_id']);
        if ($search && !empty($filters['q'])) $q->where(function ($q) use ($filters) {
            $q->where('f_name','like','%'.$filters['q'].'%')->orWhere('l_name','like','%'.$filters['q'].'%')->orWhere('phone','like','%'.$filters['q'].'%');
            if (ctype_digit($filters['q'])) $q->orWhere('id',$filters['q']);
        });
        return $q;
    }

    public function accounts(array $filters): LengthAwarePaginator
    {
        $page = $this->accountQuery($filters)->select(['id','f_name','l_name','phone','wallet_balance'])->orderByDesc('id')->paginate($filters['per_page'] ?? 20,['*'],'page',$filters['page'] ?? null)->withQueryString();
        $subscriptions = collect();
        if ($this->has('customer_monthly_subscriptions',['customer_id','package_id','starts_at','expires_at','granted_allowance','additional_allowance','committed_allowance'])) {
            $subscriptions = DB::table('customer_monthly_subscriptions')->whereIn('customer_id',$page->pluck('id'))
                ->whereNotNull('package_id')->where('starts_at','<=',now())->where('expires_at','>',now())->orderByDesc('id')->get()->unique('customer_id')->keyBy('customer_id');
        }
        $page->getCollection()->transform(function ($customer) use ($subscriptions) {
            $sub = $subscriptions->get($customer->id);
            $customer->allowance = $sub ? (float)$sub->granted_allowance + (float)$sub->additional_allowance : 0;
            $customer->committed = $sub ? (float)$sub->committed_allowance : 0;
            $customer->remaining = max(0,$customer->allowance - $customer->committed);
            $customer->expires_at = $sub->expires_at ?? null;
            return $customer;
        });
        return $page;
    }

    public function entry(string $source, int $id): array
    {
        abort_unless(isset($this->sources()[$source]),404);
        abort_unless($this->ready($source),503,__('financials.unavailable'));
        $row = $this->query($source,[],false)->where('id',$id)->first();
        abort_unless($row,404);
        $entry = $this->present($source,$row);
        // Deliberate allowlist: no proofs, gateway payloads, tokens, or private method_information.
        $fields = ['created_at','approved_at','reviewed_at','activated_at','paid_at','order_id','seller_is','order_status',
            'customer_initial_payment_review_status','admin_commission','shipping_cost','shipping_customer_cost','shipping_seller_cost',
            'shipping_seller_entitlement','total_tax_amount','discount_amount','refer_and_earn_discount','extra_discount',
            'terms_name','package_name','purchase_kind','quantity','payment_status','status','submitted_amount','currency_code',
            'wallet_debit_amount','granted_allowance','allowance_before','allowance_after','wallet_balance_after','balance',
            'credit','debit','admin_bonus','bucket','direction','event_type','reporting_category','reference_code','reference',
            'delivered_at','due_at','released_at','reversed_at','dispute_until','customer_note'];
        $details = array_intersect_key((array)$row,array_flip($fields));
        if ($source === 'orders' && $this->has('order_vendor_pricings',['order_id','seller_id','procurement_amount','merchandise_sale'])) {
            $pricing=DB::table('order_vendor_pricings')->where('order_id',$id)->where('seller_id',$row->seller_id)->first();
            if ($pricing) $details += ['approved_procurement'=>$pricing->procurement_amount, 'approved_merchandise_sale'=>$pricing->merchandise_sale,
                'approved_merchandise_margin'=>round((float)$pricing->merchandise_sale-(float)$pricing->procurement_amount,8),
                'procurement_approved_at'=>$pricing->approved_at ?? null, 'procurement_approved_by'=>$pricing->admin_id ?? null];
        }

        $orderIds = [];
        if ($source === 'orders') $orderIds = [$id];
        elseif (!empty($row->order_id)) $orderIds = [$row->order_id];
        elseif ($source === 'vendor_ledger' && ($row->reference_type ?? null) === \App\Models\Order::class && !empty($row->reference_id)) {
            $orderIds = DB::table('orders')->where('id',$row->reference_id)->where('seller_id',$row->seller_id)->where('seller_is','seller')->pluck('id')->all();
        }
        elseif ($source === 'wallet' && !empty($row->order_ids)) {
            $ids = json_decode($row->order_ids,true);
            if (is_array($ids)) $orderIds = DB::table('orders')->where('customer_id',$row->user_id)->whereIn('id',array_filter($ids,'is_numeric'))->pluck('id')->all();
        }
        $related = [];
        if ($source === 'wallet' && preg_match('/^customer-offline-deposit-(\d+)$/',(string)($row->reference ?? ''),$match) && $this->ready('deposits')) {
            $deposit = $this->query('deposits',[],false)->where('id',(int)$match[1])->where('customer_id',$row->user_id)->first();
            if ($deposit) $related[] = $this->present('deposits',$deposit);
        }
        if ($source === 'customer_packages' && $this->ready('wallet') && $this->has('wallet_transactions',['customer_subscription_payment_id'])) {
            $related = $this->query('wallet',[],false)->where('user_id',$row->customer_id)->where('customer_subscription_payment_id',$id)->get()->map(fn ($r) => $this->present('wallet',$r))->all();
        }
        if ($source === 'wallet' && !empty($row->customer_subscription_payment_id) && $this->ready('customer_packages')) {
            $payment = $this->query('customer_packages',[],false)->where('id',$row->customer_subscription_payment_id)->where('customer_id',$row->user_id)->first();
            if ($payment) $related[] = $this->present('customer_packages',$payment);
        }
        return compact('entry','details','orderIds','related') + ['baseCurrency'=>$this->baseCurrency()];
    }
}
