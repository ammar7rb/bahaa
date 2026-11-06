<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCancellationRequestController extends Controller
{
    public function store(Request $request, ?int $id = null)
    {
        $api = $request->is('api/*');
        $customerId = $api ? $request->user('api')->id : auth('customer')->id();
        $orderId = $id ?? $request->validate(['order_id' => 'required|integer|min:1'])['order_id'];

        DB::transaction(function () use ($customerId, $orderId) {
            $order = Order::where('customer_id', $customerId)->where('is_guest', 0)->lockForUpdate()->findOrFail($orderId);
            if ($order->customer_cancel_request_status === 'pending') {
                return;
            }
            if ($order->customer_cancel_request_status || !in_array($order->order_status, ['pending', 'confirmed', 'processing', 'out_for_delivery'], true)) {
                throw ValidationException::withMessages(['order' => 'لا يمكن طلب إلغاء هذا الطلب حاليًا.']);
            }

            SupportTicket::create([
                'customer_id' => $customerId,
                'subject' => 'طلب إلغاء الطلب #'.$order->id,
                'description' => 'طلب العميل إلغاء الطلب #'.$order->id.'. راجع تفاصيل الطلب لاتخاذ القرار.',
                'type' => 'problem_report',
                'priority' => 'normal',
                'status' => 'open',
                'attachment' => [],
            ]);
            $order->customer_cancel_request_status = 'pending';
            $order->save();
        });

        $message = 'تم إرسال طلب الإلغاء إلى الإدارة للمراجعة.';
        return $api ? response()->json(['message' => $message]) : back()->with('customer_cancel_message', $message);
    }
}
