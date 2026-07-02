<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Models\SellerPackageTransaction;
use App\Models\SellerProductPromotion;
use App\Services\SellerQuotaAdjustmentService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SellerQuotaAdjustmentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        DB::table('sellers')->insert(['id' => 1, 'status' => 'approved']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        foreach (['seller_package_transactions', 'seller_product_promotions', 'seller_package_subscriptions', 'sellers'] as $table) {
            Schema::dropIfExists($table);
        }
        Cache::flush();
        parent::tearDown();
    }

    public function test_admin_can_add_product_quota_with_audited_transaction(): void
    {
        $subscription = $this->createSubscription();
        $result = $this->service()->adjustQuota(
            Seller::findOrFail(1),
            SellerPackageTransaction::QUOTA_PRODUCT,
            'add',
            3,
            9,
            'Customer support compensation',
            (string) Str::uuid()
        );

        $this->assertTrue($result['applied']);
        $this->assertSame(3, $subscription->fresh()->product_adjustment_limit);
        $this->assertDatabaseHas('seller_package_transactions', [
            'transaction_type' => SellerPackageTransaction::TYPE_QUOTA_ADJUSTMENT,
            'quota_type' => SellerPackageTransaction::QUOTA_PRODUCT,
            'credit' => 3,
            'debit' => 0,
            'balance_after' => 8,
            'created_by_admin_id' => 9,
        ]);
    }

    public function test_adjustment_request_token_prevents_double_application(): void
    {
        $subscription = $this->createSubscription();
        $token = (string) Str::uuid();
        $service = $this->service();

        $first = $service->adjustQuota(Seller::findOrFail(1), 'search_promotion', 'add', 2, 9, 'Bonus', $token);
        $second = $service->adjustQuota(Seller::findOrFail(1), 'search_promotion', 'add', 2, 9, 'Bonus', $token);

        $this->assertTrue($first['applied']);
        $this->assertFalse($second['applied']);
        $this->assertSame(2, $subscription->fresh()->search_promotion_adjustment_limit);
        $this->assertSame(1, SellerPackageTransaction::where('reference', $token)->count());
    }

    public function test_quota_cannot_be_deducted_below_already_used_amount(): void
    {
        $subscription = $this->createSubscription();
        $subscription->update(['used_product_limit' => 4]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('quota_cannot_be_reduced_below_the_already_used_amount');
        try {
            $this->service()->adjustQuota(
                Seller::findOrFail(1),
                SellerPackageTransaction::QUOTA_PRODUCT,
                'deduct',
                2,
                9,
                'Correction',
                (string) Str::uuid()
            );
        } finally {
            $this->assertSame(0, $subscription->fresh()->product_adjustment_limit);
        }
    }

    public function test_each_quota_adjustment_is_independent(): void
    {
        $subscription = $this->createSubscription();
        $service = $this->service();

        $service->adjustQuota(Seller::findOrFail(1), 'product', 'add', 1, 9, 'Product bonus', (string) Str::uuid());
        $service->adjustQuota(Seller::findOrFail(1), 'homepage_promotion', 'add', 4, 9, 'Homepage bonus', (string) Str::uuid());

        $subscription->refresh();
        $this->assertSame(1, $subscription->product_adjustment_limit);
        $this->assertSame(0, $subscription->search_promotion_adjustment_limit);
        $this->assertSame(4, $subscription->homepage_promotion_adjustment_limit);
    }

    public function test_admin_can_update_promotion_order_and_cancel_without_refunding_quota(): void
    {
        $subscription = $this->createSubscription();
        $subscription->update(['used_homepage_promotion_limit' => 1]);
        $promotion = $this->createPromotion();
        $service = $this->service();

        $updated = $service->updatePromotion($promotion, 'update_order', 50, 9, 'Priority placement');
        $cancelled = $service->updatePromotion($updated, 'cancel', 50, 9, 'Policy issue');

        $this->assertSame(50, $updated->sort_order);
        $this->assertSame(SellerProductPromotion::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame(1, $subscription->fresh()->used_homepage_promotion_limit);
        $this->assertSame(2, SellerPackageTransaction::where('transaction_type', SellerPackageTransaction::TYPE_PROMOTION_ADMIN_UPDATE)->count());
        $this->assertSame(0, SellerPackageTransaction::where('transaction_type', SellerPackageTransaction::TYPE_QUOTA_RESTORE)->count());
    }

    private function service(): SellerQuotaAdjustmentService
    {
        return app(SellerQuotaAdjustmentService::class);
    }

    private function createSubscription(): SellerPackageSubscription
    {
        return SellerPackageSubscription::create([
            'seller_id' => 1,
            'seller_package_id' => 1,
            'package_name' => 'Admin Control Package',
            'paid_package_price' => 100,
            'product_limit' => 5,
            'used_product_limit' => 0,
            'product_adjustment_limit' => 0,
            'product_duration_days' => 30,
            'search_promotion_limit' => 2,
            'used_search_promotion_limit' => 0,
            'search_promotion_adjustment_limit' => 0,
            'search_promotion_duration_days' => 7,
            'homepage_promotion_limit' => 1,
            'used_homepage_promotion_limit' => 0,
            'homepage_promotion_adjustment_limit' => 0,
            'homepage_promotion_duration_days' => 7,
            'status' => SellerPackageSubscription::STATUS_ACTIVE,
            'payment_status' => 'paid',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'activated_at' => now()->subDay(),
        ]);
    }

    private function createPromotion(): SellerProductPromotion
    {
        return SellerProductPromotion::create([
            'seller_id' => 1,
            'product_id' => 10,
            'seller_package_subscription_id' => 1,
            'promotion_type' => SellerProductPromotion::TYPE_HOMEPAGE,
            'duration_days' => 7,
            'sort_order' => 0,
            'status' => SellerProductPromotion::STATUS_ACTIVE,
            'starts_at' => now(),
            'expires_at' => now()->addDays(7),
            'activated_at' => now(),
        ]);
    }

    private function createTables(): void
    {
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
