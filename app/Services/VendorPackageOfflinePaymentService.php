<?php

namespace App\Services;

use App\Models\OfflinePaymentMethod;
use App\Models\Seller;
use App\Models\SellerPackage;
use App\Models\SellerPackageSubscription;
use App\Traits\FileManagerTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorPackageOfflinePaymentService
{
    use FileManagerTrait;

    public function submit(Seller $seller, Request $request): SellerPackageSubscription
    {
        abort_unless(config('vendor_advertising.enabled'), 404);
        $data = $request->validate([
            'expected_package_price' => (config('vendor_advertising.creation_slots_enabled') ? 'required' : 'nullable').'|numeric|min:0',
            'package_id' => 'required|integer|min:1', 'method_id' => 'required|integer|min:1',
            'payment_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'method_information' => 'nullable|array', 'method_informations' => 'nullable|string|max:10000',
            'payment_note' => 'nullable|string|max:1000',
        ]);
        $method = OfflinePaymentMethod::whereKey($data['method_id'])->where('status', 1)->whereIn('payment_channel', ['wallet', 'instapay'])->firstOrFail();
        $info = app(VendorOfflinePaymentInputService::class)->information($request, $method, requireReference: false);
        // The receipt identifies submissions without asking the vendor to type a bank transaction number.
        // Existing clients may still send their real reference; preserve that contract.
        $reference = app(VendorOfflinePaymentInputService::class)->reference($request, $info, required: false)
            ?? 'receipt-sha256:'.hash_file('sha256', $request->file('payment_proof')->getRealPath());
        return DB::transaction(function () use ($seller, $data, $method, $info, $request, $reference) {
            Seller::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $service = app(SellerPackagePurchaseService::class);
            $package = SellerPackage::whereKey($data['package_id'])->lockForUpdate()->firstOrFail();
            if (isset($data['expected_package_price']) && (int) round((float) $data['expected_package_price'] * 100) !== (int) round((float) $package->package_price * 100)) {
                throw ValidationException::withMessages(['expected_package_price' => 'تغير سعر الباقة. راجع السعر قبل إرسال التحويل.']);
            }
            $subscription = $service->getOrCreatePendingSubscription($seller, $package);
            app(VendorTransferReferenceService::class)->reserve($method->id,$reference,'package_subscription',$subscription->id);
            $proof = $this->upload(dir: 'seller-package/payment-proof/', format: 'webp', image: $request->file('payment_proof'));
            return $service->submitOfflinePayment($subscription, [
                'transfer_reference'=>$reference, 'method_id' => $method->id, 'method_name' => $method->method_name, 'method_information' => $info,
                'payment_proof' => ['image_name' => $proof, 'storage' => config('filesystems.disks.default') ?? 'public'],
                'payment_note' => $data['payment_note'] ?? null,
            ]);
        });
    }
}
