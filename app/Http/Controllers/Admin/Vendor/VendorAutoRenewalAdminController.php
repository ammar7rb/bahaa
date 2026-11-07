<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Services\VendorAutoRenewalService;
use App\Utils\Helpers;
use Illuminate\Support\Facades\Schema;

class VendorAutoRenewalAdminController extends Controller
{
    public function disable(int $seller, VendorAutoRenewalService $service)
    {
        abort_unless(Helpers::module_permission_check('people'), 403);
        abort_unless(config('vendor_advertising.auto_renewal_enabled') && Schema::hasTable('vendor_auto_renewals'), 404);
        $service->disableByAdmin($seller, (int) auth('admin')->id());
        return back()->with('success', 'تم إيقاف التجديد التلقائي.');
    }
}
