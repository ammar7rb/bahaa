<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Models\SellerProductEntitlement;
use App\Services\SellerProductEntitlementService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerProductEntitlementServiceTest extends TestCase
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

    public function test_product_can_be_reserved_without_any_package_or_product_quota(): void
    {
        $product = $this->createProduct();
        $service = app(SellerProductEntitlementService::class);

        $this->assertNull($service->reserveForProduct($product, Seller::findOrFail(1)));
        $this->assertNull($service->reserveForProduct($this->createProduct(), Seller::findOrFail(1)));
        $this->assertDatabaseCount('seller_product_entitlements', 0);
        $this->assertDatabaseCount('seller_package_transactions', 0);
    }

    public function test_expired_or_exhausted_advertising_package_does_not_block_product_creation(): void
    {
        $subscription = $this->createSubscription(productLimit: 0, expiresAt: now()->subMinute());

        $this->assertNull(app(SellerProductEntitlementService::class)->reserveForProduct(
            $this->createProduct(),
            Seller::findOrFail(1)
        ));
        $this->assertSame(0, $subscription->fresh()->used_product_limit);
    }

    public function test_approved_product_can_be_published_without_creating_package_entitlement(): void
    {
        $product = $this->createProduct(requestStatus: 1);
        $service = app(SellerProductEntitlementService::class);

        $this->assertNull($service->activateForPublication($product, Seller::findOrFail(1)));
        $this->assertDatabaseCount('seller_product_entitlements', 0);
    }

    public function test_unapproved_product_still_cannot_be_published(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('product_must_be_approved_before_publication');
        app(SellerProductEntitlementService::class)->activateForPublication(
            $this->createProduct(requestStatus: 0),
            Seller::findOrFail(1)
        );
    }

    public function test_expired_legacy_entitlement_is_archived_without_unpublishing_product(): void
    {
        $subscription = $this->createSubscription(productLimit: 1);
        $product = $this->createProduct(requestStatus: 1, status: 1);
        $entitlement = SellerProductEntitlement::create([
            'seller_id' => 1, 'product_id' => $product->id,
            'seller_package_subscription_id' => $subscription->id, 'seller_package_id' => 1,
            'duration_days' => 30, 'status' => SellerProductEntitlement::STATUS_ACTIVE,
            'starts_at' => now()->subMonth(), 'expires_at' => now()->subMinute(), 'activated_at' => now()->subMonth(),
        ]);

        $this->assertSame(1, app(SellerProductEntitlementService::class)->expireDueEntitlements());
        $this->assertSame(SellerProductEntitlement::STATUS_EXPIRED, $entitlement->fresh()->status);
        $this->assertSame(1, $product->fresh()->status);
        $this->assertTrue(Product::query()->withoutGlobalScope('translate')->active()->pluck('id')->contains($product->id));
    }

    private function createSubscription(
        int $productLimit,
        int $durationDays = 30,
        mixed $expiresAt = null
    ): SellerPackageSubscription {
        return SellerPackageSubscription::create([
            'seller_id' => 1,
            'seller_package_id' => 1,
            'package_name' => 'Test Package',
            'paid_package_price' => 100,
            'product_limit' => $productLimit,
            'used_product_limit' => 0,
            'product_adjustment_limit' => 0,
            'product_duration_days' => $durationDays,
            'status' => SellerPackageSubscription::STATUS_ACTIVE,
            'payment_status' => 'paid',
            'starts_at' => now()->subDay(),
            'expires_at' => $expiresAt ?? now()->addMonth(),
            'activated_at' => now()->subDay(),
        ]);
    }

    private function createProduct(int $requestStatus = 0, int $status = 0): Product
    {
        return Product::query()->withoutGlobalScopes()->create([
            'user_id' => 1,
            'added_by' => 'seller',
            'name' => 'Test Product '.uniqid(),
            'brand_id' => null,
            'product_type' => 'physical',
            'status' => $status,
            'request_status' => $requestStatus,
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
        Schema::create('seller_insurances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 24, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->uuid('payment_request_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('forfeited_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('added_by');
            $table->string('name')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('product_type')->default('physical');
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
