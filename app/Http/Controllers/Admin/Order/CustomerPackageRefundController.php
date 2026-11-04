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
        $refundDays = $this->refundDays();

        return view('admin-views.order.customer-package-refunds', compact('refunds', 'refundDays'));
    }

    private function refundDays(): int
    {
        $value = \App\Models\BusinessSetting::where('type', 'customer_original_payment_refund_days')->value('value');

        return max(15, min(30, (int) ($value ?? 30)));
    }

    public function settings(Request $request)
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('business_settings'), 403);
        $data = $request->validate(['days' => 'required|integer|between:15,30']);
        \App\Models\BusinessSetting::updateOrCreate(
            ['type' => 'customer_original_payment_refund_days'],
            ['value' => (string) $data['days']],
        );

        return back()->with('success', 'تم حفظ مهلة رد مدفوعات العملاء.');
    }

    public function approve(Request $request, int $refund, CustomerPackageCancellationService $service)
    {
        $service->approve($refund, (int) auth('admin')->id(), $this->refundDays());

        return back()->with('success', 'تم اعتماد رد المبلغ وتحديد موعده.');
    }

    public function complete(Request $request, int $refund, CustomerPackageCancellationService $service)
    {
        $data = $request->validate([
            'method' => ['required', 'in:external'],
            'reference' => ['nullable', 'string', 'max:191', 'required_if:method,external'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->complete($refund, (int) auth('admin')->id(), $data['method'], $data['reference'] ?? null, $data['note'] ?? null);
        return back()->with('success', 'تم تسجيل تنفيذ رد المبلغ.');
    }
}
