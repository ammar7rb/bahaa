<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Services\SellerDashboardSetupService;
use App\Services\SellerCommissionService;
use App\Services\SellerInsuranceService;
use App\Services\SellerPackagePurchaseService;
use App\Services\SellerProductEntitlementService;
use App\Services\SellerProductPromotionService;
use App\Services\SellerRegistrationVerificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SellerDashboardSetupServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('added_by');
            $table->boolean('status')->default(false);
            $table->unsignedTinyInteger('request_status')->default(0);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('products');
        parent::tearDown();
    }

    public function test_insurance_is_the_next_step_after_account_approval(): void
    {
        $service = $this->makeService(
            insurance: ['active' => false, 'pending_review' => false],
            package: ['active_subscription' => null, 'pending_review' => false],
            products: ['can_add_product' => false],
        );

        $summary = $service->getSummary($this->seller());

        $this->assertSame('activate_insurance', $summary['next_step']);
        $this->assertSame(1, $summary['completed_steps']);
        $this->assertSame(25, $summary['completion_percentage']);
    }

    public function test_ready_seller_sees_package_quotas_and_product_management_as_next_step(): void
    {
        DB::table('products')->insert([
            'user_id' => 1,
            'added_by' => 'seller',
            'status' => 1,
            'request_status' => 1,
        ]);
        $subscription = new SellerPackageSubscription(['package_name' => 'Ultra']);
        $service = $this->makeService(
            insurance: ['active' => true, 'pending_review' => false],
            package: ['active_subscription' => $subscription, 'pending_review' => false],
            products: [
                'can_add_product' => true,
                'product_limit' => 10,
                'remaining_product_limit' => 7,
            ],
        );

        $summary = $service->getSummary($this->seller());

        $this->assertSame('manage_products', $summary['next_step']);
        $this->assertSame(4, $summary['completed_steps']);
        $this->assertSame(100, $summary['completion_percentage']);
        $this->assertSame(1, $summary['published_products_count']);
        $this->assertSame(7, $summary['products']['remaining_product_limit']);
    }

    private function makeService(array $insurance, array $package, array $products): SellerDashboardSetupService
    {
        $registrationService = Mockery::mock(SellerRegistrationVerificationService::class);
        $registrationService->shouldReceive('getEligibility')->once()->andReturn([
            'can_login' => true,
            'next_step' => 'ready_for_insurance',
        ]);

        $insuranceService = Mockery::mock(SellerInsuranceService::class);
        $insuranceService->shouldReceive('getSummary')->once()->andReturn($insurance);

        $packageService = Mockery::mock(SellerPackagePurchaseService::class);
        $packageService->shouldReceive('getSummary')->once()->andReturn($package);

        $productService = Mockery::mock(SellerProductEntitlementService::class);
        $productService->shouldReceive('getSummary')->once()->andReturn(array_merge([
            'product_limit' => 0,
            'remaining_product_limit' => 0,
        ], $products));

        $promotionService = Mockery::mock(SellerProductPromotionService::class);
        $promotionService->shouldReceive('getSearchSummary')->once()->andReturn([
            'search_promotion_limit' => 0,
            'remaining_search_promotion_limit' => 0,
        ]);
        $promotionService->shouldReceive('getHomepageSummary')->once()->andReturn([
            'homepage_promotion_limit' => 0,
            'remaining_homepage_promotion_limit' => 0,
        ]);
        $commissionService = Mockery::mock(SellerCommissionService::class);
        $commissionService->shouldReceive('getSummary')->once()->andReturn([
            'effective_rate' => 5.0,
            'source' => 'system_default',
        ]);

        return new SellerDashboardSetupService(
            $registrationService,
            $insuranceService,
            $packageService,
            $productService,
            $promotionService,
            $commissionService,
        );
    }

    private function seller(): Seller
    {
        $seller = new Seller([
            'status' => 'approved',
            'phone_verified_at' => now(),
        ]);
        $seller->id = 1;

        return $seller;
    }
}
