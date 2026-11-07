<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\VendorAutoRenewalService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class VendorAutoRenewalController extends Controller
{
    public function enable(Request $request, VendorAutoRenewalService $service)
    {
        abort_unless(config('vendor_advertising.auto_renewal_enabled') && Schema::hasTable('vendor_auto_renewals'), 404);
        $data = $request->validate(['subscription_id' => 'required|integer|min:1', 'expected_price' => 'required|numeric|gt:0']);
        $seller = $request->seller ?? auth('seller')->user();
        abort_unless($seller, 401);
        try { $service->enable($seller, (int) $data['subscription_id'], (float) $data['expected_price']); }
        catch (DomainException $error) { throw ValidationException::withMessages(['renewal' => $error->getMessage()]); }
        return $request->is('api/*') ? response()->json(['enabled' => true]) : back()->with('success', 'تم تفعيل التجديد التلقائي.');
    }
}
