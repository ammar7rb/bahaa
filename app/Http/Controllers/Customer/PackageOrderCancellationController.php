<?php
namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerPackageCancellationService;
use Illuminate\Http\Request;

class PackageOrderCancellationController extends Controller
{
    public function request(Request $request, int $order, CustomerPackageCancellationService $service)
    {
        $customer = $request->is('api/*') ? $request->user('api') : $request->user('customer');
        abort_unless($customer, 401);
        $refund = $service->request((int) $customer->id, $order);
        $payload = ['message' => 'تم إلغاء الطلب وطلب رد المبلغ. ستراجعه الإدارة خلال ١٥ إلى ٣٠ يومًا من تاريخ الطلب.',
            'refund' => ['id' => $refund->id, 'amount' => (float) $refund->amount, 'status' => $refund->status,
                'requested_at' => $refund->created_at?->toIso8601String(), 'due_at' => $refund->due_at?->toIso8601String()]];
        return $request->is('api/*') ? response()->json($payload) : back()->with('customer_cancel_message', $payload['message']);
    }
}
