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
        $sellerCancellationOrders = \App\Models\OrderLogisticsEvent::query()
            ->whereIn('order_id', $items->getCollection()->pluck('order_id'))
            ->where('order_status', 'canceled')->where('actor_type', 'seller')
            ->get()->groupBy('order_id');
        return view('admin-views.order.center.cancellations', compact('items', 'settlements', 'sellerCancellationOrders'));
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
