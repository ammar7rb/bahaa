<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Models\SellerPackageTransaction;
use App\Models\SellerProductEntitlement;
use App\Models\SellerProductPromotion;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerProductPromotionService
{
    public function __construct(
        private readonly SellerInsuranceService $sellerInsuranceService,
    ) {}

    public function getSearchSummary(Seller $seller): array
    {
        $summary = $this->getPromotionSummary($seller, SellerProductPromotion::TYPE_SEARCH);

        return [
            'insurance_satisfied' => $summary['insurance_satisfied'],
            'active_subscription' => $summary['active_subscription'],
            'search_promotion_limit' => $summary['promotion_limit'],
            'used_search_promotion_limit' => $summary['used_promotion_limit'],
            'remaining_search_promotion_limit' => $summary['remaining_promotion_limit'],
            'search_promotion_duration_days' => $summary['promotion_duration_days'],
            'can_promote' => $summary['can_promote'],
        ];
    }

    public function getHomepageSummary(Seller $seller): array
    {
        $summary = $this->getPromotionSummary($seller, SellerProductPromotion::TYPE_HOMEPAGE);

        return [
            'insurance_satisfied' => $summary['insurance_satisfied'],
            'active_subscription' => $summary['active_subscription'],
            'homepage_promotion_limit' => $summary['promotion_limit'],
            'used_homepage_promotion_limit' => $summary['used_promotion_limit'],
            'remaining_homepage_promotion_limit' => $summary['remaining_promotion_limit'],
            'homepage_promotion_duration_days' => $summary['promotion_duration_days'],
            'can_promote' => $summary['can_promote'],
        ];
    }

    public function activateSearchPromotion(Product $product, Seller $seller): SellerProductPromotion
    {
        return $this->activatePromotion($product, $seller, SellerProductPromotion::TYPE_SEARCH);
    }

    public function activateHomepagePromotion(Product $product, Seller $seller): SellerProductPromotion
    {
        return $this->activatePromotion($product, $seller, SellerProductPromotion::TYPE_HOMEPAGE);
    }

    private function activatePromotion(Product $product, Seller $seller, string $promotionType): SellerProductPromotion
    {
        $config = $this->promotionConfig($promotionType);

        return DB::transaction(function () use ($product, $seller, $promotionType, $config) {
            Seller::query()->whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->withoutGlobalScopes()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $this->assertSellerOwnsProduct($seller, $product);

            if ((int) $product->request_status !== 1 || (int) $product->status !== 1) {
                throw new DomainException($config['inactive_product_error']);
            }

            $existing = $product->sellerProductPromotions()
                ->where('promotion_type', $promotionType)
                ->where('status', SellerProductPromotion::STATUS_ACTIVE)
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if ($existing && (! $existing->expires_at || $existing->expires_at->isFuture())) {
                return $existing;
            }
            if ($existing) {
                $existing->update([
                    'status' => SellerProductPromotion::STATUS_EXPIRED,
                    'expired_at' => now(),
                ]);
            }

            if (! $this->sellerInsuranceService->getSummary($seller)['active']) {
                throw new DomainException('active_seller_insurance_is_required_before_promoting_products');
            }

            $subscription = $this->activeSubscriptionQuery($seller)->lockForUpdate()->first();
            if (! $subscription) {
                throw new DomainException('active_seller_package_is_required_before_promoting_products');
            }
            $durationDays = (int) $subscription->{$config['duration_column']};
            if ($durationDays <= 0) {
                throw new DomainException($config['duration_error']);
            }

            $total = $this->quotaTotal($subscription, $config);
            if ($subscription->{$config['used_column']} >= $total) {
                throw new DomainException($config['limit_error']);
            }

            $entitlement = $this->getPublicationEntitlement($product);
            $expiresAt = now()->addDays($durationDays);
            if ($entitlement?->expires_at && $entitlement->expires_at->lt($expiresAt)) {
                throw new DomainException($config['listing_duration_error']);
            }

            $subscription->increment($config['used_column']);
            $subscription->refresh();
            $promotion = SellerProductPromotion::create([
                'seller_id' => $seller->id,
                'product_id' => $product->id,
                'seller_package_subscription_id' => $subscription->id,
                'seller_product_entitlement_id' => $entitlement?->id,
                'promotion_type' => $promotionType,
                'duration_days' => $durationDays,
                'status' => SellerProductPromotion::STATUS_ACTIVE,
                'starts_at' => now(),
                'expires_at' => $expiresAt,
                'activated_at' => now(),
                'metadata' => ['activated_from' => $config['activated_from']],
            ]);

            SellerPackageTransaction::create([
                'seller_id' => $seller->id,
                'seller_package_subscription_id' => $subscription->id,
                'seller_package_id' => $subscription->seller_package_id,
                'product_id' => $product->id,
                'seller_product_entitlement_id' => $entitlement?->id,
                'seller_product_promotion_id' => $promotion->id,
                'transaction_id' => (string) Str::uuid(),
                'transaction_type' => SellerPackageTransaction::TYPE_QUOTA_USAGE,
                'quota_type' => $config['quota_type'],
                'debit' => 1,
                'balance_after' => max(0, $total - $subscription->{$config['used_column']}),
                'paid_amount' => 0,
                'note' => $config['transaction_note'],
            ]);

            cacheRemoveByType(type: 'products');

            return $promotion;
        });
    }

    private function getPromotionSummary(Seller $seller, string $promotionType): array
    {
        $config = $this->promotionConfig($promotionType);
        $this->expireDuePromotions($seller->id);
        $subscription = $this->activeSubscriptionQuery($seller)->first();
        $insuranceSatisfied = $this->sellerInsuranceService->getSummary($seller)['active'];
        $total = $subscription ? $this->quotaTotal($subscription, $config) : 0;
        $used = $subscription ? (int) $subscription->{$config['used_column']} : 0;
        $duration = $subscription ? (int) $subscription->{$config['duration_column']} : 0;

        return [
            'insurance_satisfied' => $insuranceSatisfied,
            'active_subscription' => $subscription,
            'promotion_limit' => $total,
            'used_promotion_limit' => $used,
            'remaining_promotion_limit' => max(0, $total - $used),
            'promotion_duration_days' => $duration,
            'can_promote' => $insuranceSatisfied && $subscription && $duration > 0 && $used < $total,
        ];
    }

    public function expireDuePromotions(?int $sellerId = null): int
    {
        $expired = SellerProductPromotion::query()
            ->where('status', SellerProductPromotion::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->when($sellerId, fn ($query) => $query->where('seller_id', $sellerId))
            ->update([
                'status' => SellerProductPromotion::STATUS_EXPIRED,
                'expired_at' => now(),
            ]);

        if ($expired > 0) {
            cacheRemoveByType(type: 'products');
        }

        return $expired;
    }

    private function getPublicationEntitlement(Product $product): ?SellerProductEntitlement
    {
        $hasManagedPublication = $product->sellerProductEntitlements()->exists();
        if (! $hasManagedPublication) {
            return null;
        }

        $entitlement = $product->sellerProductEntitlements()
            ->where('status', SellerProductEntitlement::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();
        if (! $entitlement) {
            throw new DomainException('seller_product_publication_is_not_active');
        }

        return $entitlement;
    }

    private function activeSubscriptionQuery(Seller $seller)
    {
        return $seller->packageSubscriptions()
            ->where('status', SellerPackageSubscription::STATUS_ACTIVE)
            ->where('payment_status', 'paid')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id');
    }

    private function quotaTotal(SellerPackageSubscription $subscription, array $config): int
    {
        return max(0, $subscription->{$config['limit_column']} + $subscription->{$config['adjustment_column']});
    }

    private function promotionConfig(string $promotionType): array
    {
        return match ($promotionType) {
            SellerProductPromotion::TYPE_SEARCH => [
                'limit_column' => 'search_promotion_limit',
                'used_column' => 'used_search_promotion_limit',
                'adjustment_column' => 'search_promotion_adjustment_limit',
                'duration_column' => 'search_promotion_duration_days',
                'quota_type' => SellerPackageTransaction::QUOTA_SEARCH_PROMOTION,
                'inactive_product_error' => 'only_active_approved_products_can_be_promoted_in_search',
                'duration_error' => 'seller_package_search_promotion_duration_is_not_configured',
                'limit_error' => 'seller_package_search_promotion_limit_has_been_reached',
                'listing_duration_error' => 'product_listing_duration_must_cover_search_promotion_duration',
                'activated_from' => 'seller_search_promotion',
                'transaction_note' => 'Seller search promotion quota used.',
            ],
            SellerProductPromotion::TYPE_HOMEPAGE => [
                'limit_column' => 'homepage_promotion_limit',
                'used_column' => 'used_homepage_promotion_limit',
                'adjustment_column' => 'homepage_promotion_adjustment_limit',
                'duration_column' => 'homepage_promotion_duration_days',
                'quota_type' => SellerPackageTransaction::QUOTA_HOMEPAGE_PROMOTION,
                'inactive_product_error' => 'only_active_approved_products_can_be_promoted_on_homepage',
                'duration_error' => 'seller_package_homepage_promotion_duration_is_not_configured',
                'limit_error' => 'seller_package_homepage_promotion_limit_has_been_reached',
                'listing_duration_error' => 'product_listing_duration_must_cover_homepage_promotion_duration',
                'activated_from' => 'seller_homepage_promotion',
                'transaction_note' => 'Seller homepage promotion quota used.',
            ],
            default => throw new DomainException('invalid_seller_product_promotion_type'),
        };
    }

    private function assertSellerOwnsProduct(Seller $seller, Product $product): void
    {
        if ($product->added_by !== 'seller' || (int) $product->user_id !== (int) $seller->id) {
            throw new DomainException('seller_product_not_found');
        }
    }
}
