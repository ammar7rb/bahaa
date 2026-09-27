<?php

namespace Tests\Unit;

use App\Http\Middleware\SellerApiAuthMiddleware;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerInsurance;
use App\Models\SellerPackage;
use App\Models\SellerPackageSubscription;
use App\Models\SellerPackageTransaction;
use App\Models\SellerProductEntitlement;
use App\Models\SellerProductPromotion;
use App\Services\SellerCommissionService;
use App\Services\SellerInsuranceService;
use App\Services\SellerPackagePurchaseService;
use App\Services\SellerProductEntitlementService;
use App\Services\SellerProductPromotionService;
use App\Utils\Helpers;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SellerCommerceRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();

        DB::table('business_settings')->insert([
            ['type' => 'seller_insurance_status', 'value' => '1'],
            ['type' => 'seller_insurance_amount', 'value' => '100'],
            ['type' => 'seller_insurance_repayment_after_forfeiture', 'value' => '1'],
            ['type' => 'seller_activation_verification_mode', 'value' => 'otp_only'],
            ['type' => 'sales_commission', 'value' => '10'],
            ['type' => 'product_brand', 'value' => '0'],
            ['type' => 'digital_product', 'value' => '0'],
            ['type' => 'business_mode', 'value' => 'multi'],
            ['type' => 'language', 'value' => json_encode([[
                'code' => 'en',
                'status' => 1,
                'default' => true,
                'direction' => 'ltr',
            ]])],
        ]);
        DB::table('sellers')->insert([
            'id' => 1,
            'status' => 'approved',
            'activation_status' => 'active',
            'phone_verified_at' => now(),
            'registration_reference' => (string) Str::uuid(),
            'auth_token' => Str::random(50),
        ]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        foreach ([
            'seller_package_transactions',
            'seller_product_promotions',
            'seller_product_entitlements',
            'products',
            'seller_package_subscriptions',
            'seller_packages',
            'seller_insurances',
            'sellers',
            'business_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Cache::flush();
        parent::tearDown();
    }

    public function test_complete_vendor_commerce_flow_cannot_bypass_reviews_or_quotas(): void
    {
        $seller = Seller::findOrFail(1);
        $package = SellerPackage::create([
            'name' => 'Ultra',
            'slug' => 'ultra',
            'package_price' => 250,
            'product_limit' => 2,
            'product_duration_days' => 30,
            'search_promotion_limit' => 1,
            'search_promotion_duration_days' => 7,
            'homepage_promotion_limit' => 1,
            'homepage_promotion_duration_days' => 5,
            'package_validity_days' => 60,
            'status' => true,
        ]);
        $packageService = app(SellerPackagePurchaseService::class);
        $entitlementService = app(SellerProductEntitlementService::class);
        $promotionService = app(SellerProductPromotionService::class);

        // Publishing is independent from advertising packages.
        $entitlementService->assertCanReserve($seller);
        $product = $this->createProduct('Published Product');
        $this->assertNull($entitlementService->reserveForProduct($product, $seller));
        $product->update(['request_status' => 1]);
        $this->assertNull($entitlementService->activateForPublication($product->fresh(), $seller));
        $product->update(['status' => 1]);

        // Advertising still requires an active paid advertising package.
        $this->assertDomainException(
            fn () => $promotionService->activateSearchPromotion($product->fresh(), $seller),
            'active_seller_package_is_required_before_promoting_products'
        );

        $subscription = $packageService->getOrCreatePendingSubscription($seller, $package);
        $subscription = $packageService->submitOfflinePayment($subscription, [
            'method_id' => 1,
            'payment_proof' => ['image_name' => 'package.webp', 'storage' => 'public'],
        ]);
        $this->assertSame(SellerPackageSubscription::STATUS_PENDING_REVIEW, $subscription->status);
        $entitlementService->assertCanReserve($seller);

        $packagePayment = [
            'payment_method' => 'offline_payment',
            'transaction_id' => 'package-approved-1',
            'payment_amount' => 250,
            'currency_code' => 'EGP',
        ];
        $this->assertSame('paid', $packageService->markPaid($subscription, $packagePayment, 1, 'Approved')['message']);
        $this->assertSame('already_paid', $packageService->markPaid($subscription, $packagePayment, 1, 'Duplicate')['message']);
        $subscription = $subscription->fresh();
        $this->assertSame(SellerPackageSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame(3, $subscription->transactions()->count());
        $this->assertSame(0, $subscription->fresh()->used_product_limit);

        $searchPromotion = $promotionService->activateSearchPromotion($product->fresh(), $seller);
        $homepagePromotion = $promotionService->activateHomepagePromotion($product->fresh(), $seller);
        $this->assertSame($searchPromotion->id, $promotionService->activateSearchPromotion($product->fresh(), $seller)->id);
        $this->assertSame($homepagePromotion->id, $promotionService->activateHomepagePromotion($product->fresh(), $seller)->id);
        $this->assertSame(SellerProductPromotion::APPROVAL_PENDING, $searchPromotion->approval_status);
        $this->assertSame(SellerProductPromotion::APPROVAL_PENDING, $homepagePromotion->approval_status);
        $this->assertSame(0, $subscription->fresh()->used_search_promotion_limit);
        $this->assertSame(0, $subscription->fresh()->used_homepage_promotion_limit);

        $searchPromotion = $promotionService->reviewPromotion(
            $searchPromotion,
            'approve',
            1,
            'Approved search campaign',
            'search_results',
            null,
            1,
        );
        $homepagePromotion = $promotionService->reviewPromotion(
            $homepagePromotion,
            'approve',
            1,
            'Approved homepage campaign',
            'homepage_featured',
            null,
            1,
        );
        $this->assertSame(SellerProductPromotion::APPROVAL_APPROVED, $searchPromotion->approval_status);
        $this->assertSame(SellerProductPromotion::APPROVAL_APPROVED, $homepagePromotion->approval_status);
        $subscription->refresh();
        $this->assertSame(1, $subscription->used_search_promotion_limit);
        $this->assertSame(1, $subscription->used_homepage_promotion_limit);

        $rejectedProduct = $this->createProduct('Rejected Product');
        $this->assertNull($entitlementService->reserveForProduct($rejectedProduct, $seller));
        $entitlementService->restoreAfterRejection($rejectedProduct);
        $entitlementService->restoreAfterRejection($rejectedProduct);
        $this->assertSame(0, $subscription->fresh()->used_product_limit);
        $this->assertSame(0, SellerPackageTransaction::where('transaction_type', SellerPackageTransaction::TYPE_QUOTA_RESTORE)->count());

        $entitlementService->cancelForDeletion($product);
        $this->assertSame(0, $subscription->fresh()->used_product_limit);

        $this->assertSame('50.00', Helpers::seller_sales_commission('seller', $seller->id, 500));
        app(SellerCommissionService::class)->updateOverride($seller, true, 0);
        $this->assertSame('0.00', Helpers::seller_sales_commission('seller', $seller->id, 500));
    }

    public function test_legacy_v2_token_helper_rejects_unapproved_or_unverified_sellers(): void
    {
        $seller = Seller::findOrFail(1);
        $request = Request::create('/api/v2/seller/products/list', 'GET');
        $request->headers->set('authorization', 'Bearer '.$seller->auth_token);

        $this->assertSame(1, Helpers::get_seller_by_token($request)['success']);

        $seller->update(['phone_verified_at' => null]);
        $this->assertSame(0, Helpers::get_seller_by_token($request)['success']);
        $this->assertNull(Helpers::getSellerByToken($request));

        $seller->update(['phone_verified_at' => now(), 'status' => 'suspended']);
        $this->assertSame(0, Helpers::get_seller_by_token($request)['success']);
        $this->assertNull(Helpers::getSellerByToken($request));
    }

    public function test_product_quota_guard_rejects_a_seller_with_pending_activation(): void
    {
        $seller = Seller::findOrFail(1);
        $seller->update(['activation_status' => 'activation_ticket_open']);

        $this->assertDomainException(
            fn () => app(SellerProductEntitlementService::class)->assertCanReserve($seller),
            'seller_activation_is_required_before_adding_products'
        );
    }

    public function test_v3_api_middleware_rejects_unverified_and_suspended_sellers(): void
    {
        $seller = Seller::findOrFail(1);
        $request = Request::create('/api/v3/seller/packages', 'GET');
        $request->headers->set('authorization', 'Bearer '.$seller->auth_token);
        $middleware = app(SellerApiAuthMiddleware::class);

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($seller->id, $request->seller->id);

        $seller->update(['phone_verified_at' => null]);
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('seller_phone_verification_required', $response->getData(true)['errors'][0]['code']);

        $seller->update(['phone_verified_at' => now(), 'status' => 'suspended']);
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('seller_account_not_approved', $response->getData(true)['errors'][0]['code']);
    }

    private function createProduct(string $name): Product
    {
        return Product::query()->withoutGlobalScopes()->create([
            'user_id' => 1,
            'added_by' => 'seller',
            'name' => $name,
            'status' => 0,
            'request_status' => 0,
        ]);
    }

    private function assertDomainException(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    private function createTables(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('approved');
            $table->string('activation_status')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->uuid('registration_reference')->nullable();
            $table->string('auth_token', 100)->nullable();
            $table->decimal('sales_commission_percentage', 40, 20)->nullable();
            $table->timestamps();
        });
        Schema::create('seller_insurances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->uuid('transaction_id')->unique();
            $table->decimal('amount', 40, 20)->default(0);
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->char('payment_request_id', 36)->nullable();
            $table->text('forfeiture_reason')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('waived_at')->nullable();
            $table->timestamp('forfeited_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('package_price', 40, 20)->default(0);
            $table->unsignedInteger('product_limit')->default(0);
            $table->unsignedInteger('product_duration_days')->default(0);
            $table->unsignedInteger('search_promotion_limit')->default(0);
            $table->unsignedInteger('search_promotion_duration_days')->default(0);
            $table->unsignedInteger('homepage_promotion_limit')->default(0);
            $table->unsignedInteger('homepage_promotion_duration_days')->default(0);
            $table->unsignedInteger('coupon_limit')->nullable();
            $table->unsignedInteger('package_validity_days')->nullable();
            $table->string('duration_unit', 20)->nullable();
            $table->unsignedInteger('duration_value')->nullable();
            $table->boolean('status')->default(true);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_package_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('seller_package_id')->nullable();
            $table->string('package_name')->nullable();
            $table->decimal('paid_package_price', 40, 20)->default(0);
            $table->unsignedInteger('product_limit')->default(0);
            $table->unsignedInteger('used_product_limit')->default(0);
            $table->integer('product_adjustment_limit')->default(0);
            $table->unsignedInteger('product_duration_days')->default(0);
            $table->unsignedInteger('search_promotion_limit')->default(0);
            $table->unsignedInteger('used_search_promotion_limit')->default(0);
            $table->integer('search_promotion_adjustment_limit')->default(0);
            $table->unsignedInteger('search_promotion_duration_days')->default(0);
            $table->unsignedInteger('homepage_promotion_limit')->default(0);
            $table->unsignedInteger('used_homepage_promotion_limit')->default(0);
            $table->integer('homepage_promotion_adjustment_limit')->default(0);
            $table->unsignedInteger('homepage_promotion_duration_days')->default(0);
            $table->unsignedInteger('coupon_limit')->nullable();
            $table->unsignedInteger('used_coupon_limit')->default(0);
            $table->integer('coupon_adjustment_limit')->default(0);
            $table->unsignedInteger('package_validity_days')->nullable();
            $table->string('duration_unit', 20)->nullable();
            $table->unsignedInteger('duration_value')->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->char('payment_request_id', 36)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('added_by');
            $table->string('name')->nullable();
            $table->boolean('status')->default(false);
            $table->unsignedTinyInteger('request_status')->default(0);
            $table->timestamps();
        });
        Schema::create('seller_product_entitlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('seller_package_subscription_id');
            $table->unsignedBigInteger('seller_package_id')->nullable();
            $table->unsignedInteger('duration_days')->default(0);
            $table->string('status')->default('reserved');
            $table->boolean('quota_restored')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('quota_restored_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_product_promotions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('seller_package_subscription_id');
            $table->unsignedBigInteger('seller_product_entitlement_id')->nullable();
            $table->string('promotion_type');
            $table->unsignedInteger('duration_days')->default(0);
            $table->integer('sort_order')->default(0);
            $table->string('status')->default('reserved');
            $table->string('approval_status')->default('legacy_approved');
            $table->timestamp('requested_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->string('placement_scope')->nullable();
            $table->string('placement_key')->nullable();
            $table->unsignedInteger('requested_position')->nullable();
            $table->unsignedInteger('approved_position')->nullable();
            $table->boolean('quota_restored')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('quota_restored_at')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_package_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('seller_package_subscription_id')->nullable();
            $table->unsignedBigInteger('seller_package_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('seller_product_entitlement_id')->nullable();
            $table->unsignedBigInteger('seller_product_promotion_id')->nullable();
            $table->char('payment_request_id', 36)->nullable();
            $table->uuid('transaction_id')->unique();
            $table->string('transaction_type');
            $table->string('quota_type')->nullable();
            $table->integer('credit')->default(0);
            $table->integer('debit')->default(0);
            $table->integer('balance_after')->default(0);
            $table->decimal('paid_amount', 40, 20)->default(0);
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }
}
