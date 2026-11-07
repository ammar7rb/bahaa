<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CompanyMedicalDevice;
use App\Services\MedicalDeviceInquiryService;
use Illuminate\Http\Request;

class MedicalDeviceController extends Controller
{
    public function index()
    {
        return view('medical-devices.index', ['devices' => CompanyMedicalDevice::published()->paginate(24)]);
    }

    public function show(int $device)
    {
        $purchase = auth('customer')->check() ? \App\Models\MedicalDeviceReportPurchase::where('device_id', $device)->where('customer_id', auth('customer')->id())->first() : null;
        return view('medical-devices.show', ['device' => CompanyMedicalDevice::published()->findOrFail($device), 'reportPurchase' => $purchase]);
    }

    public function inquire(Request $request, int $device, \App\Services\MedicalDeviceReportService $service)
    {
        CompanyMedicalDevice::published()->findOrFail($device);
        if (! auth('customer')->check()) {
            $url = route('medical-devices.show', $device);
            session()->put('keep_customer_login_redirect_url', $url);
            // The existing sign-in flow records browsing history and validates the return origin.
            $history = session('recent_user_routes_history', []);
            $history[] = $url;
            session()->put('recent_user_routes_history', $history);

            return redirect()->route('customer.auth.login');
        }
        abort_unless(auth('customer')->user()->is_active && ! auth('customer')->user()->account_deleted_at, 403);
        $data = $request->validate(['report_price' => 'required|numeric|gt:0']);
        $purchase = $service->pay($device, (int) auth('customer')->id(), (float) $data['report_price']);
        $inquiry = \App\Models\MedicalDeviceInquiry::findOrFail($purchase->inquiry_id);

        return redirect()->route('medical-devices.show', $device)->with('device_inquiry_id', $inquiry->id)
            ->with('device_conversation_id', $inquiry->support_ticket_id);
    }

    public function purchaseRequest(int $device, \App\Services\MedicalDeviceReportService $service)
    {
        CompanyMedicalDevice::published()->findOrFail($device);
        $service->requestPurchase($device, (int) auth('customer')->id());
        return redirect()->route('medical-devices.show', $device)->with('purchase_message', 'تم استلام طلبكم وسيتم التواصل معكم في أقرب وقت.');
    }
}
