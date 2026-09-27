<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Seller;
use App\Services\SellerProductReviewService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerProductReviewServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('approved');
            $table->string('cm_firebase_token')->nullable();
            $table->string('app_language')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('added_by');
            $table->integer('request_status')->default(0);
            $table->integer('status')->default(0);
            $table->integer('featured')->default(0);
            $table->integer('is_homepage_visible')->default(0);
            $table->text('denied_note')->nullable();
            $table->string('seller_review_status')->nullable();
            $table->text('seller_review_reason')->nullable();
            $table->json('seller_review_changes')->nullable();
            $table->timestamp('seller_submitted_at')->nullable();
            $table->timestamp('seller_reviewed_at')->nullable();
            $table->unsignedBigInteger('seller_reviewed_by')->nullable();
            $table->date('production_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_product_review_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('seller_id');
            $table->string('status');
            $table->text('reason')->nullable();
            $table->json('changed_fields')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('seller_product_review_decisions');
        Schema::dropIfExists('products');
        Schema::dropIfExists('sellers');
        parent::tearDown();
    }

    public function test_submission_approval_and_suspension_keep_legacy_columns_and_auditable_history(): void
    {
        $seller = Seller::create(['status' => 'approved']);
        $product = Product::query()->withoutGlobalScopes()->create([
            'user_id' => $seller->id,
            'added_by' => 'seller',
            'production_date' => today()->subDay(),
            'expiry_date' => today()->addMonth(),
        ]);
        $service = app(SellerProductReviewService::class);

        $submitted = $service->submit($product, ['اسم المنتج', 'سعر الوحدة']);
        $this->assertSame(['اسم المنتج', 'سعر الوحدة'], $submitted->seller_review_changes);
        $approved = $service->transition($submitted, SellerProductReviewService::APPROVED_PUBLISHED, 'Approved after document review', 7);
        $this->assertSame('Approved after document review', $approved->seller_review_reason);
        $suspended = $service->transition($approved, SellerProductReviewService::SUSPENDED, 'Awaiting document correction', 8);

        $this->assertSame(SellerProductReviewService::SUSPENDED, $suspended->seller_review_status);
        $this->assertSame(1, $suspended->request_status);
        $this->assertSame(0, $suspended->status);
        $this->assertSame('Awaiting document correction', $suspended->seller_review_reason);
        $this->assertDatabaseHas('seller_product_review_decisions', [
            'product_id' => $product->id,
            'status' => SellerProductReviewService::APPROVED_PUBLISHED,
            'reason' => 'Approved after document review',
            'admin_id' => 7,
        ]);
        $this->assertDatabaseCount('seller_product_review_decisions', 3);
        $this->assertDatabaseHas('seller_product_review_decisions', [
            'product_id' => $product->id,
            'status' => SellerProductReviewService::APPROVED_PUBLISHED,
            'admin_id' => 7,
        ]);
    }

    public function test_rejection_and_deletion_require_a_reason(): void
    {
        $seller = Seller::create(['status' => 'approved']);
        $product = Product::query()->withoutGlobalScopes()->create([
            'user_id' => $seller->id,
            'added_by' => 'seller',
        ]);
        $service = app(SellerProductReviewService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('seller_product_review_reason_is_required');
        $service->transition($product, SellerProductReviewService::REJECTED);
    }

    public function test_legacy_product_status_maps_to_the_new_status_name(): void
    {
        $seller = Seller::create(['status' => 'approved']);
        $product = Product::query()->withoutGlobalScopes()->create([
            'user_id' => $seller->id,
            'added_by' => 'seller',
            'request_status' => 1,
            'status' => 1,
        ]);

        $this->assertSame(SellerProductReviewService::APPROVED_PUBLISHED, app(SellerProductReviewService::class)->statusFor($product));
    }

    public function test_missing_or_expired_dates_block_publication(): void
    {
        $seller = Seller::create(['status' => 'approved']);
        $service = app(SellerProductReviewService::class);
        $missingDates = Product::query()->withoutGlobalScopes()->create([
            'user_id' => $seller->id,
            'added_by' => 'seller',
        ]);

        try {
            $service->transition($missingDates, SellerProductReviewService::APPROVED_PUBLISHED, 'Approved', 7);
            $this->fail('A product without dates must not be published.');
        } catch (DomainException $exception) {
            $this->assertSame('product_production_date_is_required', $exception->getMessage());
        }

        $expired = Product::query()->withoutGlobalScopes()->create([
            'user_id' => $seller->id,
            'added_by' => 'seller',
            'production_date' => today()->subMonth(),
            'expiry_date' => today()->subDay(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('expired_product_cannot_be_submitted_or_published');
        $service->transition($expired, SellerProductReviewService::APPROVED_PUBLISHED, 'Approved', 7);
    }
}
