<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use App\Models\OrderWorkItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderCenterController extends Controller
{
    private const QUEUES = ['all', 'awaiting_admin', 'sent_to_vendor', 'accepted', 'processing', 'out_for_delivery', 'returned', 'failed', 'rejected', 'overdue', 'canceled', 'completed', 'guests', 'history'];

    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable','string','max:255'], 'seller_id' => ['nullable','integer','min:1'],
            'from' => ['nullable','date'], 'to' => ['nullable','date','after_or_equal:from']]);
        $queue = \App\Support\OrderCenterFilters::normalize(is_string($request->query('queue')) ? $request->query('queue') : null);
        $query = OrderWorkItem::query()->with([
            'order.customer', 'order.seller.shop', 'line.seller.shop', 'line.productAllStatus',
        ])->whereHas('order')->whereHas('line');

        $this->applyQueue($query, $queue);
        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(function (Builder $search) use ($term): void {
                $id = preg_replace('/\D+/', '', $term);
                if ($id !== '') $search->orWhere('order_id', (int) $id)->orWhere('order_detail_id', (int) $id);
                $search->orWhereHas('order', fn (Builder $order) => $order
                    ->where('transaction_ref', 'like', "%{$term}%")
                    ->orWhere('order_group_id', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn (Builder $customer) => $customer
                        ->where('f_name', 'like', "%{$term}%")->orWhere('l_name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%"))
                    ->orWhereHas('seller.shop', fn (Builder $shop) => $shop->where('name', 'like', "%{$term}%")));
                $search->orWhereHas('line', fn (Builder $line) => $line->where('product_details', 'like', "%{$term}%"));
                $search->orWhereHas('seller.shop', fn (Builder $shop) => $shop->where('name', 'like', "%{$term}%"));
            });
        }
        if ($request->filled('from')) $query->whereDate('order_work_items.created_at', '>=', $request->date('from')->toDateString());
        if ($request->filled('to')) $query->whereDate('order_work_items.created_at', '<=', $request->date('to')->toDateString());
        if ($request->filled('seller_id') && ctype_digit((string) $request->query('seller_id'))) $query->where('seller_id', (int) $request->query('seller_id'));

        $items = $query->orderByDesc('order_work_items.updated_at')->paginate(25)->withQueryString();
        $groups = $items->getCollection()->pluck('order.order_group_id')->filter()->unique()->values();
        $references = [];
        if ($groups->isNotEmpty()) {
            $allGroupItems = OrderWorkItem::query()->join('orders', 'orders.id', '=', 'order_work_items.order_id')
                ->whereIn('orders.order_group_id', $groups)->orderBy('order_work_items.id')
                ->get(['order_work_items.id', 'orders.order_group_id'])->groupBy('order_group_id');
            foreach ($allGroupItems as $groupId => $groupItems) {
                $total = $groupItems->count();
                foreach ($groupItems->values() as $position => $groupItem) {
                    $references[$groupItem->id] = $total === 1 ? $groupId :
                        ($total < 10 ? 'Mv'.$total.($position + 1) : 'Mv'.$total.'-'.($position + 1)).'-'.$groupId;
                }
            }
        }
        $counts = [];
        foreach (array_keys(\App\Support\OrderCenterFilters::OPTIONS) as $key) {
            $countQuery = OrderWorkItem::query()->whereHas('order')->whereHas('line');
            $this->applyQueue($countQuery, $key);
            $counts[$key] = $countQuery->count();
        }

        return view('admin-views.order.center.index', compact('items', 'counts', 'queue', 'references'));
    }

    private function applyQueue(Builder $query, string $queue): void
    {
        match ($queue) {
            'waiting_vendor' => $query->whereIn('status', ['sent_to_vendor', 'overdue']),
            'active' => $query->where('status', 'accepted')->whereHas('order', fn (Builder $order) => $order->whereNotIn('order_status', ['returned','failed','canceled','delivered'])),
            'stopped' => $query->where(fn (Builder $q) => $q->whereIn('status', ['rejected','canceled'])->orWhereHas('order', fn (Builder $order) => $order->whereIn('order_status', ['returned','failed']))),
            'processing', 'out_for_delivery', 'returned', 'failed' => $query->whereHas('order', fn (Builder $order) => $order->where('order_status', $queue)),
            'awaiting_admin' => $query->where('status', 'awaiting_admin'),
            'sent_to_vendor' => $query->where('status', 'sent_to_vendor')->where(fn (Builder $q) => $q->whereNull('vendor_response_due_at')->orWhere('vendor_response_due_at', '>=', now())),
            'accepted' => $query->where('status', 'accepted'),
            'rejected' => $query->where('status', 'rejected'),
            'overdue' => $query->where(fn (Builder $q) => $q->where('status', 'overdue')->orWhere(fn (Builder $sent) => $sent->where('status', 'sent_to_vendor')->whereNotNull('vendor_response_due_at')->where('vendor_response_due_at', '<', now()))),
            'canceled' => $query->where('status', 'canceled'),
            'completed' => $query->where('status', 'completed'),
            'guests' => $query->whereHas('order', fn (Builder $order) => $order->where('is_guest', true)),
            'history' => $query->where('status', 'completed')->where('order_work_items.updated_at', '<', now()->subDays(90)),
            default => null,
        };
    }
}
