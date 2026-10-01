<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SellerOrderInsurance;
use App\Support\Commerce\OrderCommerceState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Keeps new v3 orders invisible to the seller until the admin release. */
class SellerOrderVisibilityService
{
    private const BLOCKED_REVIEW_STATUSES = [
        'pending_admin_review', 'assignment_in_progress', 'waiting_customer_post_purchase_payment',
    ];

    private const ACCESSIBLE_FLOW_STATUSES = [
        OrderCommerceState::SELLER_INSURANCE_PENDING,
        OrderCommerceState::SELLER_INSURANCE_UNDER_REVIEW,
        OrderCommerceState::RELEASED_TO_SELLER,
        OrderCommerceState::FULFILLMENT_IN_PROGRESS,
        OrderCommerceState::COMPLETED,
        OrderCommerceState::CANCELLED,
    ];

    /** Apply the same access rules before counting or paginating orders. */
    public function applyAccessRules(Builder $query): Builder
    {
        $restrictedVersions = [PostPurchaseInvoiceService::CONTRACT_VERSION, config('order_commerce.new_flow_version')];

        return $query
            ->where(fn ($gate) => $gate->whereNull('admin_order_review_status')
                ->orWhereNotIn('admin_order_review_status', self::BLOCKED_REVIEW_STATUSES))
            ->where(function ($flow) use ($restrictedVersions): void {
                // Both legacy-v1 and legacy_v1 occur in deployed databases.
                $flow->whereNull('commerce_flow_version')
                    ->orWhereNotIn('commerce_flow_version', $restrictedVersions)
                    ->orWhere(fn ($restricted) => $restricted
                        ->where('admin_order_review_status', 'approved')
                        ->whereIn('commerce_flow_status', self::ACCESSIBLE_FLOW_STATUSES));
            });
    }

    /** Seller may see either the complete order or its restricted insurance gate. */
    public function canSellerAccess(Order|array $order): bool
    {
        if (in_array(data_get($order, 'admin_order_review_status'), self::BLOCKED_REVIEW_STATUSES, true)) return false;

        $flow = data_get($order, 'commerce_flow_version');
        if (! in_array($flow, [PostPurchaseInvoiceService::CONTRACT_VERSION, config('order_commerce.new_flow_version')], true)) {
            return true;
        }

        return data_get($order, 'admin_order_review_status') === 'approved'
            && in_array(data_get($order, 'commerce_flow_status'), self::ACCESSIBLE_FLOW_STATUSES, true);
    }

    public function canSellerView(Order|array $order): bool
    {
        if (! $this->canSellerAccess($order)) return false;
        $flow = data_get($order, 'commerce_flow_version');
        $requiresRestrictedRelease = in_array($flow, [
            PostPurchaseInvoiceService::CONTRACT_VERSION,
            config('order_commerce.new_flow_version'),
        ], true);
        if (! $requiresRestrictedRelease) {
            return true;
        }

        if ($flow === PostPurchaseInvoiceService::CONTRACT_VERSION
            && data_get($order, 'post_purchase_status') !== PostPurchaseInvoiceService::ORDER_STATUS_RELEASED) {
            return false;
        }

        $insurance = $order instanceof Order
            ? $order->sellerOrderInsurance()->first()
            : SellerOrderInsurance::query()
                ->where('order_id', (int) data_get($order, 'id'))
                ->where('seller_id', (int) data_get($order, 'seller_id'))
                ->first();

        return $insurance !== null
            && app(SellerOrderInsuranceService::class)->canViewDetails($insurance);
    }

    public function visible(Collection $orders): Collection
    {
        return $orders->filter(fn ($order) => $this->canSellerView($order))->values();
    }

    public function accessible(Collection $orders): Collection
    {
        return $orders->filter(fn ($order) => $this->canSellerAccess($order))->values();
    }
}
