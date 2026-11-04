<?php
namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use App\Models\OrderAdminPurchaseRefund;
use App\Services\CustomerPackageCancellationService;
use Illuminate\Http\Request;

class CustomerPackageRefundController extends Controller
{
    public function index()
    {
        $refunds = OrderAdminPurchaseRefund::query()->with('order')
            ->where('reason', 'customer_canceled_waiting_for_package')
            ->orderByRaw("CASE WHEN status = 'customer_requested' THEN 0 ELSE 1 END")
            ->orderBy('due_at')->paginate(25);
        return view('admin-views.order.customer-package-refunds', compact('refunds'));
    }

    public function complete(Request $request, int $refund, CustomerPackageCancellationService $service)
    {
        $data = $request->validate([
            'method' => ['required', 'in:purchase_wallet,external'],
            'reference' => ['nullable', 'string', 'max:191', 'required_if:method,external'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->complete($refund, (int) auth('admin')->id(), $data['method'], $data['reference'] ?? null, $data['note'] ?? null);
        return back()->with('success', 'تم تسجيل تنفيذ رد المبلغ.');
    }
}
