<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorAdvertisingAccessService
{
    public function rollout(): ?object
    {
        return config('vendor_advertising.enabled') ? DB::table('vendor_advertising_rollouts')->find(1) : null;
    }

    public function inGrace(int $sellerId): bool
    {
        $rollout = $this->rollout();
        return $rollout && $sellerId <= $rollout->seller_cutoff_id && now()->lt(Carbon::parse($rollout->grace_ends_at));
    }

    public function assertCanCreate(int $sellerId): void
    {
        if (! config('vendor_advertising.enabled') && !config('vendor_product_workspace.enabled')) return;
        $seller = Seller::findOrFail($sellerId);
        if (config('vendor_advertising.enabled')) app(VendorAdvertisingSlotService::class)->synchronize($seller);
        if (! $this->requiresSubscription($sellerId)) return;
        throw ValidationException::withMessages(['subscription' => 'يلزم اشتراك سارٍ لإنشاء منتجات جديدة.']);
    }

    /** Read-only eligibility shared by the web and mobile dashboard. */
    public function requiresSubscription(int $sellerId): bool
    {
        if (!config('vendor_advertising.enabled') && !config('vendor_product_workspace.enabled')) return false;

        return ! SellerPackageSubscription::where('seller_id', $sellerId)->where('payment_status', 'paid')
            ->whereIn('status', ['active', 'scheduled'])->where('starts_at', '<=', now())->where('expires_at', '>', now())->exists();
    }

    /** Correlated SQL also protects direct product details and checkout checks. */
    public function constrain($query): void
    {
        if (! config('vendor_advertising.enabled') && !config('vendor_product_workspace.enabled')) return;
        $rollout = $this->rollout();
        if (config('vendor_product_workspace.enabled')) {
            $query->whereNotExists(function ($stopped) {
                $stopped->selectRaw('1')->from('vendor_product_workspaces as stopped_workspace')
                    ->whereColumn('stopped_workspace.product_id', 'products.id')
                    ->whereColumn('stopped_workspace.seller_id', 'products.user_id')
                    ->where('stopped_workspace.publication_suspended', true);
            });
            $query->where(function ($allowed) {
                $allowed->where('products.added_by', 'admin')
                    ->orWhereExists(function ($legacy) {
                        $legacy->selectRaw('1')->from('vendor_product_workspaces as vpw')
                            ->whereColumn('vpw.product_id', 'products.id')->whereColumn('vpw.seller_id', 'products.user_id')
                            ->where('vpw.legacy_exempt', true);
                    })->orWhereExists(function ($slots) {
                        $slots->selectRaw('1')->from('vendor_advertising_slots as vas')
                            ->join('seller_package_subscriptions as vps', 'vps.id', '=', 'vas.seller_package_subscription_id')
                            ->whereColumn('vas.product_id', 'products.id')->whereColumn('vas.seller_id', 'products.user_id')
                            ->whereColumn('vps.seller_id', 'products.user_id')->where('vps.payment_status', 'paid')
                            ->whereIn('vps.status', ['active', 'scheduled'])->where('vps.starts_at', '<=', now())
                            ->where('vps.expires_at', '>', now())->where('vas.starts_at', '<=', now())->where('vas.expires_at', '>', now());
                    });
            });
            return;
        }
        $query->where(function ($allowed) use ($rollout) {
            $allowed->where('products.added_by', 'admin')->orWhere(function ($vendor) use ($rollout) {
                $vendor->where('products.added_by', 'seller')->where(function ($access) use ($rollout) {
                    $access->whereExists(function ($slots) {
                        $slots->selectRaw('1')->from('vendor_advertising_slots as vas')
                            ->join('seller_package_subscriptions as vps', 'vps.id', '=', 'vas.seller_package_subscription_id')
                            ->whereColumn('vas.product_id', 'products.id')->whereColumn('vas.seller_id', 'products.user_id')
                            ->where('vps.payment_status', 'paid')->whereIn('vps.status', ['active', 'scheduled'])
                            ->where('vas.starts_at', '<=', now())->where('vas.expires_at', '>', now());
                    });
                    if ($rollout && now()->lt(Carbon::parse($rollout->grace_ends_at))) {
                        $access->orWhere(function ($legacy) use ($rollout) {
                            $legacy->where('products.user_id', '<=', $rollout->seller_cutoff_id)
                                ->where(fn ($existing) => $existing->where('products.created_at', '<=', $rollout->starts_at)->orWhereNull('products.created_at'))
                                ->whereNotExists(function ($paid) {
                                    $paid->selectRaw('1')->from('seller_package_subscriptions as vpaid')->whereColumn('vpaid.seller_id', 'products.user_id')
                                        ->where('vpaid.payment_status', 'paid')->where('vpaid.starts_at', '<=', now());
                                });
                        });
                    }
                });
            });
        });
    }

    public function visible(?Product $product, bool $requireApproval = false): bool
    {
        if (! $product) return false;
        if ((! config('vendor_advertising.enabled') && !config('vendor_product_workspace.enabled')) || $product->added_by === 'admin') return true;
        if ($requireApproval && ((int) $product->request_status !== 1 || (int) $product->status !== 1)) return false;
        $query = Product::withoutGlobalScopes()->whereKey($product->id);
        $this->constrain($query);
        return $query->exists();
    }
}
