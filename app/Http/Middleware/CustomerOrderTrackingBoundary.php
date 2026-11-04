<?php
namespace App\Http\Middleware;

use App\Models\Order;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderTrackingBoundary
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->validate(['order_id'=>['required','integer','min:1']]);
        $order = Order::find($request->input('order_id'));
        abort_unless($order,404);
        $customer = auth('api')->user() ?? auth('customer')->user();
        $owner = $customer && !$order->is_guest && (int)$order->customer_id === (int)$customer->id;
        if ($order->is_guest && $request->filled('guest_id') && (string)$order->customer_id !== '0') {
            $owner = hash_equals((string)$order->customer_id,(string)$request->input('guest_id'));
        }
        $method = $request->route()?->getActionMethod();
        $phone = preg_replace('/\D/', '', (string)$request->input('phone_number'));
        $phones = [(string)data_get($order->shipping_address_data,'phone'), (string)data_get($order->billing_address_data,'phone')];
        $phoneMatch = false;
        foreach ($phones as $value) {
            $stored = preg_replace('/\D/','',$value);
            if (strlen($phone)>=10 && strlen($stored)>=10 && hash_equals(substr($stored,-10),substr($phone,-10))) $phoneMatch=true;
        }
        if (in_array($method, ['order_cancel', 'getOrderById', 'get_order_details', 'getOrderInvoice'], true)) abort_unless($owner,404);
        else abort_unless($owner || $phoneMatch,404);
        if ($customer) $request->merge(['user'=>$customer]);
        if (!$request->is('api/*') && !$owner) {
            return response()->view('customer-account.public-tracking', ['tracking'=>[
                'id'=>$order->id,'status'=>$order->order_status,
                'created_at'=>$order->created_at?->format('Y-m-d H:i'),
                'history'=>$order->orderStatusHistory->map(fn($row)=>['status'=>$row->status,'at'=>$row->created_at?->format('Y-m-d H:i')])->all(),
            ]]);
        }
        if ($method === 'track_by_order_id') return response()->json([
            'id'=>$order->id,'order_status'=>$order->order_status,'created_at'=>$order->created_at,
            'expected_delivery_date'=>$order->expected_delivery_date,
            'order_status_history'=>$order->orderStatusHistory->map(fn($row)=>['status'=>$row->status,'created_at'=>$row->created_at])]);
        // A phone is known to couriers and vendors. It authorizes status tracking,
        // never customer prices, editorial descriptions or order payment evidence.
        if ($method === 'track_order' && !$owner) return response()->json([[
            'id'=>0,'order_id'=>$order->id,'qty'=>0,'price'=>0,'tax'=>0,'discount'=>0,'product_details'=>null,
            'order'=>['id'=>$order->id,'order_status'=>$order->order_status,'created_at'=>$order->created_at]
        ]]);
        $response=$next($request);
        if ($response instanceof JsonResponse) $response->setData($this->sanitize($response->getData(true)));
        return $response;
    }
    private function sanitize(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        foreach (['vendor_unit_price_snapshot','vendor_content_revision','admin_commission','shipping_seller_cost',
            'shipping_seller_entitlement','seller_shipping_allocation','admin_order_review_note','shipping_decision_note',
            'pickup_address_snapshot','shipping_price_snapshot','seller_order_insurance','admin_gate_decisions','shipping_decisions'] as $key) unset($value[$key]);
        foreach ($value as $key=>$item) $value[$key]=$this->sanitize($item);
        return $value;
    }
}
