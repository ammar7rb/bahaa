<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CustomerCancellationStockService;
use App\Services\OrderLogisticsService;
use App\Services\OrderWorkItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PaidOrderCancellationController extends Controller
{
    public function request(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:2000']);
        $customerId = (int) auth('customer')->id();
        DB::transaction(function () use ($order, $customerId, $data): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ((int) $locked->customer_id !== $customerId || $locked->is_guest) abort(404);
            $previousStatus = (string) $locked->order_status;
            if ($locked->payment_status !== 'paid' || !in_array($previousStatus, ['pending', 'confirmed', 'processing'], true)
                || $locked->customer_cancel_request_status === 'pending'
                || app(\App\Services\VendorFulfillmentDecisionService::class)->managed($locked)
                || $locked->shipment_reference || $locked->third_party_delivery_tracking_id
                || (Schema::hasTable('seller_settlements') && DB::table('seller_settlements')->where('order_id', $locked->id)->exists())
                || (Schema::hasTable('order_shipping_proofs') && DB::table('order_shipping_proofs')->where('order_id', $locked->id)->exists())
                || (Schema::hasTable('order_admin_purchase_refunds') && DB::table('order_admin_purchase_refunds')->where('order_id', $locked->id)->exists())
                || (Schema::hasTable('order_refund_ledgers') && DB::table('order_refund_ledgers')->where('order_id', $locked->id)->exists())
                || (Schema::hasTable('refund_requests') && DB::table('refund_requests')->where('order_id', $locked->id)->exists())) {
                throw ValidationException::withMessages(['reason' => 'حالة الطلب أو طلب رد سابق لا تسمح بالإلغاء من الحساب. تواصل مع الإدارة.']);
            }
            app(CustomerCancellationStockService::class)->restore($locked);
            $locked->forceFill([
                'order_status' => 'canceled', 'customer_cancel_request_status' => 'pending',
                'commerce_flow_status' => 'purchase_refund_pending',
                'commerce_flow_status_updated_at' => now(),
                'operational_blocked_by' => 'customer_cancellation',
                'operational_block_reason' => trim($data['reason']),
            ])->save();
            app(OrderLogisticsService::class)->recordOrderStatus($locked, 'canceled', 'customer', $customerId, $previousStatus, false, trim($data['reason']));
            $workItems = app(OrderWorkItemService::class);
            foreach ($locked->details as $line) $workItems->ensureForLine($line);
            $workItems->syncOrderStatus($locked->fresh());
        }, 3);
        return back()->with('customer_cancel_message', 'تم إلغاء الطلب وإرساله إلى الإدارة لمراجعة مبلغ الرد والرسوم.');
    }
}
