<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Models\VendorAdvertisingSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Vendor presentation only. Never allocates slots or changes subscription records. */
class VendorAdvertisingOverviewService
{
    public function build(Seller $seller): array
    {
        $cycle = SellerPackageSubscription::where('seller_id', $seller->id)->where('payment_status', 'paid')
            ->where('starts_at', '<=', now())->orderByDesc('starts_at')->orderByDesc('id')->first();
        $slots = VendorAdvertisingSlot::where('seller_id', $seller->id)
            ->where('seller_package_subscription_id', $cycle?->id ?? 0)->orderBy('position')->get();
        $products = Product::withoutGlobalScopes()->where('added_by', 'seller')->where('user_id', $seller->id)
            ->orderByDesc('id')->get(['id', 'user_id', 'added_by', 'name', 'thumbnail', 'unit_price', 'current_stock', 'request_status', 'status']);
        $current = $cycle && in_array($cycle->status, ['active', 'scheduled'], true)
            && $cycle->expires_at?->gt(now());
        $states = config('vendor_product_workspace.enabled')
            ? \App\Models\VendorProductWorkspace::where('seller_id', $seller->id)->get()->keyBy('product_id') : collect();
        if (config('vendor_product_workspace.enabled')) $products = $products->filter(fn ($product) => $states->has($product->id));
        $viewCounts = app(ProductViewCountService::class)->totalsForProducts($products->pluck('id')->all());
        $presentProduct = static function (Product $product) use ($states, $current, $slots, $viewCounts): array {
            $views = $viewCounts[$product->id];
            $remainingStock = (int) $product->current_stock;
            $state = $states->get($product->id);
            if ($state) {
                $product = clone $product;
                $product->setRawAttributes(array_replace($product->getAttributes(), $state->seller_content), true);
            }
            $visible = $state ? app(VendorProductWorkspaceService::class)->vendorActive($state)
                : ($current && $slots->contains(fn ($slot) => $slot->product_id === $product->id && $slot->editable));
            return [
                'id' => $product->id, 'name' => $product->name, 'thumbnail' => $product->thumbnail,
                'thumbnail_url' => $product->thumbnail ? getStorageImages(path: $product->thumbnail_full_url, type: 'backend-product') : null,
                'unit_price' => (float) $product->unit_price, 'current_stock' => $remainingStock, 'request_status' => 1,
                'status' => (int) $visible,
                'product_view_count' => $views['total'],
                'recorded_product_visits' => $views['actual'],
                'administrative_view_additions' => $views['added'],
                'product_view_count_is_adjusted' => $views['added'] > 0,
                'vendor_workspace' => ['private_reference' => $state?->private_reference, 'legacy_exempt' => (bool) $state?->legacy_exempt,
                    'advertising_visible' => (bool) $visible],
            ];
        };
        $cards = $slots->map(function ($slot) use ($products, $cycle, $current, $presentProduct) {
            $product = $products->firstWhere('id', $slot->product_id);
            return array_merge($slot->toArray(), [
                'editable' => (bool) $current && $slot->editable,
                'capacity_status' => !$current || !$slot->editable ? 'expired' : ($product ? 'occupied' : (app(VendorSlotReservationService::class)->isReserved($slot) ? 'reserved' : 'available')),
                'product' => $product ? $presentProduct($product) : null,
                // Counts belong to this product during this cycle, not to a slot's total history.
                'statistics' => $product ? $this->statistics((int) $slot->seller_id, (int) $product->id, $cycle) : null,
            ]);
        })->values();

        $extraSettings = app(VendorExtraAdService::class)->settings();
        return [
            'subscription' => $cycle ? [
                'id' => $cycle->id, 'package_name' => $cycle->package_name, 'status' => $cycle->status,
                'starts_at' => $cycle->starts_at, 'expires_at' => $cycle->expires_at,
                'product_limit' => (int) $cycle->product_limit,
            ] : null,
            'slots' => $cards,
            'products' => $products->map($presentProduct)->values(),
            'capacity' => ['total' => $slots->count(), 'used' => $slots->whereNotNull('product_id')->count(),
                'available' => $cards->where('capacity_status', 'available')->count(), 'reserved' => $cards->where('capacity_status', 'reserved')->count(),
                'included' => (int) ($cycle?->product_limit ?? 0), 'additional' => $slots->whereNotNull('extra_ad_purchase_id')->count()],
            'extra_settings' => $extraSettings,
            'extra_publish_enabled' => (bool) (config('vendor_advertising.extra_publish_enabled') && config('vendor_product_workspace.enabled') && config('vendor_advertising.creation_slots_enabled')),
            'can_buy_extra' => (bool) $current && $extraSettings['enabled'] && (float) $extraSettings['unit_price'] > 0,
        ];
    }

    private function statistics(int $sellerId, int $productId, SellerPackageSubscription $cycle): array
    {
        $visits = Schema::hasTable('seller_package_product_metrics')
            ? (int) DB::table('seller_package_product_metrics')->where('seller_id', $sellerId)
                ->where('seller_package_subscription_id', $cycle->id)->where('product_id', $productId)->sum('visits') : null;
        $orders = DB::table('order_details')->where('seller_id', $sellerId)->where('product_id', $productId)
            ->where('created_at', '>=', $cycle->starts_at)->where('created_at', '<', $cycle->expires_at)
            ->whereNotIn('delivery_status', ['canceled', 'failed'])->distinct()->count('order_id');
        return ['visits' => $visits, 'orders' => $orders, 'scope' => 'product_during_subscription'];
    }
}
