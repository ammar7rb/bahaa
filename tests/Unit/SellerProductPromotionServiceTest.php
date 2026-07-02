<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Models\SellerProductEntitlement;
use App\Models\SellerProductPromotion;
use App\Services\SellerProductPromotionService;
use App\Utils\ProductManager;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerProductPromotionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        DB::table('business_settings')->insert([
            ['type' => 'seller_insurance_status', 'value' => '0'],
            ['type' => 'product_brand', 'value' => '0'],
            ['type' => 'digital_product', 'value' => '0'],
            ['type' => 'business_mode', 'value' => 'multi'],
        ]);
        DB::table('sellers')->insert(['id' => 1, 'status' => 'approved']);
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
            'seller_insurances',
            'sellers',
            'business_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Cache::flush();
        parent::tearDown();
    }

    public function test_search_promotion_consumes_quota_once_and_is_idempotent(): void
    {
        $subscription = $this->createSubscription(searchLimit: 2, durationDays: 7);
        $product = $this->createProduct();
        $service = app(SellerProductPromotionService::class);

        $first = $service->activateSearchPromotion($product, Seller::findOrFail(1));
        $second = $service->activateSearchPromotion($product, Seller::findOrFail(1));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $subscription->fresh()->used_search_promotion_limit);
        $this->assertSame(7.0, $first->starts_at->diffInDays($first->expires_at));
        $this->assertDatabaseHas('seller_package_transactions', [
            'quota_type' => 'search_promotion',
            'transaction_type' => 'quota_usage',
            'debit' => 1,
            'balance_after' => 1,
        ]);
    }

    public function test_search_promotion_limit_cannot_be_bypassed(): void
    {
        $this->createSubscription(searchLimit: 1);
        $service = app(SellerProductPromotionService::class);
        $service->activateSearchPromotion($this->createProduct(), Seller::findOrFail(1));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('seller_package_search_promotion_limit_has_been_reached');
        $service->activateSearchPromotion($this->createProduct(), Seller::findOrFail(1));
    }

    public function test_inactive_product_cannot_be_promoted(): void
    {
        $subscription = $this->createSubscription(searchLimit: 1);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('only_active_approved_products_can_be_promoted_in_search');
        try {
            app(SellerProductPromotionService::class)->activateSearchPromotion(
                $this->createProduct(status: 0),
                Seller::findOrFail(1)
            );
        } finally {
            $this->assertSame(0, $subscription->fresh()->used_search_promotion_limit);
        }
    }

    public function test_listing_must_cover_the_full_search_promotion_duration(): void
    {
        $subscription = $this->createSubscription(searchLimit: 1, durationDays: 10);
        $product = $this->createProduct();
        SellerProductEntitlement::create([
            'seller_id' => 1,
            'product_id' => $product->id,
            'seller_package_subscription_id' => $subscription->id,
            'seller_package_id' => 1,
            'duration_days' => 30,
            'status' => SellerProductEntitlement::STATUS_ACTIVE,
            'starts_at' => now()->subDays(25),
            'expires_at' => now()->addDays(5),
            'activated_at' => now()->subDays(25),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('product_listing_duration_must_cover_search_promotion_duration');
        try {
            app(SellerProductPromotionService::class)->activateSearchPromotion($product, Seller::findOrFail(1));
        } finally {
            $this->assertSame(0, $subscription->fresh()->used_search_promotion_limit);
        }
    }

    public function test_expired_promotion_is_removed_without_restoring_used_quota(): void
    {
        $subscription = $this->createSubscription(searchLimit: 1);
        $service = app(SellerProductPromotionService::class);
        $promotion = $service->activateSearchPromotion($this->createProduct(), Seller::findOrFail(1));
        $promotion->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(1, $service->expireDuePromotions());
        $this->assertSame(SellerProductPromotion::STATUS_EXPIRED, $promotion->fresh()->status);
        $this->assertSame(1, $subscription->fresh()->used_search_promotion_limit);
    }

    public function test_promoted_products_are_sorted_first_and_marked_for_clients(): void
    {
        $regular = $this->createProduct(name: 'Regular');
        $promoted = $this->createProduct(name: 'Promoted');
        $priorityPromoted = $this->createProduct(name: 'Priority Promoted');
        $promoted->setRelation('activeSearchPromotion', new SellerProductPromotion([
            'promotion_type' => SellerProductPromotion::TYPE_SEARCH,
            'status' => SellerProductPromotion::STATUS_ACTIVE,
            'sort_order' => 10,
        ]));
        $priorityPromoted->setRelation('activeSearchPromotion', new SellerProductPromotion([
            'promotion_type' => SellerProductPromotion::TYPE_SEARCH,
            'status' => SellerProductPromotion::STATUS_ACTIVE,
            'sort_order' => 50,
        ]));
        $regular->setRelation('activeSearchPromotion', null);

        $sorted = ProductManager::prioritizeSearchPromotions(collect([$regular, $promoted, $priorityPromoted]))->values();

        $this->assertSame($priorityPromoted->id, $sorted->first()->id);
        $this->assertTrue($sorted->first()->is_search_promoted);
        $this->assertFalse($sorted->last()->is_search_promoted);
    }

    public function test_homepage_promotion_uses_an_independent_quota_and_is_idempotent(): void
    {
        $subscription = $this->createSubscription(
            searchLimit: 1,
            homepageLimit: 2,
            homepageDurationDays: 14
        );
        $product = $this->createProduct();
        $service = app(SellerProductPromotionService::class);

        $first = $service->activateHomepagePromotion($product, Seller::findOrFail(1));
        $second = $service->activateHomepagePromotion($product, Seller::findOrFail(1));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $subscription->fresh()->used_homepage_promotion_limit);
        $this->assertSame(0, $subscription->fresh()->used_search_promotion_limit);
        $this->assertSame(14.0, $first->starts_at->diffInDays($first->expires_at));
        $this->assertDatabaseHas('seller_package_transactions', [
            'quota_type' => 'homepage_promotion',
            'transaction_type' => 'quota_usage',
            'debit' => 1,
            'balance_after' => 1,
        ]);
    }

    public function test_product_can_have_search_and_homepage_promotions_at_the_same_time(): void
    {
        $subscription = $this->createSubscription(searchLimit: 1, homepageLimit: 1);
        $product = $this->createProduct();
        $service = app(SellerProductPromotionService::class);

        $service->activateSearchPromotion($product, Seller::findOrFail(1));
        $service->activateHomepagePromotion($product, Seller::findOrFail(1));

        $this->assertSame(1, $subscription->fresh()->used_search_promotion_limit);
        $this->assertSame(1, $subscription->fresh()->used_homepage_promotion_limit);
        $this->assertDatabaseHas('seller_product_promotions', [
            'product_id' => $product->id,
            'promotion_type' => SellerProductPromotion::TYPE_SEARCH,
            'status' => SellerProductPromotion::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('seller_product_promotions', [
            'product_id' => $product->id,
            'promotion_type' => SellerProductPromotion::TYPE_HOMEPAGE,
            'status' => SellerProductPromotion::STATUS_ACTIVE,
        ]);
    }

    public function test_expired_homepage_promotion_is_not_returned_as_active(): void
    {
        $subscription = $this->createSubscription(searchLimit: 0, homepageLimit: 1);
        $product = $this->createProduct();
        SellerProductPromotion::create([
            'seller_id' => 1,
            'product_id' => $product->id,
            'seller_package_subscription_id' => $subscription->id,
            'promotion_type' => SellerProductPromotion::TYPE_HOMEPAGE,
            'duration_days' => 7,
            'status' => SellerProductPromotion::STATUS_ACTIVE,
            'starts_at' => now()->subDays(8),
            'expires_at' => now()->subDay(),
            'activated_at' => now()->subDays(8),
        ]);

        $this->assertNull($product->activeHomepagePromotion()->first());
    }

    public function test_homepage_offer_query_is_bound_to_active_homepage_promotions(): void
    {
        $sql = ProductManager::getHomepagePromotedProductsQuery()->toSql();

        $this->assertStringContainsString('seller_product_promotions', $sql);
        $this->assertStringContainsString('promotion_type', $sql);
        $this->assertStringContainsString('expires_at', $sql);
    }

    private function createSubscription(
        int $searchLimit,
        int $durationDays = 7,
        int $homepageLimit = 0,
        int $homepageDurationDays = 7
    ): SellerPackageSubscription {
        return SellerPackageSubscription::create([
            'seller_id' => 1,
            'seller_package_id' => 1,
            'package_name' => 'Promotion Package',
            'paid_package_price' => 100,
            'product_limit' => 5,
            'product_duration_days' => 30,
            'search_promotion_limit' => $searchLimit,
            'used_search_promotion_limit' => 0,
            'search_promotion_adjustment_limit' => 0,
            'search_promotion_duration_days' => $durationDays,
            'homepage_promotion_limit' => $homepageLimit,
            'used_homepage_promotion_limit' => 0,
            'homepage_promotion_adjustment_limit' => 0,
            'homepage_promotion_duration_days' => $homepageLimit > 0 ? $homepageDurationDays : 0,
            'status' => SellerPackageSubscription::STATUS_ACTIVE,
            'payment_status' => 'paid',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'activated_at' => now()->subDay(),
        ]);
    }

    private function createProduct(string $name = 'Test Product', int $status = 1): Product
    {
        return Product::query()->withoutGlobalScopes()->create([
            'user_id' => 1,
            'added_by' => 'seller',
            'name' => $name.' '.uniqid(),
            'status' => $status,
            'request_status' => 1,
        ]);
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
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_insurances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->timestamps();
        });
        Schema::create('seller_package_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('seller_package_id')->nullable();
            $table->string('package_name');
            $table->decimal('paid_package_price', 24, 2)->default(0);
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
            $table->unsignedInteger('package_validity_days')->nullable();
            $table->string('status');
            $table->string('payment_status');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->uuid('payment_request_id')->nullable();
            $table->timestamp('starts_at')->nullable();
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
            $table->uuid('payment_request_id')->nullable();
            $table->uuid('transaction_id')->unique();
            $table->string('transaction_type');
            $table->string('quota_type')->nullable();
            $table->integer('credit')->default(0);
            $table->integer('debit')->default(0);
            $table->integer('balance_after')->default(0);
            $table->decimal('paid_amount', 24, 2)->default(0);
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }
}
