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

    public function test_product_quota_is_reserved_once_and_limit_cannot_be_bypassed(): void
    {
        $subscription = $this->createSubscription(productLimit: 1);
        $product = $this->createProduct();
        $service = app(SellerProductEntitlementService::class);

        $first = $service->reserveForProduct($product, Seller::findOrFail(1));
        $second = $service->reserveForProduct($product, Seller::findOrFail(1));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $subscription->fresh()->used_product_limit);
        $this->assertDatabaseCount('seller_product_entitlements', 1);
        $this->assertDatabaseHas('seller_package_transactions', [
            'transaction_type' => 'quota_usage',
            'debit' => 1,
            'balance_after' => 0,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('seller_package_product_limit_has_been_reached');
        $service->reserveForProduct($this->createProduct(), Seller::findOrFail(1));
    }

    public function test_publication_starts_duration_only_once(): void
    {
        $this->createSubscription(productLimit: 2, durationDays: 14);
        $product = $this->createProduct(requestStatus: 1);
        $service = app(SellerProductEntitlementService::class);
        $service->reserveForProduct($product, Seller::findOrFail(1));

        $first = $service->activateForPublication($product, Seller::findOrFail(1));
        $second = $service->activateForPublication($product, Seller::findOrFail(1));

        $this->assertSame(SellerProductEntitlement::STATUS_ACTIVE, $first->status);
        $this->assertSame($first->starts_at->toDateTimeString(), $second->starts_at->toDateTimeString());
        $this->assertSame($first->expires_at->toDateTimeString(), $second->expires_at->toDateTimeString());
        $this->assertSame(14.0, $first->starts_at->diffInDays($first->expires_at));
    }

    public function test_rejection_before_publication_restores_quota_only_once(): void
    {
        $subscription = $this->createSubscription(productLimit: 2);
        $product = $this->createProduct();
        $service = app(SellerProductEntitlementService::class);
        $service->reserveForProduct($product, Seller::findOrFail(1));

        $service->restoreAfterRejection($product);
        $service->restoreAfterRejection($product);

        $entitlement = SellerProductEntitlement::firstOrFail();
        $this->assertSame(SellerProductEntitlement::STATUS_RESTORED, $entitlement->status);
        $this->assertTrue($entitlement->quota_restored);
        $this->assertSame(0, $subscription->fresh()->used_product_limit);
        $this->assertSame(1, DB::table('seller_package_transactions')->where('transaction_type', 'quota_restore')->count());
    }

    public function test_deleting_published_product_does_not_restore_consumed_quota(): void
    {
        $subscription = $this->createSubscription(productLimit: 2);
        $product = $this->createProduct(requestStatus: 1);
        $service = app(SellerProductEntitlementService::class);
        $service->reserveForProduct($product, Seller::findOrFail(1));
        $service->activateForPublication($product, Seller::findOrFail(1));

        $service->cancelForDeletion($product);

        $this->assertSame(1, $subscription->fresh()->used_product_limit);
        $this->assertSame(SellerProductEntitlement::STATUS_CANCELLED, SellerProductEntitlement::firstOrFail()->status);
        $this->assertSame(0, DB::table('seller_package_transactions')->where('transaction_type', 'quota_restore')->count());
    }

    public function test_expired_publication_is_deactivated(): void
    {
        $this->createSubscription(productLimit: 2);
        $product = $this->createProduct(requestStatus: 1, status: 1);
        $service = app(SellerProductEntitlementService::class);
        $entitlement = $service->reserveForProduct($product, Seller::findOrFail(1));
        $entitlement->update([
            'status' => SellerProductEntitlement::STATUS_ACTIVE,
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->subMinute(),
            'activated_at' => now()->subDays(2),
        ]);

        $this->assertSame(1, $service->expireDueEntitlements());
        $this->assertSame(SellerProductEntitlement::STATUS_EXPIRED, $entitlement->fresh()->status);
        $this->assertSame(0, $product->fresh()->status);
    }

    public function test_expired_package_cannot_reserve_new_product(): void
    {
        $this->createSubscription(productLimit: 2, expiresAt: now()->subMinute());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('active_seller_package_is_required_before_adding_products');
        app(SellerProductEntitlementService::class)->reserveForProduct(
            $this->createProduct(),
            Seller::findOrFail(1)
        );
    }

    public function test_active_scope_keeps_legacy_products_and_hides_expired_managed_products(): void
    {
        $subscription = $this->createSubscription(productLimit: 3);
        $legacyProduct = $this->createProduct(requestStatus: 1, status: 1);
        $expiredProduct = $this->createProduct(requestStatus: 1, status: 1);
        $activeProduct = $this->createProduct(requestStatus: 1, status: 1);

        SellerProductEntitlement::create([
            'seller_id' => 1,
            'product_id' => $expiredProduct->id,
            'seller_package_subscription_id' => $subscription->id,
            'seller_package_id' => 1,
            'duration_days' => 30,
            'status' => SellerProductEntitlement::STATUS_ACTIVE,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->subMinute(),
            'activated_at' => now()->subMonth(),
        ]);
        SellerProductEntitlement::create([
            'seller_id' => 1,
            'product_id' => $activeProduct->id,
            'seller_package_subscription_id' => $subscription->id,
            'seller_package_id' => 1,
            'duration_days' => 30,
            'status' => SellerProductEntitlement::STATUS_ACTIVE,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'activated_at' => now(),
        ]);

        $visibleIds = Product::query()
            ->withoutGlobalScope('translate')
            ->active()
            ->pluck('id');

        $this->assertTrue($visibleIds->contains($legacyProduct->id));
        $this->assertTrue($visibleIds->contains($activeProduct->id));
        $this->assertFalse($visibleIds->contains($expiredProduct->id));
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
