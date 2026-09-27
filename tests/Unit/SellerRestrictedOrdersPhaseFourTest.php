<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\SellerOrderInsurance;
use App\Services\OrderCommerceContractService;
use App\Services\SellerOrderInsuranceService;
use App\Support\Commerce\OrderCommerceState;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SellerRestrictedOrdersPhaseFourTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('orders', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('seller_id'); $table->string('seller_is')->default('seller');
            $table->decimal('order_amount', 24, 3)->default(0); $table->string('order_status')->default('pending');
            $table->string('commerce_flow_version')->nullable(); $table->string('commerce_flow_status')->nullable();
            $table->string('post_purchase_status')->nullable();
            $table->timestamp('commerce_flow_status_updated_at')->nullable(); $table->uuid('seller_restricted_access_token')->nullable()->unique();
            $table->string('operational_blocked_by')->nullable(); $table->text('operational_block_reason')->nullable(); $table->timestamps();
        });
        Schema::create('seller_order_insurances', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('order_id'); $table->unsignedBigInteger('seller_id');
            $table->decimal('amount', 24, 3); $table->decimal('order_amount', 24, 3); $table->decimal('percentage', 8, 4)->default(0);
            $table->string('amount_source')->nullable(); $table->string('calculation_type')->nullable(); $table->decimal('calculation_value', 24, 3)->nullable();
            $table->string('balance_use_policy')->default('insurance_only'); $table->string('status'); $table->string('payment_status');
            $table->string('payment_method')->nullable(); $table->string('payment_reference')->nullable(); $table->uuid('payment_request_id')->nullable();
            $table->unsignedSmallInteger('pending_days')->default(3); $table->unsignedSmallInteger('reuse_after_days')->default(90);
            $table->timestamp('expires_at')->nullable(); $table->timestamp('reusable_at')->nullable(); $table->timestamp('paid_at')->nullable();
            $table->timestamp('reusable_released_at')->nullable(); $table->timestamp('expired_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable(); $table->text('admin_note')->nullable(); $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('seller_order_insurances');
        Schema::dropIfExists('orders');
        parent::tearDown();
    }

    public function test_restricted_contract_uses_opaque_token_and_never_serializes_private_order_data(): void
    {
        [$order, $insurance] = $this->records();
        $payload = app(OrderCommerceContractService::class)->restrictedSellerPayload($order, $insurance);

        $this->assertTrue($payload['details_locked']);
        $this->assertSame('***321', $payload['order_reference']);
        $this->assertTrue(Str::isUuid($payload['restricted_access_token']));
        $this->assertSame(['amount', 'status', 'payment_status', 'expires_at', 'amount_source', 'balance_use_policy'], array_keys($payload['seller_insurance']));
        foreach (['id', 'order_id', 'customer', 'customer_id', 'phone', 'address', 'products', 'order_details', 'shipping_address'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $payload);
        }
    }

    public function test_paid_insurance_unlocks_the_order_flow_immediately(): void
    {
        [$order, $insurance] = $this->records();
        $paid = app(SellerOrderInsuranceService::class)->markPaid($insurance, 'digital_gateway', 'pay-1');

        $this->assertSame(SellerOrderInsurance::STATUS_PAID, $paid->status);
        $this->assertSame(OrderCommerceState::RELEASED_TO_SELLER, $order->fresh()->commerce_flow_status);
        $this->assertNull($order->fresh()->operational_blocked_by);
    }

    public function test_offline_proof_keeps_details_locked_until_admin_accepts_it(): void
    {
        [$order, $insurance] = $this->records();
        $service = app(SellerOrderInsuranceService::class);
        $review = $service->submitOfflinePayment($insurance, ['method_id' => 7, 'payment_proof' => ['image_name' => 'proof.webp']]);

        $this->assertSame(SellerOrderInsurance::STATUS_PENDING_REVIEW, $review->status);
        $this->assertFalse($service->canViewDetails($review));
        $this->assertSame(OrderCommerceState::SELLER_INSURANCE_UNDER_REVIEW, $order->fresh()->commerce_flow_status);

        $rejected = $service->rejectOfflinePayment($review, 10, 'Unreadable proof');
        $this->assertSame(SellerOrderInsurance::STATUS_PENDING_PAYMENT, $rejected->status);
        $this->assertSame(OrderCommerceState::SELLER_INSURANCE_PENDING, $order->fresh()->commerce_flow_status);
    }

    public function test_routes_and_application_have_a_dedicated_pending_insurance_inbox(): void
    {
        $apiRoutes = file_get_contents(base_path('routes/rest_api/v3/seller.php'));
        $vendorRoutes = file_get_contents(base_path('routes/vendor/routes.php'));
        $apiController = file_get_contents(app_path('Http/Controllers/RestAPI/v3/seller/OrderController.php'));
        $appRoot = 'E:/project/Vendor app/lib';

        $this->assertStringContainsString("Route::get('insurance-pending', 'insurancePending')", $apiRoutes);
        $this->assertStringContainsString("Route::get('insurance-pending', [SellerOrderInsuranceWebController::class, 'pending'])->name('insurance-pending')", $vendorRoutes);
        $this->assertStringContainsString('never selects or serializes customer, address or product relations', $apiController);
        $this->assertStringContainsString('insurancePendingOrdersUri', file_get_contents($appRoot . '/utill/app_constants.dart'));
        $this->assertStringContainsString("_orderType = 'insurance_pending'", file_get_contents($appRoot . '/features/order/controllers/order_controller.dart'));
        $this->assertStringContainsString('restrictedAccessToken', file_get_contents($appRoot . '/features/order/domain/models/order_model.dart'));
    }

    public function test_company_shipping_statuses_are_protected_in_web_api_and_application(): void
    {
        $api = file_get_contents(app_path('Http/Controllers/RestAPI/v3/seller/OrderController.php'));
        $web = file_get_contents(app_path('Http/Controllers/Vendor/Order/OrderController.php'));
        $app = file_get_contents('E:/project/Vendor app/lib/features/order_details/widgets/seller_shipping_assignment_widget.dart');

        foreach ([$api, $web] as $source) {
            $this->assertStringContainsString("shipping_fulfillment_mode === 'admin_shipping'", $source);
            $this->assertStringContainsString("['out_for_delivery', 'delivered', 'returned', 'failed']", $source);
        }
        $this->assertStringContainsString("data['responsible_party'] == 'company'", $app);
    }

    private function records(): array
    {
        Order::unguard();
        $order = Order::query()->create([
            'id' => 321, 'seller_id' => 8, 'seller_is' => 'seller', 'order_amount' => 2500,
            'commerce_flow_version' => config('order_commerce.new_flow_version'),
            'commerce_flow_status' => OrderCommerceState::SELLER_INSURANCE_PENDING,
            'operational_blocked_by' => 'seller_insurance', 'operational_block_reason' => 'payment_required',
        ]);
        Order::reguard();
        $insurance = SellerOrderInsurance::query()->create([
            'order_id' => $order->id, 'seller_id' => 8, 'amount' => 125, 'order_amount' => 2500,
            'percentage' => 5, 'calculation_type' => 'percentage', 'calculation_value' => 5,
            'amount_source' => 'admin_default_rule', 'status' => SellerOrderInsurance::STATUS_PENDING_PAYMENT,
            'payment_status' => 'unpaid', 'expires_at' => now()->addDays(3),
        ]);
        return [$order, $insurance];
    }
}
