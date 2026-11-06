<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
class OrderTrackingController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth('customer')->check(),401);
        $request->validate(['order_id'=>'nullable|integer|min:1']);
        $query=Order::where('customer_id',auth('customer')->id());
        if ($request->filled('order_id')) {
            $query->whereKey($request->integer('order_id'));
            abort_unless((clone $query)->exists(),404);
        }
        return view('customer-account.tracking',['orders'=>$query->latest('id')->paginate(12)->withQueryString()]);
    }
}
