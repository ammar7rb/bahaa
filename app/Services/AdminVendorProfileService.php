<?php

namespace App\Services;

use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Models\VendorAdvertisingSlot;
use App\Models\VendorProductWorkspace;
use Illuminate\Support\Facades\Schema;

/** Read-only profile projection: opening an account must never advance or buy a cycle. */
class AdminVendorProfileService
{
    public function productQuery(Seller $seller, array $filters = [])
    {
        return \App\Models\Product::query()->where('added_by', 'seller')->where('user_id', $seller->id)
            ->when($filters['searchValue'] ?? null, fn ($query, $term) => $query->where(function ($search) use ($term) {
                $search->where('name', 'like', '%'.$term.'%')->orWhereHas('translations', fn ($translation) => $translation->where('key', 'name')->where('value', 'like', '%'.$term.'%'));
            }))
            ->when(isset($filters['review_status']), fn ($query) => $query->where('request_status', $filters['review_status']))
            ->when(isset($filters['publication_status']), fn ($query) => $query->where('status', $filters['publication_status']));
    }

    public function build(Seller $seller): array
    {
        $overview = app(VendorAccountOverviewService::class)->build($seller);
        $subscriptions = SellerPackageSubscription::where('seller_id', $seller->id)
            ->orderByDesc('created_at')->paginate(10, ['*'], 'subscriptions_page')->withQueryString();
        $slots = VendorAdvertisingSlot::where('seller_id', $seller->id)
            ->with(['product', 'subscription'])->orderByDesc('starts_at')
            ->orderBy('position')->paginate(20, ['*'], 'slots_page')->withQueryString();
        $next = SellerPackageSubscription::where('seller_id', $seller->id)
            ->where('payment_status', 'paid')->whereIn('status', ['active', 'scheduled'])
            ->where('starts_at', '>', now())->orderBy('starts_at')->first();
        $activation = 'active';
        $cycleQuery = SellerPackageSubscription::where('seller_id', $seller->id)->where('payment_status', 'paid');
        $displayCycle = (clone $cycleQuery)->whereIn('status', ['active','scheduled'])->where('starts_at','<=',now())->where('expires_at','>',now())->orderByDesc('starts_at')->orderByDesc('id')->first()
            ?? (clone $cycleQuery)->where('starts_at','<=',now())->orderByDesc('starts_at')->orderByDesc('id')->first();
        $displaySlots = VendorAdvertisingSlot::where('seller_id',$seller->id)->where('seller_package_subscription_id',$displayCycle?->id ?? 0)->with(['product','subscription'])->orderBy('position')->get();
        $canCreate = $seller->status === 'approved'
            && !app(VendorAdvertisingAccessService::class)->requiresSubscription((int) $seller->id);

        return compact('overview', 'subscriptions', 'slots', 'next', 'canCreate', 'displaySlots') + [
            'activation' => $activation,
            'pendingPayments' => SellerPackageSubscription::where('seller_id', $seller->id)
                ->where('payment_status', 'unpaid')->whereIn('status', ['pending', 'pending_review'])->count(),
        ];
    }

    public function products(Seller $seller, $products): array
    {
        $ids = $products->getCollection()->pluck('id');
        $workspaces = Schema::hasTable('vendor_product_workspaces')
            ? VendorProductWorkspace::where('seller_id', $seller->id)->whereIn('product_id', $ids)->get()->keyBy('product_id')
            : collect();
        $slots = VendorAdvertisingSlot::where('seller_id', $seller->id)->whereIn('product_id', $ids)
            ->with('subscription')->orderByDesc('starts_at')->get()->groupBy('product_id');
        $accessQuery = \App\Models\Product::withoutGlobalScopes()->where('added_by', 'seller')
            ->where('user_id', $seller->id)->whereIn('id', $ids);
        app(VendorAdvertisingAccessService::class)->constrain($accessQuery);
        $subscriptionEligible = $accessQuery->pluck('id')->flip();
        return compact('workspaces', 'slots', 'subscriptionEligible');
    }
}
