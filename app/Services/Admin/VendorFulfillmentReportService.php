<?php

namespace App\Services\Admin;

use App\Services\VendorFulfillmentDecisionService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Administration-only, read-only projections. Never assess, collect or repair money here. */
class VendorFulfillmentReportService
{
    private function restricted(Builder $query, string $alias): void
    {
        $query->where(fn ($q) => $q->whereIn($alias.'.status', ['locked', 'pending_vendor'])
            ->orWhere(fn ($closed) => $closed->where($alias.'.status', 'canceled')->where($alias.'.portion', 'outside_slot')->whereNull($alias.'.unlocked_at')));
    }

    public function requests(array $filters): Builder
    {
        return DB::table('vendor_fulfillment_requests as r')->select('r.*')
            ->when(! empty($filters['seller_id']), fn ($q) => $q->where('r.seller_id', $filters['seller_id']))
            ->when(! empty($filters['order_id']), fn ($q) => $q->where('r.order_id', $filters['order_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('r.status', $filters['status']))
            ->when(! empty($filters['portion']), fn ($q) => $q->where('r.portion', $filters['portion']))
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('r.forwarded_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->whereDate('r.forwarded_at', '<=', $filters['to']))
            ->when(in_array($filters['restriction'] ?? '', ['restricted', 'partial'], true), function ($q) use ($filters) {
                $this->restricted($q, 'r');
                if ($filters['restriction'] === 'partial') {
                    $q->whereExists(function ($other) {
                        $other->selectRaw('1')->from('vendor_fulfillment_requests as a')->whereColumn('a.order_id', 'r.order_id')
                            ->whereColumn('a.seller_id', 'r.seller_id')->whereNotIn('a.status', ['locked', 'pending_vendor', 'canceled', 'rejected'])
                            ->where(fn ($access) => $access->where('a.portion', 'advertising')->orWhereNotNull('a.unlocked_at'));
                    });
                }
            });
    }

    private function seller(Builder $query, array $filters, string $column): Builder
    {
        return $query->when(! empty($filters['seller_id']), fn ($q) => $q->where($column, $filters['seller_id']));
    }

    public function charges(array $filters): Builder
    {
        $paid = DB::table('vendor_debt_repayments')->whereNull('reversed_at')->groupBy('charge_id')->selectRaw(
            "charge_id, SUM(amount) as collected, SUM(CASE WHEN collection_type = 'wallet' THEN amount ELSE 0 END) as wallet_paid, SUM(CASE WHEN collection_type = 'deposit' THEN amount ELSE 0 END) as deposit_paid");

        return $this->seller(DB::table('vendor_debt_charges as c')->leftJoinSub($paid, 'p', fn ($join) => $join->on('p.charge_id', '=', 'c.id')), $filters, 'c.seller_id')
            ->select('c.*')->selectRaw('COALESCE(p.wallet_paid,0) as actual_wallet_collected, COALESCE(p.deposit_paid,0) as deposit_recovered, ROUND(c.amount - COALESCE(p.collected,0) - c.outstanding,8) as reconciliation_difference')
            ->selectRaw('ROUND(c.amount - c.wallet_collected - c.debt_assessed,8) as assessment_difference, ROUND(c.wallet_collected - COALESCE(p.wallet_paid,0),8) as wallet_difference');
    }

    public function deposits(array $filters): Builder
    {
        $paid = DB::table('vendor_debt_repayments')->whereNull('reversed_at')->where('collection_type', 'deposit')->groupBy('deposit_id')->selectRaw('deposit_id, SUM(amount) as recovered');

        return $this->seller(DB::table('vendor_deposit_allocations as d')->leftJoinSub($paid, 'p', fn ($join) => $join->on('p.deposit_id', '=', 'd.deposit_id')), $filters, 'd.seller_id')
            ->whereNull('d.reversed_at')->select('d.*')->selectRaw('ROUND(d.gross_amount - d.debt_recovered - d.net_wallet_credit,8) as allocation_difference, ROUND(d.debt_recovered - COALESCE(p.recovered,0),8) as collection_difference');
    }

    public function reconciliation(array $filters): array
    {
        return DB::transaction(function () use ($filters) {
            // Match cumulative assessments to cumulative collections, independently of the activity date filter.
            $charges = DB::query()->fromSub($this->charges($filters), 'c');
            $deposits = DB::query()->fromSub($this->deposits($filters), 'd');
            $chargeIssues = (clone $charges)->where(fn ($q) => $q->where('reconciliation_difference', '<>', 0)->orWhere('assessment_difference', '<>', 0)->orWhere('wallet_difference', '<>', 0)->orWhere('outstanding', '<', 0));
            $depositIssues = (clone $deposits)->where(fn ($q) => $q->where('allocation_difference', '<>', 0)->orWhere('collection_difference', '<>', 0)->orWhere('net_wallet_credit', '<', 0));

            return ['charges_checked' => (clone $charges)->count(), 'deposits_checked' => (clone $deposits)->count(),
                'charge_issues' => (clone $chargeIssues)->count(), 'deposit_issues' => (clone $depositIssues)->count(),
                'charge_examples' => $chargeIssues->orderBy('id')->limit(20)->get(), 'deposit_examples' => $depositIssues->orderBy('id')->limit(20)->get()];
        });
    }

    public function workspace(array $filters): array
    {
        $query = $this->requests($filters);
        $counts = (clone $query)->reorder()->select('r.status')->selectRaw('COUNT(*) as total')->groupBy('r.status')->pluck('total', 'status');
        $requests = $query->orderByDesc('r.id')->paginate(25)->withQueryString();
        $events = DB::table('vendor_fulfillment_events')->whereIn('request_id', $requests->pluck('id'))->orderBy('id')->get()->groupBy('request_id');

        return compact('filters', 'counts', 'requests', 'events') + ['labels' => VendorFulfillmentDecisionService::LABELS, 'reconciliation' => $this->reconciliation($filters)];
    }

    public function exportRows(string $dataset, array $filters): iterable
    {
        if ($dataset === 'requests') {
            yield ['التنفيذ', 'الطلب', 'التاجر', 'الجزء وقت التحويل', 'الحالة', 'مستحق المنتجات', 'مستحق الشحن', 'تحويل الإدارة', 'موعد التعليق', 'تصنيف التعليق', 'فك القفل', 'القبول', 'الرفض', 'الإلغاء', 'التسليم', 'السبب', 'قاعدة الغرامة المحفوظة', 'تخصيص المساحة المحفوظ', 'سجل الأحداث'];
            foreach ($this->requests($filters)->orderBy('r.id')->cursor() as $r) {
                $history = DB::table('vendor_fulfillment_events')->where('request_id', $r->id)->orderBy('id')->get(['event_type', 'created_at', 'metadata']);
                yield [$r->id, $r->order_id, $r->seller_id, $r->portion, VendorFulfillmentDecisionService::LABELS[$r->status] ?? $r->status, $r->vendor_amount, $r->shipping_amount, $r->forwarded_at, $r->pending_due_at, $r->pending_at, $r->unlocked_at, $r->accepted_at, $r->rejected_at, $r->canceled_at, $r->delivered_at, $r->decision_reason, $r->penalty_rule_snapshot, $r->unlock_snapshot, $history->toJson(JSON_UNESCAPED_UNICODE)];
            }
        } elseif ($dataset === 'charges') {
            yield ['مرجع الرسم', 'التاجر', 'النوع', 'نوع المصدر', 'مرجع المصدر', 'الاستحقاق الأصلي', 'المحصل من المحفظة', 'المحصل من الإيداعات', 'الدين القائم', 'فرق المطابقة', 'فرق التقييم', 'فرق تحصيل المحفظة', 'تاريخ الاستحقاق', 'القاعدة المحفوظة'];
            foreach ($this->charges($filters)->orderBy('c.id')->cursor() as $r) {
                yield ['DEBT-'.$r->id, $r->seller_id, $r->kind, $r->source_type, $r->source_id, $r->amount, $r->actual_wallet_collected, $r->deposit_recovered, $r->outstanding, $r->reconciliation_difference, $r->assessment_difference, $r->wallet_difference, $r->assessed_at, $r->rule_snapshot];
            }
        } else {
            yield ['الإيداع', 'التاجر', 'المبلغ الإجمالي', 'الدين المسدد', 'صافي المحفظة', 'الدين الباقي وقت التأكيد', 'فرق التوزيع', 'فرق التحصيل', 'تاريخ التأكيد', 'مراجع الغرامات ورسوم المساحات المسددة'];
            foreach ($this->deposits($filters)->orderBy('d.id')->cursor() as $r) {
                $references = DB::table('vendor_debt_repayments as p')->join('vendor_debt_charges as c', 'c.id', '=', 'p.charge_id')->where('p.deposit_id', $r->deposit_id)->whereNull('p.reversed_at')->orderBy('p.id')->get(['p.charge_id', 'p.amount', 'c.kind', 'c.source_type', 'c.source_id']);
                yield ['DEP-'.$r->deposit_id, $r->seller_id, $r->gross_amount, $r->debt_recovered, $r->net_wallet_credit, $r->remaining_debt, $r->allocation_difference, $r->collection_difference, $r->confirmed_at, $references->toJson(JSON_UNESCAPED_UNICODE)];
            }
        }
    }
}
