<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderWorkItem;
use App\Services\OrderCancellationSettlementService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderCancellationController extends Controller
{
    public function sellerDebts(Request $request): View
    {
        $data = $request->validate(['seller_id' => 'nullable|integer|min:1']);
        $debts = DB::table('order_cancellation_settlements')
            ->where('canceled_by', 'seller')->where('seller_debt_amount', '>', 0)
            ->when($data['seller_id'] ?? null, fn ($query, $sellerId) => $query->where('seller_id', $sellerId))
            ->orderByDesc('id')->paginate(25)->withQueryString();
        $sellers = \App\Models\Seller::query()->with('shop')->whereIn('id', $debts->pluck('seller_id'))->get()->keyBy('id');
        return view('admin-views.order.center.seller-debts', compact('debts', 'sellers'));
    }

    public function index(Request $request): View
    {
        $data = $request->validate(['q' => 'nullable|string|max:100']);
        $items = OrderWorkItem::query()->where('status', 'canceled')
            ->with(['order.customer', 'line', 'seller.shop', 'events'])
            ->when($data['q'] ?? null, fn ($query, $term) => $query->where('order_id', ctype_digit($term) ? (int) $term : 0))
            ->latest('id')->paginate(25)->withQueryString();
        $settlements = DB::table('order_cancellation_settlements')
            ->whereIn('order_id', $items->getCollection()->pluck('order_id'))
            ->get()->keyBy('operation_key');
        $cancellationEvents = \App\Models\OrderLogisticsEvent::query()
            ->whereIn('order_id', $items->getCollection()->pluck('order_id'))
            ->where('order_status', 'canceled')->orderByDesc('id')->get()->groupBy('order_id');
        $vendorCancellations = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('vendor_fulfillment_request_items')) {
            $rows = DB::table('vendor_fulfillment_request_items as mapping')
                ->join('vendor_fulfillment_requests as request', 'request.id', '=', 'mapping.request_id')
                ->leftJoin('vendor_debt_charges as charge', 'charge.id', '=', 'request.penalty_charge_id')
                ->whereIn('mapping.order_detail_id', $items->getCollection()->pluck('order_detail_id'))
                ->where('request.status', 'canceled')
                ->get(['mapping.order_detail_id', 'request.id as request_id', 'request.canceled_at',
                    'charge.amount as fee_amount', 'charge.wallet_collected', 'charge.debt_assessed']);
            $events = DB::table('vendor_fulfillment_events')->whereIn('request_id', $rows->pluck('request_id'))
                ->where('event_type', 'canceled')->orderByDesc('id')->get()->keyBy('request_id');
            $vendorCancellations = $rows->filter(function ($row) use ($events) {
                $event = $events->get($row->request_id);
                return $event && (json_decode((string) $event->metadata, true)['actor'] ?? null) === 'seller';
            })->keyBy('order_detail_id');
        }
        return view('admin-views.order.center.cancellations', compact('items', 'settlements', 'cancellationEvents', 'vendorCancellations'));
    }

    public function refundCustomer(Request $request, Order $order, OrderCancellationSettlementService $service): RedirectResponse
    {
        $data = $request->validate([
            'refund_amount' => 'required|numeric|gt:0|max:10000000',
            'fee_amount' => 'required|numeric|min:0|max:10000000',
            'discount_amount' => 'required|numeric|min:0|max:10000000',
            'credit_expires_on' => 'required|date|after:today',
            'reason' => 'required|string|max:2000',
        ]);
        $service->refundCustomer($order->id, (float) $data['refund_amount'], (float) $data['fee_amount'],
            (float) $data['discount_amount'], Carbon::parse($data['credit_expires_on'])->endOfDay(),
            $data['reason'], (int) auth('admin')->id());
        return back()->with('success', 'تم رد المبلغ وتسجيل الرسوم وتاريخ انتهاء الرصيد.');
    }

    public function chargeSeller(Request $request, OrderWorkItem $item, OrderCancellationSettlementService $service): RedirectResponse
    {
        $data = $request->validate(['fee_amount' => 'required|numeric|min:0|max:10000000', 'reason' => 'required|string|max:2000']);
        $service->chargeSeller($item->id, (float) $data['fee_amount'], $data['reason'], (int) auth('admin')->id());
        return back()->with('success', 'تم تسجيل رسوم إلغاء التاجر والجزء المتبقي كمديونية.');
    }
}
