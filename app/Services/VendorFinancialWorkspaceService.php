<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SellerOrderInsurance;
use App\Models\SellerSettlement;
use Carbon\Carbon;

/** Seller-safe financial records; sales and deposit returns never share totals. */
class VendorFinancialWorkspaceService
{
    public function transactions(int $sellerId, int $page = 1, ?string $bucket = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        // Never expose order identifiers or private ledger metadata from locked orders.
        return \App\Models\SellerLedgerEntry::where('seller_id', $sellerId)
            ->whereIn('bucket', [SellerLedgerService::AVAILABLE, SellerLedgerService::OPERATING, SellerLedgerService::ORDER_INSURANCE_CREDIT])
            ->when(in_array($bucket, [SellerLedgerService::AVAILABLE, SellerLedgerService::OPERATING, SellerLedgerService::ORDER_INSURANCE_CREDIT], true), fn ($q) => $q->where('bucket', $bucket))
            ->latest('id')->paginate(20, ['id', 'bucket', 'direction', 'amount', 'created_at', 'event_type', 'reference_type', 'reference_id'], 'transactions_page', max(1, $page))->through(fn ($entry) => $entry->makeHidden(['event_type', 'reference_type', 'reference_id']));
    }

    public function invoices(int $sellerId, int $page = 1): array
    {
        $base = SellerSettlement::where('seller_id', $sellerId)->whereHas('order', fn ($q) => $q->sellerAccessible());
        $visible = (clone $base)->whereHas('order', fn ($q) => $q->whereDoesntHave('sellerOrderInsurance', fn ($insurance) => $insurance->whereNotIn('status', ['paid', 'waived'])));
        $pending = (clone $visible)->whereIn('status', ['held', 'disputed']);
        $upcoming = (clone $pending)->where('status', 'held')->where('due_at', '>', now());
        $nearest = (clone $upcoming)->min('due_at');
        $summary = [
            'total_receivables' => (float) (clone $pending)->sum('amount'),
            'upcoming_receivables' => (float) (clone $upcoming)->sum('amount'),
            'settled_total' => (float) (clone $visible)->where('status', 'released')->sum('amount'),
            'next_due_at' => $nearest,
            'next_due_amount' => $nearest ? (float) (clone $upcoming)->whereDate('due_at', Carbon::parse($nearest)->toDateString())->sum('amount') : 0,
        ];
        $records = $base->with('order.sellerOrderInsurance')->latest('id')->paginate(20, ['*'], 'page', max(1, $page));
        $records->through(function ($item) {
            $order = $item->order;
            $visible = app(SellerOrderVisibilityService::class)->canSellerView($order);
            $reference = $visible ? (string) $order->id : $this->lockedReference($order);
            // Locked rows contain only the permitted public projection.
            return $visible ? [
                'invoice_number' => AdministrativeReferenceService::format('invoice', (int) $order->id), 'receivable' => (float) $item->amount,
                'due_at' => $item->due_at?->toIso8601String(), 'status' => $item->status,
                'order_reference' => $reference, 'locked' => false,
                'action_url' => route('vendor.orders.details', $reference),
            ] : ['locked' => true, 'order_number_suffix' => substr((string) $order->id, -3),
                'order_amount' => (float) $order->order_amount, 'security_deposit_amount' => (float) $order->sellerOrderInsurance?->amount,
                'order_reference' => $reference, 'action_url' => route('vendor.orders.details', $reference)];
        });
        return ['summary' => $summary, 'records' => $records];
    }

    public function securityDeposits(int $sellerId, int $page = 1): array
    {
        $base = SellerOrderInsurance::where('seller_id', $sellerId)->whereNotNull('paid_at');
        $returning = (clone $base)->where('status', 'paid')->whereNull('reusable_released_at')->where(fn ($q) => $q->whereNull('confiscation_status')->orWhere('confiscation_status', '<>', 'confiscated'));
        $nearest = (clone $returning)->where('reusable_at', '>', now())->min('reusable_at');
        $summary = [
            'total_paid' => (float) (clone $base)->sum('amount'),
            'held_total' => (float) (clone $returning)->sum(\Illuminate\Support\Facades\DB::raw('amount - COALESCE(confiscated_amount, 0)')),
            'next_return_at' => $nearest,
            'next_return_amount' => $nearest ? (float) (clone $returning)->whereDate('reusable_at', Carbon::parse($nearest)->toDateString())->sum(\Illuminate\Support\Facades\DB::raw('amount - COALESCE(confiscated_amount, 0)')) : 0,
            'return_destination' => 'insurance_only',
        ];
        $records = $base->with('order.sellerOrderInsurance')->latest('id')->paginate(20, ['*'], 'security_page', max(1, $page));
        $records->through(function ($item) {
            $visible = $item->order && app(SellerOrderVisibilityService::class)->canSellerView($item->order);
            return [
                'reference' => $item->payment_reference ?: 'DEP-'.$item->id,
                'amount' => (float) $item->amount, 'paid_at' => $item->paid_at?->toIso8601String(),
                'invoice_number' => $visible ? AdministrativeReferenceService::format('invoice', (int) $item->order_id) : null,
                'order_reference' => $visible ? (string) $item->order_id : null,
                'invoice_url' => $visible ? route('vendor.orders.generate-invoice', $item->order_id) : null,
                'return_at' => $item->reusable_at?->toIso8601String(),
                'status' => $item->confiscation_status === 'confiscated' ? 'confiscated' : ($item->reusable_released_at ? 'returned_to_security_balance' : 'held'),
            ];
        });
        return ['summary' => $summary, 'records' => $records];
    }

    private function lockedReference(Order $order): string
    {
        if (!$order->seller_restricted_access_token) {
            $order->seller_restricted_access_token = (string) \Illuminate\Support\Str::uuid();
            $order->save();
        }
        return $order->seller_restricted_access_token;
    }
}
