<?php

namespace App\Http\Controllers\RestAPI\v3\seller;

use App\Http\Controllers\Controller;
use App\Models\{OfflinePaymentMethod, VendorExtraAdPurchase};
use App\Services\VendorExtraAdService;
use App\Traits\FileManagerTrait;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VendorExtraAdController extends Controller
{
    use FileManagerTrait;

    public function index(Request $request)
    {
        abort_unless(config('vendor_advertising.enabled'), 404);
        return response()->json(['settings' => app(VendorExtraAdService::class)->settings(),
            'purchases' => VendorExtraAdPurchase::where('seller_id', $request->seller->id)->latest('id')->paginate(20)]);
    }

    public function store(Request $request)
    {
        abort_unless(config('vendor_advertising.enabled'), 404);
        $data = $request->validate([
            'subscription_id' => 'required|integer|min:1', 'quantity' => 'required|integer|min:1|max:10000',
            'expected_unit_price' => 'required|numeric|min:0.01|max:999999999.99',
            'request_key' => 'required|uuid', 'payment_method' => 'required|in:operating_balance,offline_payment',
            'method_id' => 'required_if:payment_method,offline_payment|nullable|integer',
            'payment_proof' => 'required_if:payment_method,offline_payment|nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'method_information' => 'nullable|array|max:30', 'method_informations' => 'nullable|string|max:10000',
            'payment_note' => 'nullable|string|max:1000',
        ]);
        $data['expected_unit_price'] = number_format((float) $data['expected_unit_price'], 2, '.', '');
        $proof = null;
        // An already committed request returns its original result before file upload.
        $existing = VendorExtraAdPurchase::where('seller_id', $request->seller->id)->where('request_key', $data['request_key'])->exists();
        if ($data['payment_method'] === 'offline_payment' && !$existing) {
            $method = OfflinePaymentMethod::whereKey($data['method_id'])->where('status', 1)->whereIn('payment_channel', ['wallet', 'instapay'])->firstOrFail();
            $info = app(\App\Services\VendorOfflinePaymentInputService::class)->information($request, $method);
            $proof = ['transfer_reference'=>app(\App\Services\VendorOfflinePaymentInputService::class)->reference($request,$info), 'method_id' => $method->id, 'method_name' => $method->method_name, 'method_information' => $info,
                'payment_note' => $data['payment_note'] ?? null,
                'payment_proof' => ['image_name' => $this->upload(dir: 'seller-package/payment-proof/', format: 'webp', image: $request->file('payment_proof')),
                    'storage' => config('filesystems.disks.default') ?? 'public']];
        }
        $retained = false;
        try {
            $purchase = app(VendorExtraAdService::class)->purchase($request->seller, $data, $proof);
            $retained = $proof && (($purchase->payment_details['payment_proof']['image_name'] ?? null) === $proof['payment_proof']['image_name']);
            return response()->json(['message' => 'تم تسجيل طلب المساحات الإضافية.', 'purchase' => $purchase]);
        } catch (DomainException $e) { return response()->json(['message' => $e->getMessage()], 422); }
        finally {
            if ($proof && !$retained) \Illuminate\Support\Facades\Storage::disk($proof['payment_proof']['storage'])->delete('seller-package/payment-proof/'.$proof['payment_proof']['image_name']);
        }
    }
}
