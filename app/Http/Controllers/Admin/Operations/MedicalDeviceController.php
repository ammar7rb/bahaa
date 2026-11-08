<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Http\Controllers\Controller;
use App\Models\CompanyMedicalDevice;
use App\Models\MedicalDeviceInquiry;
use Illuminate\Http\Request;

class MedicalDeviceController extends Controller
{
    public function index()
    {
        return view('admin-views.operations.devices', ['devices' => CompanyMedicalDevice::orderBy('sort_order')->orderBy('id')->paginate(20)]);
    }

    public function edit(?CompanyMedicalDevice $device = null)
    {
        return view('admin-views.operations.device-edit', ['device' => $device ?? new CompanyMedicalDevice]);
    }

    public function save(Request $request, ?CompanyMedicalDevice $device = null)
    {
        $data = $request->validate(['name' => 'required|string|max:200', 'description' => 'nullable|string|max:20000',
            'specifications' => 'nullable|string|max:20000', 'is_published' => 'required|boolean', 'sort_order' => 'required|integer|min:0|max:100000',
            'price' => 'required|numeric|min:0|max:999999999999', 'report_price' => 'required|numeric|gt:0|max:999999999999',
            'device_condition' => 'required|in:new,used', 'origin_country' => ['required', \Illuminate\Validation\Rule::in(array_column(COUNTRIES, 'code'))],
            'images' => 'nullable|array|max:10', 'images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_images' => 'nullable|array', 'remove_images.*' => 'integer|min:0|max:9']);
        $device ??= new CompanyMedicalDevice;
        $images = collect($device->images ?? [])->reject(fn ($image, $index) => in_array($index, array_map('intval', $data['remove_images'] ?? []), true))->values()->all();
        if (count($images) + count($request->file('images', [])) > 10) {
            return back()->withErrors(['images' => __('operations.image_limit')])->withInput();
        }
        foreach ($request->file('images', []) as $image) {
            $images[] = $image->store('medical-devices', 'public');
        }
        unset($data['remove_images']);
        $data['images'] = $images;
        $device->fill($data)->save();

        return redirect()->route('admin.operations.medical-devices.index')->with('operations_success', __('operations.saved'));
    }

    public function delete(CompanyMedicalDevice $device)
    {
        $device->delete();

        return back()->with('operations_success', __('operations.saved'));
    }

    public function inquiries(Request $request)
    {
        $data = $request->validate(['status' => 'nullable|in:new,following_up,closed', 'q' => 'nullable|string|max:100']);
        $inquiries = MedicalDeviceInquiry::with(['customer', 'device', 'conversation', 'reportPurchase'])
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('device_name', 'like', '%'.$term.'%')->orWhereHas('customer', fn ($q) => $q->where('f_name', 'like', '%'.$term.'%')->orWhere('l_name', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))))
            ->latest()->paginate(25)->withQueryString();

        return view('admin-views.operations.inquiries', compact('inquiries'));
    }

    public function inquiry(MedicalDeviceInquiry $inquiry)
    {
        return view('admin-views.operations.inquiry', ['inquiry' => $inquiry->load(['customer', 'device', 'conversation']),
            'reportPurchase' => \App\Models\MedicalDeviceReportPurchase::where('inquiry_id', $inquiry->id)->first()]);
    }

    public function sendReport(Request $request, MedicalDeviceInquiry $inquiry, \App\Services\MedicalDeviceReportService $service)
    {
        $request->validate(['report' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp|max:20480']);
        $service->sendReport($inquiry, $request->file('report'), (int) auth('admin')->id());
        return back()->with('operations_success', 'تم إرسال التقرير في محادثة العميل.');
    }

    public function status(Request $request, MedicalDeviceInquiry $inquiry)
    {
        $inquiry->update($request->validate(['status' => 'required|in:new,following_up,closed']));

        return back()->with('operations_success', __('operations.saved'));
    }
}
