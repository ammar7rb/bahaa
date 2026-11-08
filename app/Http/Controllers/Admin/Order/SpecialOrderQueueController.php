<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use App\Models\MedicalDeviceInquiry;
use App\Models\MedicalDeviceReportPurchase;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketConv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SpecialOrderQueueController extends Controller
{
    public function guests(Request $request): View
    {
        $data = $request->validate(['q' => 'nullable|string|max:100']);
        $orders = Order::query()->where('is_guest', 1)->with(['customer', 'details'])
            ->when($data['q'] ?? null, fn ($query, $term) => $query->where(fn ($search) => $search
                ->where('id', ctype_digit($term) ? (int) $term : 0)
                ->orWhere('shipping_address_data', 'like', '%'.$term.'%')))
            ->latest('id')->paginate(25)->withQueryString();
        return view('admin-views.order.center.guests', compact('orders'));
    }

    public function credit(Request $request): View
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('people'), 403);
        $data = $request->validate(['q' => 'nullable|string|max:100']);
        $tickets = SupportTicket::query()->where('purpose', 'credit_limit_request')
            ->with(['customer', 'latestConversation'])
            ->when($data['q'] ?? null, fn ($query, $term) => $query->whereHas('customer',
                fn ($customer) => $customer->where('f_name', 'like', '%'.$term.'%')
                    ->orWhere('l_name', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')))
            ->latest('id')->paginate(25)->withQueryString();
        return view('admin-views.order.center.credit', compact('tickets'));
    }

    public function decideCredit(Request $request, int $ticket): RedirectResponse
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('people'), 403);
        $data = $request->validate([
            'decision' => 'required|in:approved,rejected,needs_information',
            'message' => 'required|string|min:2|max:2000',
        ]);
        DB::transaction(function () use ($ticket, $data): void {
            $record = SupportTicket::query()->where('purpose', 'credit_limit_request')
                ->lockForUpdate()->findOrFail($ticket);
            if ($record->reviewed_at && $record->review_status !== 'needs_information') {
                throw \Illuminate\Validation\ValidationException::withMessages(['decision' => 'تم تسجيل قرار لهذا الطلب بالفعل.']);
            }
            $record->forceFill([
                'review_status' => $data['decision'], 'review_note' => $data['message'],
                'reviewed_by_admin_id' => (int) auth('admin')->id(), 'reviewed_at' => now(),
                'status' => $data['decision'] === 'needs_information' ? 'open' : 'close',
                'closed_at' => $data['decision'] === 'needs_information' ? null : now(),
            ])->save();
            SupportTicketConv::query()->create([
                'support_ticket_id' => $record->id, 'admin_id' => (int) auth('admin')->id(),
                'admin_message' => $data['message'], 'attachment' => [],
            ]);
        }, 3);
        return back()->with('success', 'تم تسجيل القرار وإرسال الرسالة للعميل.');
    }

    public function inspections(Request $request): View
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('catalog') && \App\Utils\Helpers::module_permission_check('people'), 403);
        $data = $request->validate(['q' => 'nullable|string|max:100']);
        $inquiries = MedicalDeviceInquiry::query()->with(['customer', 'device', 'reportPurchase', 'conversation'])
            ->whereHas('reportPurchase')
            ->when($data['q'] ?? null, fn ($query, $term) => $query->where(fn ($search) => $search
                ->where('device_name', 'like', '%'.$term.'%')
                ->orWhereHas('customer', fn ($customer) => $customer->where('phone', 'like', '%'.$term.'%'))))
            ->latest('id')->paginate(25)->withQueryString();
        return view('admin-views.order.center.medical', ['inquiries' => $inquiries, 'purchaseQueue' => false]);
    }

    public function purchases(Request $request): View
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('catalog') && \App\Utils\Helpers::module_permission_check('people'), 403);
        $data = $request->validate(['q' => 'nullable|string|max:100']);
        $inquiries = MedicalDeviceInquiry::query()->with(['customer', 'device', 'reportPurchase', 'conversation'])
            ->whereHas('reportPurchase', fn ($query) => $query->whereNotNull('purchase_requested_at'))
            ->when($data['q'] ?? null, fn ($query, $term) => $query->where(fn ($search) => $search
                ->where('device_name', 'like', '%'.$term.'%')
                ->orWhereHas('customer', fn ($customer) => $customer->where('phone', 'like', '%'.$term.'%'))))
            ->latest('id')->paginate(25)->withQueryString();
        return view('admin-views.order.center.medical', ['inquiries' => $inquiries, 'purchaseQueue' => true]);
    }

    public function confirmInspection(MedicalDeviceReportPurchase $purchase, \App\Services\MedicalDeviceReportService $service): RedirectResponse
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('catalog') && \App\Utils\Helpers::module_permission_check('people'), 403);
        $service->confirmPayment($purchase->id, (int) auth('admin')->id());
        return back()->with('success', 'تم تأكيد دفع رسوم الفحص.');
    }
}
