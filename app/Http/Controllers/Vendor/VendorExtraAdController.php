<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Devrabiul\ToastMagic\Facades\ToastMagic;

class VendorExtraAdController extends Controller
{
    public function store(Request $request)
    {
        $request->merge(['seller' => auth('seller')->user()]);
        $response = app(\App\Http\Controllers\RestAPI\v3\seller\VendorExtraAdController::class)->store($request);
        $data = $response->getData(true);
        if ($response->getStatusCode() >= 400) return back()->withErrors(['extra_ad' => $data['message']]);
        ToastMagic::success($data['message']);
        return back();
    }
}
