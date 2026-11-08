<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCommunicationLinkController extends Controller
{
    public function store(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['channel'=>'required|in:support_ticket,vendor_thread,chat_message', 'resource_id'=>'required|integer|min:1']);
        $id = (int) $data['resource_id'];
        $valid = match ($data['channel']) {
            'support_ticket' => $order->customer_id && DB::table('support_tickets')->where('id', $id)->where('customer_id', $order->customer_id)->exists(),
            'vendor_thread' => $order->seller_is === 'seller' && DB::table('vendor_support_threads')->where('id', $id)->where('seller_id', $order->seller_id)->exists(),
            'chat_message' => $order->customer_id && DB::table('chattings')->where('id', $id)->where('user_id', $order->customer_id)
                ->where(fn ($q) => $q->whereNull('seller_id')->orWhere('seller_id', 0)->orWhere('seller_id', $order->seller_id))->exists(),
        };
        if (!$valid) throw ValidationException::withMessages(['resource_id'=>'المحادثة غير موجودة أو لا تخص أطراف هذا الطلب.']);
        DB::table('order_communication_links')->insertOrIgnore(['order_id'=>$order->id, 'channel'=>$data['channel'],
            'resource_id'=>$id, 'admin_id'=>(int) auth('admin')->id(), 'created_at'=>now()]);
        return back()->with('success','تم ربط المحادثة بسجل الطلب.');
    }
}
