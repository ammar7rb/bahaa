<?php

namespace App\Services\Admin;

use App\Services\VendorDebtService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VendorDebtReadService
{
    public function workspace(array $filters): array
    {
        $ready = app(VendorDebtService::class)->available();
        $summary = array_fill_keys(['assessed','wallet_collected','deposit_recovered','outstanding','gross_deposits','net_wallet_credit'], '0.00000000');
        $records = new LengthAwarePaginator([], 0, 20);
        if ($ready) {
            $scope = fn ($query, $column = 'created_at') => $query
                ->when(!empty($filters['seller_id']), fn ($q) => $q->where('seller_id', $filters['seller_id']))
                ->when(!empty($filters['from']), fn ($q) => $q->whereDate($column, '>=', $filters['from']))
                ->when(!empty($filters['to']), fn ($q) => $q->whereDate($column, '<=', $filters['to']));
            $summary['assessed'] = (string) $scope(DB::table('vendor_debt_charges'), 'assessed_at')->sum('amount');
            $summary['outstanding'] = (string) DB::table('vendor_debt_charges')->when(!empty($filters['seller_id']), fn ($q) => $q->where('seller_id', $filters['seller_id']))->sum('outstanding');
            $collections = $scope(DB::table('vendor_debt_repayments'))->whereNull('reversed_at');
            $summary['wallet_collected'] = (string) (clone $collections)->where('collection_type', 'wallet')->sum('amount');
            $summary['deposit_recovered'] = (string) (clone $collections)->where('collection_type', 'deposit')->sum('amount');
            $deposits = $scope(DB::table('vendor_deposit_allocations'), 'confirmed_at')->whereNull('reversed_at');
            $summary['gross_deposits'] = (string) (clone $deposits)->sum('gross_amount');
            $summary['net_wallet_credit'] = (string) (clone $deposits)->sum('net_wallet_credit');
            $events = DB::table('vendor_debt_events as e')->leftJoin('vendor_debt_charges as c', 'c.id', '=', 'e.charge_id')
                ->when(!empty($filters['seller_id']), fn ($q) => $q->where('e.seller_id', $filters['seller_id']))
                ->when(!empty($filters['from']), fn ($q) => $q->whereDate('e.created_at', '>=', $filters['from']))
                ->when(!empty($filters['to']), fn ($q) => $q->whereDate('e.created_at', '<=', $filters['to']))
                ->when(!empty($filters['event_type']), fn ($q) => $q->where('e.event_type', $filters['event_type']))
                ->select(['e.*','c.kind','c.source_type','c.source_id','c.outstanding']);
            $records = $events->orderByDesc('e.id')->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1)->withQueryString();
        }
        return ['tab' => 'vendor_debts', 'filters' => $filters, 'debtSummary' => $summary, 'debtRecords' => $records,
            'baseCurrency' => 'EGP', 'missing' => !$ready, 'totals' => [], 'records' => null, 'statuses' => collect(), 'methods' => collect()];
    }
}
