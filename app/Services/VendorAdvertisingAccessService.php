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
        if ($seller->activation_status !== null && $seller->activation_status !== 'active') {
            throw ValidationException::withMessages(['activation' => 'يلزم تفعيل حساب التاجر قبل إرسال المنتجات.']);
        }
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
        // Catalog publication is independent of paid advertising placement.
        if (config('vendor_product_workspace.enabled')) {
            $query->whereNotExists(function ($stopped) {
                $stopped->selectRaw('1')->from('vendor_product_workspaces as stopped_workspace')
                    ->whereColumn('stopped_workspace.product_id', 'products.id')
                    ->whereColumn('stopped_workspace.seller_id', 'products.user_id')
                    ->where('stopped_workspace.publication_suspended', true);
            });
        }
        return;
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
