<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\CompanyMedicalDevice;
use App\Models\MedicalDeviceReportPurchase;
use App\Services\MedicalDeviceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalDeviceApiController extends Controller
{
    public function index(): JsonResponse
    {
        $devices = CompanyMedicalDevice::published()->latest('id')->paginate(20);

        return response()->json($devices);
    }

    public function show(Request $request, int $device): JsonResponse
    {
        $record = CompanyMedicalDevice::published()->findOrFail($device);
        $purchase = $request->user()
            ? MedicalDeviceReportPurchase::where('device_id', $device)->where('customer_id', $request->user()->id)->first()
            : null;

        return response()->json([
            'device' => $record,
            'report_purchase' => $purchase,
            'can_request_purchase' => (bool) ($purchase?->report_sent_at),
            'purchase_message' => 'تم استلام طلبكم وسيتم التواصل معكم في أقرب وقت.',
        ]);
    }

    public function payReport(Request $request, int $device, MedicalDeviceReportService $service): JsonResponse
    {
        CompanyMedicalDevice::published()->findOrFail($device);
        $data = $request->validate(['report_price' => 'required|numeric|gt:0']);
        $purchase = $service->pay($device, (int) $request->user()->id, (float) $data['report_price']);

        return response()->json(['purchase' => $purchase], 201);
    }

    public function purchase(Request $request, int $device, MedicalDeviceReportService $service): JsonResponse
    {
        CompanyMedicalDevice::published()->findOrFail($device);
        $purchase = $service->requestPurchase($device, (int) $request->user()->id);

        return response()->json([
            'message' => 'تم استلام طلبكم وسيتم التواصل معكم في أقرب وقت.',
            'purchase' => $purchase,
        ]);
    }
}
