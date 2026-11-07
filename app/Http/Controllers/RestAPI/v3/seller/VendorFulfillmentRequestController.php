<?php

namespace App\Http\Controllers\RestAPI\v3\seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SellerOrderVisibilityService;
use App\Services\VendorOrderPresentationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorFulfillmentRequestController extends Controller
{
    public function show(Request $request, string $reference): JsonResponse
    {
        $order = Order::where('seller_id', $request->seller->id)->where('seller_is', 'seller')
            ->where(fn ($query) => ctype_digit($reference) ? $query->whereKey($reference)
                : $query->where('seller_restricted_access_token', $reference))->firstOrFail();
        abort_unless(app(SellerOrderVisibilityService::class)->canSellerView($order), 404);
        return response()->json(['fulfillment_request' => app(VendorOrderPresentationService::class)->order($order)])
            ->header('Cache-Control', 'private, no-store');
    }
}
