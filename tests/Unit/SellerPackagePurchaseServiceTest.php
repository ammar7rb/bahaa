<?php

namespace Tests\Unit;

use App\Models\PaymentRequest;
use App\Models\Seller;
use App\Models\SellerInsurance;
use App\Models\SellerPackage;
use App\Models\SellerPackageSubscription;
use App\Models\SellerPackageTransaction;
use App\Services\SellerPackagePurchaseService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerPackagePurchaseServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        DB::table('business_settings')->insert([
            ['type' => 'seller_insurance_status', 'value' => '1'],
            ['type' => 'seller_insurance_amount', 'value' => '100'],
            ['type' => 'seller_insurance_repayment_after_forfeiture', 'value' => '1'],
        ]);
        DB::table('sellers')->insert(['id' => 1, 'status' => 'approved']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        foreach ([
            'payment_requests',
            'seller_package_transactions',
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

    public function test_package_purchase_is_blocked_without_active_insurance(): void
    {
        $this->expectException(DomainException::class);
        app(SellerPackagePurchaseService::class)->getOrCreatePendingSubscription(
            Seller::findOrFail(1),
            $this->createPackage()
        );
    }

    public function test_pending_subscription_keeps_package_snapshot_after_admin_edits_package(): void
    {
        $this->grantInsurance();
        $package = $this->createPackage();
        $subscription = app(SellerPackagePurchaseService::class)
            ->getOrCreatePendingSubscription(Seller::findOrFail(1), $package);

        $package->update(['product_limit' => 99, 'package_price' => 999]);
        $subscription->refresh();

        $this->assertSame(10, $subscription->product_limit);
        $this->assertSame(250.0, (float) $subscription->paid_package_price);
    }

    public function test_offline_payment_requires_approval_replaces_old_package_and_grants_quotas_once(): void
    {
        $this->grantInsurance();
        $package = $this->createPackage();
        $oldSubscription = $this->createActiveSubscription($package, 'Old Package');
        $service = app(SellerPackagePurchaseService::class);
        $subscription = $service->getOrCreatePendingSubscription(Seller::findOrFail(1), $package);
        $subscription = $service->submitOfflinePayment($subscription, [
            'method_id' => 1,
            'method_name' => 'Manual Transfer',
            'payment_proof' => ['image_name' => 'proof.webp', 'storage' => 'public'],
        ]);

        $this->assertSame(SellerPackageSubscription::STATUS_PENDING_REVIEW, $subscription->status);
        $this->assertSame('unpaid', $subscription->payment_status);
        $this->assertSame(SellerPackageSubscription::STATUS_ACTIVE, $oldSubscription->fresh()->status);

        $result = $service->markPaid($subscription, [
            'payment_method' => 'offline_payment',
            'transaction_id' => 'manual-package-1',
            'payment_amount' => 250,
            'currency_code' => 'USD',
        ], 8, 'Verified.');

        $this->assertSame(1, $result['status']);
        $this->assertSame(SellerPackageSubscription::STATUS_ACTIVE, $result['subscription']->status);
        $this->assertSame('paid', $result['subscription']->payment_status);
        $this->assertSame(SellerPackageSubscription::STATUS_REPLACED, $oldSubscription->fresh()->status);
        $this->assertNotNull($result['subscription']->expires_at);
        $this->assertSame(4, SellerPackageTransaction::where('seller_package_subscription_id', $subscription->id)->count());
        $this->assertSame(250.0, (float) SellerPackageTransaction::where('seller_package_subscription_id', $subscription->id)->sum('paid_amount'));

        $duplicate = $service->markPaid($result['subscription'], [
            'payment_method' => 'offline_payment',
            'transaction_id' => 'manual-package-1',
        ], 8);
        $this->assertSame('already_paid', $duplicate['message']);
        $this->assertSame(4, SellerPackageTransaction::where('seller_package_subscription_id', $subscription->id)->count());
    }

    public function test_rejected_offline_payment_allows_a_new_purchase_attempt(): void
    {
        $this->grantInsurance();
        $package = $this->createPackage();
        $service = app(SellerPackagePurchaseService::class);
        $first = $service->getOrCreatePendingSubscription(Seller::findOrFail(1), $package);
        $first = $service->submitOfflinePayment($first, ['method_id' => 1]);
        $rejected = $service->rejectOfflinePayment($first, 5, 'Invalid proof.');
        $second = $service->getOrCreatePendingSubscription(Seller::findOrFail(1), $package);

        $this->assertSame(SellerPackageSubscription::STATUS_REJECTED, $rejected->status);
        $this->assertNotSame($rejected->id, $second->id);
    }

    public function test_verified_payment_request_hook_activates_package_once(): void
    {
        $this->grantInsurance();
        $subscription = app(SellerPackagePurchaseService::class)->getOrCreatePendingSubscription(
            Seller::findOrFail(1),
            $this->createPackage()
        );
        $paymentRequest = PaymentRequest::create([
            'payer_id' => '1',
            'receiver_id' => '100',
            'payment_amount' => 250,
            'success_hook' => 'seller_package_payment_success',
            'failure_hook' => 'seller_package_payment_fail',
            'transaction_id' => 'gateway-package-1',
            'currency_code' => 'USD',
            'payment_method' => 'stripe',
            'additional_data' => json_encode([
                'seller_id' => 1,
                'seller_package_subscription_id' => $subscription->id,
            ]),
            'is_paid' => 1,
            'attribute_id' => (string) $subscription->id,
            'attribute' => 'seller_package_subscription',
            'payment_platform' => 'web',
        ]);

        seller_package_payment_success($paymentRequest);
        seller_package_payment_success($paymentRequest->fresh());
        $subscription->refresh();

        $this->assertSame(SellerPackageSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame((string) $paymentRequest->id, $subscription->payment_request_id);
        $this->assertSame(4, SellerPackageTransaction::where('seller_package_subscription_id', $subscription->id)->count());
    }

    private function grantInsurance(): void
    {
        SellerInsurance::create([
            'seller_id' => 1,
            'transaction_id' => '10000000-0000-4000-8000-000000000001',
            'amount' => 100,
            'status' => SellerInsurance::STATUS_PAID,
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    private function createPackage(): SellerPackage
    {
        return SellerPackage::create([
            'name' => 'Ultra',
            'slug' => 'ultra',
            'package_price' => 250,
            'product_limit' => 10,
            'product_duration_days' => 30,
            'search_promotion_limit' => 2,
            'search_promotion_duration_days' => 7,
            'homepage_promotion_limit' => 1,
            'homepage_promotion_duration_days' => 5,
            'package_validity_days' => 60,
            'status' => true,
            'sort_order' => 1,
        ]);
    }

    private function createActiveSubscription(SellerPackage $package, string $name): SellerPackageSubscription
    {
        return SellerPackageSubscription::create([
            'seller_id' => 1,
            'seller_package_id' => $package->id,
            'package_name' => $name,
            'paid_package_price' => 100,
            'product_limit' => 3,
            'product_duration_days' => 10,
            'status' => SellerPackageSubscription::STATUS_ACTIVE,
            'payment_status' => 'paid',
            'starts_at' => now()->subDay(),
            'activated_at' => now()->subDay(),
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
            $table->string('status')->default('approved');
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
            $table->unsignedInteger('package_validity_days')->nullable();
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
            $table->unsignedInteger('package_validity_days')->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->char('payment_request_id', 36)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
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
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('payer_id', 64)->nullable();
            $table->string('receiver_id', 64)->nullable();
            $table->decimal('payment_amount', 40, 20)->default(0);
            $table->string('success_hook')->nullable();
            $table->string('failure_hook')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('currency_code')->default('USD');
            $table->string('payment_method')->nullable();
            $table->longText('additional_data')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->string('attribute_id')->nullable();
            $table->string('attribute')->nullable();
            $table->string('payment_platform')->nullable();
            $table->timestamps();
        });
    }
}
