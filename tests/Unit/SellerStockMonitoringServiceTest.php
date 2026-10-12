<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerStockViolation;
use App\Services\SellerStockMonitoringService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerStockMonitoringServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->integer('stock_limit')->default(0);
            $table->string('status')->default('approved');
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('added_by');
            $table->string('product_type')->default('physical');
            $table->integer('request_status')->default(1);
            $table->integer('current_stock')->default(0);
            $table->timestamps();
        });
        Schema::create('seller_stock_violations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('alert_type');
            $table->string('decision');
            $table->string('policy_reference')->nullable();
            $table->text('reason');
            $table->decimal('penalty_amount', 12, 2)->nullable();
            $table->unsignedBigInteger('decided_by');
            $table->timestamp('detected_at');
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('seller_stock_violations');
        Schema::dropIfExists('products');
        Schema::dropIfExists('sellers');
        parent::tearDown();
    }

    public function test_stock_alert_never_creates_a_violation_automatically(): void
    {
        $seller = Seller::create(['stock_limit' => 5]);
        $product = Product::query()->withoutGlobalScopes()->create([
            'user_id' => $seller->id,
            'added_by' => 'seller',
            'product_type' => 'physical',
            'request_status' => 1,
            'current_stock' => 0,
        ]);

        $service = app(SellerStockMonitoringService::class);

        $this->assertSame(SellerStockMonitoringService::ALERT_OUT_OF_STOCK, $service->alertTypeFor($product, $seller));
        $this->assertDatabaseCount('seller_stock_violations', 0);
    }

    public function test_stock_at_the_configured_limit_is_a_low_stock_alert(): void
    {
        $seller = Seller::create(['stock_limit' => 5]);
        $product = Product::query()->withoutGlobalScopes()->create([
            'user_id' => $seller->id,
            'added_by' => 'seller',
            'product_type' => 'physical',
            'request_status' => 1,
            'current_stock' => 5,
        ]);

        $this->assertSame(
            SellerStockMonitoringService::ALERT_LOW_STOCK,
            app(SellerStockMonitoringService::class)->alertTypeFor($product, $seller)
        );
    }

    public function test_admin_can_document_warning_or_penalty_after_a_stock_alert(): void
    {
        $seller = Seller::create(['stock_limit' => 5]);
        $product = Product::query()->withoutGlobalScopes()->create([
            'user_id' => $seller->id,
            'added_by' => 'seller',
            'product_type' => 'physical',
            'request_status' => 1,
            'current_stock' => 3,
        ]);

        $violation = app(SellerStockMonitoringService::class)->recordManualDecision(
            product: $product,
            seller: $seller,
            decision: SellerStockViolation::DECISION_PENALTY,
            reason: 'Stock was not updated after a customer order.',
            adminId: 17,
            penaltyAmount: 25.50,
            policyReference: 'STOCK-01',
            orderId: 99,
        );

        $this->assertSame(SellerStockViolation::DECISION_PENALTY, $violation->decision);
        $this->assertDatabaseHas('seller_stock_violations', [
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'decision' => SellerStockViolation::DECISION_PENALTY,
            'policy_reference' => 'STOCK-01',
            'decided_by' => 17,
        ]);
    }

    public function test_penalty_requires_a_documented_positive_amount(): void
    {
        $seller = Seller::create(['stock_limit' => 5]);
        $product = Product::query()->withoutGlobalScopes()->create([
            'user_id' => $seller->id,
            'added_by' => 'seller',
            'product_type' => 'physical',
            'request_status' => 1,
            'current_stock' => 0,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('seller_stock_violation_penalty_amount_is_required');
        app(SellerStockMonitoringService::class)->recordManualDecision(
            product: $product,
            seller: $seller,
            decision: SellerStockViolation::DECISION_PENALTY,
            reason: 'Reason',
            adminId: 1,
        );
    }
}
