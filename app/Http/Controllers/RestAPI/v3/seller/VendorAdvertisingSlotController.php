<?php

namespace App\Http\Controllers\RestAPI\v3\seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\VendorAdvertisingSlot;
use App\Services\SellerProductReviewService;
use App\Services\VendorAdvertisingSlotService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorAdvertisingSlotController extends Controller
{
    public function reserve(Request $request, int $slot): JsonResponse
    {
        abort_unless(config('vendor_advertising.creation_slots_enabled'), 404);
        $data = $request->validate(['reservation_token' => 'required|uuid']);
        $reserved = app(\App\Services\VendorSlotReservationService::class)->reserve($request->seller, $slot, $data['reservation_token']);
        return response()->json(['slot_id' => $reserved->id, 'expires_at' => $reserved->reservation_expires_at->toIso8601String()]);
    }

    public function cancelReservation(Request $request, int $slot): JsonResponse
    {
        abort_unless(config('vendor_advertising.creation_slots_enabled'), 404);
        $request->validate(['reservation_token' => 'nullable|uuid']);
        app(\App\Services\VendorSlotReservationService::class)->cancel($request->seller, $slot, $request->input('reservation_token'));
        return response()->json(['message' => 'أُلغي الحجز. المساحة متاحة لإضافة منتج.']);
    }
    public function index(Request $request): JsonResponse
    {
        abort_unless(config('vendor_advertising.enabled'), 404);
        $seller = $request->seller;
        app(VendorAdvertisingSlotService::class)->synchronize($seller);
        return response()->json(app(\App\Services\VendorAdvertisingOverviewService::class)->build($seller));

    }

    public function update(Request $request, int $slot): JsonResponse
    {
        abort_unless(config('vendor_advertising.enabled'), 404);
        $data = $request->validate(['product_id' => 'present|nullable|integer|min:1', 'slot_revision' => (config('vendor_advertising.creation_slots_enabled') ? 'required' : 'nullable').'|integer|min:0']);
        $data['product_id'] = isset($data['product_id']) ? (int) $data['product_id'] : null;
        try {
            $updated = DB::transaction(function () use ($request, $slot, $data) {
                \App\Models\Seller::whereKey($request->seller->id)->lockForUpdate()->firstOrFail();
                $before = VendorAdvertisingSlot::where('seller_id', $request->seller->id)->findOrFail($slot);
                if (config('vendor_advertising.creation_slots_enabled') && (int) $before->assignment_revision !== (int) $data['slot_revision']) throw new DomainException('تغيرت المساحة في صفحة أخرى. حدّث الصفحة قبل التعيين أو الإزالة.');
                $updated = app(VendorAdvertisingSlotService::class)->assign($request->seller, $slot, $data['product_id']);
                if ($data['product_id'] !== null && $before->product_id !== $data['product_id']) {
                    $product = Product::withoutGlobalScopes()->findOrFail($data['product_id']);
                    $renewalSelection = ($updated->subscription->metadata['slot_selection_required'] ?? false)
                        && (int) $product->request_status === 1
                        && VendorAdvertisingSlot::where('seller_id', $request->seller->id)->where('product_id', $product->id)
                            ->where('expires_at', '<=', $updated->starts_at)->exists();
                    if (! $renewalSelection) {
                        DB::table('products')->where('id', $product->id)->update(['status' => 0]);
                        if (config('vendor_product_workspace.enabled')) \App\Models\VendorProductWorkspace::where('product_id', $product->id)->where('seller_id', $request->seller->id)->update(['publication_suspended' => true]);
                        app(SellerProductReviewService::class)->submit($product);
                    }
                }
                $subscription = $updated->subscription->fresh();
                if (($subscription->metadata['slot_selection_required'] ?? false) && $subscription->used_product_limit >= $subscription->product_limit) {
                    $metadata = $subscription->metadata;
                    $metadata['slot_selection_required'] = false;
                    $subscription->update(['metadata' => $metadata]);
                }
                return $updated;
            });

            return response()->json(['slot' => $updated, 'message' => 'تم تحديث المساحة. لا يظهر منتج جديد أو بديل قبل اعتماد الإدارة.']);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
