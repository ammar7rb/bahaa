<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Models\SellerProductEntitlement;
use App\Models\SellerProductPromotion;
use App\Services\SellerPackagePerformanceService;
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
            'seller_package_product_metrics',
            'order_details',
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

    public function test_search_promotion_is_pending_idempotent_and_consumes_quota_only_after_approval(): void
    {
        $subscription = $this->createSubscription(searchLimit: 2, durationDays: 7);
        $product = $this->createProduct();
        $service = app(SellerProductPromotionService::class);

        $first = $service->activateSearchPromotion($product, Seller::findOrFail(1));
        $second = $service->activateSearchPromotion($product, Seller::findOrFail(1));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(0, $subscription->fresh()->used_search_promotion_limit);
        $this->assertSame(SellerProductPromotion::APPROVAL_PENDING, $first->approval_status);
        $this->assertNull($first->starts_at);
        $approved = $service->reviewPromotion($first, 'approve', 99, 'Approved placement', 'search_results', null, 1, badgeLabel: 'Sponsored');
        $this->assertSame(1, $subscription->fresh()->used_search_promotion_limit);
        $this->assertSame(7.0, $approved->starts_at->diffInDays($approved->expires_at));
        $this->assertSame('Sponsored', data_get($approved->metadata, 'badge_label'));
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
        $first = $service->activateSearchPromotion($this->createProduct(), Seller::findOrFail(1));
        $second = $service->activateSearchPromotion($this->createProduct(), Seller::findOrFail(1));
        $service->reviewPromotion($first, 'approve', 99, 'First slot', 'search_results', null, 1);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('seller_package_search_promotion_limit_has_been_reached');
        $service->reviewPromotion($second, 'approve', 99, 'Second slot', 'search_results', null, 2);
    }

    public function test_rejected_request_never_consumes_advertising_quota(): void
    {
        $subscription = $this->createSubscription(searchLimit: 1);
        $service = app(SellerProductPromotionService::class);
        $request = $service->activateSearchPromotion($this->createProduct(), Seller::findOrFail(1));

        $rejected = $service->reviewPromotion($request, 'reject', 99, 'Creative does not meet policy');

        $this->assertSame(SellerProductPromotion::APPROVAL_REJECTED, $rejected->approval_status);
        $this->assertSame(SellerProductPromotion::STATUS_CANCELLED, $rejected->status);
        $this->assertSame(0, $subscription->fresh()->used_search_promotion_limit);
        $this->assertDatabaseMissing('seller_package_transactions', ['seller_product_promotion_id' => $request->id]);
    }

    public function test_admin_cannot_assign_two_campaigns_to_the_same_slot_and_period(): void
    {
        $this->createSubscription(searchLimit: 2);
        $service = app(SellerProductPromotionService::class);
        $first = $service->activateSearchPromotion($this->createProduct(), Seller::findOrFail(1));
        $second = $service->activateSearchPromotion($this->createProduct(), Seller::findOrFail(1));
        $service->reviewPromotion($first, 'approve', 99, 'Slot one', 'keyword_search', 'mask', 1);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('advertising_placement_conflicts_with_an_existing_campaign');
        $service->reviewPromotion($second, 'approve', 99, 'Same slot', 'keyword_search', 'mask', 1);
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

    public function test_legacy_listing_expiry_does_not_block_advertising_request(): void
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

        $promotion = app(SellerProductPromotionService::class)->activateSearchPromotion($product, Seller::findOrFail(1));
        $this->assertSame(SellerProductPromotion::APPROVAL_PENDING, $promotion->approval_status);
        $this->assertSame(0, $subscription->fresh()->used_search_promotion_limit);
    }

    public function test_expired_promotion_is_removed_without_restoring_used_quota(): void
    {
        $subscription = $this->createSubscription(searchLimit: 1);
        $service = app(SellerProductPromotionService::class);
        $promotion = $service->activateSearchPromotion($this->createProduct(), Seller::findOrFail(1));
        $promotion = $service->reviewPromotion($promotion, 'approve', 99, 'Approved', 'search_results', null, 1);
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

    public function test_lower_package_priority_wins_before_manual_promotion_order(): void
    {
        $priorityOne = $this->createProduct(name: 'Priority One');
        $priorityThree = $this->createProduct(name: 'Priority Three');
        $priorityOne->setRelation('activeSearchPromotion', new SellerProductPromotion([
            'search_priority' => 1,
            'sort_order' => 1,
        ]));
        $priorityThree->setRelation('activeSearchPromotion', new SellerProductPromotion([
            'search_priority' => 3,
            'sort_order' => 999,
        ]));

        $sorted = ProductManager::prioritizeSearchPromotions(collect([$priorityThree, $priorityOne]));

        $this->assertSame($priorityOne->id, $sorted->first()->id);
    }

    public function test_keyword_campaign_is_featured_only_for_its_approved_keyword(): void
    {
        $product = $this->createProduct(name: 'Medical Mask');
        $product->setRelation('activeSearchPromotion', new SellerProductPromotion([
            'placement_scope' => 'keyword_search',
            'placement_key' => 'mask',
            'search_priority' => 1,
            'sort_order' => 1,
        ]));

        $matching = ProductManager::prioritizeSearchPromotions(collect([$product]), 'mask')->first();
        $this->assertTrue($matching->is_search_promoted);
        $notMatching = ProductManager::prioritizeSearchPromotions(collect([$product]), 'gloves')->first();

        $this->assertFalse($notMatching->is_search_promoted);
    }

    public function test_package_performance_is_aggregated_without_customer_identity(): void
    {
        $subscription = $this->createSubscription(searchLimit: 1);
        $product = $this->createProduct();
        SellerProductEntitlement::create([
            'seller_id' => 1,
            'product_id' => $product->id,
            'seller_package_subscription_id' => $subscription->id,
            'seller_package_id' => 1,
            'duration_days' => 30,
            'status' => SellerProductEntitlement::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(29),
            'activated_at' => now()->subDay(),
        ]);
        SellerProductPromotion::create([
            'seller_id' => 1,
            'product_id' => $product->id,
            'seller_package_subscription_id' => $subscription->id,
            'promotion_type' => SellerProductPromotion::TYPE_SEARCH,
            'duration_days' => 7,
            'status' => SellerProductPromotion::STATUS_ACTIVE,
            'approval_status' => SellerProductPromotion::APPROVAL_APPROVED,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(6),
            'activated_at' => now()->subDay(),
        ]);
        DB::table('seller_package_product_metrics')->insert([
            'seller_id' => 1,
            'seller_package_subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'metric_date' => today(),
            'impressions' => 20,
            'visits' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('order_details')->insert([
            'order_id' => 10,
            'seller_id' => 1,
            'product_id' => $product->id,
            'delivery_status' => 'delivered',
            'qty' => 2,
            'price' => 100,
            'discount' => 10,
            'tax' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(SellerPackagePerformanceService::class)->report($subscription);

        $this->assertSame(1, $report['published_products']);
        $this->assertSame(20, $report['impressions']);
        $this->assertSame(5, $report['visits']);
        $this->assertSame(1, $report['orders']);
        $this->assertSame(2, $report['units_sold']);
        $this->assertSame(190.0, $report['sales_amount']);
        $this->assertArrayNotHasKey('customer_id', $report);
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
        $this->assertSame(0, $subscription->fresh()->used_homepage_promotion_limit);
        $this->assertSame(0, $subscription->fresh()->used_search_promotion_limit);
        $approved = $service->reviewPromotion($first, 'approve', 99, 'Approved', 'homepage_featured', null, 1);
        $this->assertSame(1, $subscription->fresh()->used_homepage_promotion_limit);
        $this->assertSame(14.0, $approved->starts_at->diffInDays($approved->expires_at));
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

        $search = $service->activateSearchPromotion($product, Seller::findOrFail(1));
        $homepage = $service->activateHomepagePromotion($product, Seller::findOrFail(1));
        $service->reviewPromotion($search, 'approve', 99, 'Approved search', 'search_results', null, 1);
        $service->reviewPromotion($homepage, 'approve', 99, 'Approved homepage', 'homepage_featured', null, 1);

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
            'approval_status' => SellerProductPromotion::APPROVAL_APPROVED,
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
            $table->unsignedInteger('search_priority')->default(100);
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
            $table->unsignedInteger('search_priority')->default(100);
            $table->string('status')->default('reserved');
            $table->string('approval_status')->default('pending_admin_review');
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
        Schema::create('seller_package_product_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('seller_package_subscription_id');
            $table->unsignedBigInteger('product_id');
            $table->date('metric_date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('visits')->default(0);
            $table->timestamps();
        });
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('product_id');
            $table->string('delivery_status')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('price', 24, 2)->default(0);
            $table->decimal('discount', 24, 2)->default(0);
            $table->decimal('tax', 24, 2)->default(0);
            $table->timestamps();
        });
    }
}
