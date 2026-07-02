<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;

class SellerDashboardSetupService
{
    public function __construct(
        private readonly SellerRegistrationVerificationService $registrationVerificationService,
        private readonly SellerInsuranceService $sellerInsuranceService,
        private readonly SellerPackagePurchaseService $sellerPackagePurchaseService,
        private readonly SellerProductEntitlementService $sellerProductEntitlementService,
        private readonly SellerProductPromotionService $sellerProductPromotionService,
        private readonly SellerCommissionService $sellerCommissionService,
    ) {}

    public function getSummary(Seller $seller): array
    {
        $registration = $this->registrationVerificationService->getEligibility($seller);
        $insurance = $this->sellerInsuranceService->getSummary($seller);
        $package = $this->sellerPackagePurchaseService->getSummary($seller);
        $products = $this->sellerProductEntitlementService->getSummary($seller);
        $searchPromotions = $this->sellerProductPromotionService->getSearchSummary($seller);
        $homepagePromotions = $this->sellerProductPromotionService->getHomepageSummary($seller);
        $publishedProducts = Product::query()
            ->withoutGlobalScopes()
            ->where('added_by', 'seller')
            ->where('user_id', $seller->id)
            ->where('status', 1)
            ->where('request_status', 1)
            ->count();

        $steps = [
            [
                'key' => 'account',
                'completed' => $registration['can_login'],
                'state' => $registration['next_step'],
            ],
            [
                'key' => 'insurance',
                'completed' => $insurance['active'],
                'state' => $insurance['pending_review'] ? 'pending_review' : ($insurance['active'] ? 'active' : 'required'),
            ],
            [
                'key' => 'package',
                'completed' => (bool) $package['active_subscription'],
                'state' => $package['pending_review'] ? 'pending_review' : ($package['active_subscription'] ? 'active' : 'required'),
            ],
            [
                'key' => 'products',
                'completed' => $publishedProducts > 0,
                'state' => $publishedProducts > 0 ? 'published' : 'not_published',
            ],
        ];

        return [
            'registration' => $registration,
            'insurance' => $insurance,
            'package' => $package,
            'products' => $products,
            'search_promotions' => $searchPromotions,
            'homepage_promotions' => $homepagePromotions,
            'commission' => $this->sellerCommissionService->getSummary($seller),
            'published_products_count' => $publishedProducts,
            'steps' => $steps,
            'completed_steps' => collect($steps)->where('completed', true)->count(),
            'completion_percentage' => (int) round(collect($steps)->where('completed', true)->count() / count($steps) * 100),
            'next_step' => $this->resolveNextStep(
                registration: $registration,
                insurance: $insurance,
                package: $package,
                products: $products,
                publishedProducts: $publishedProducts,
            ),
        ];
    }

    private function resolveNextStep(
        array $registration,
        array $insurance,
        array $package,
        array $products,
        int $publishedProducts,
    ): string {
        if (! $registration['can_login']) {
            return $registration['next_step'];
        }
        if (! $insurance['active']) {
            return $insurance['pending_review'] ? 'insurance_pending_review' : 'activate_insurance';
        }
        if (! $package['active_subscription']) {
            return $package['pending_review'] ? 'package_pending_review' : 'choose_package';
        }
        if ($publishedProducts === 0 && $products['can_add_product']) {
            return 'add_first_product';
        }
        if (! $products['can_add_product']) {
            return 'product_limit_reached';
        }

        return 'manage_products';
    }
}
