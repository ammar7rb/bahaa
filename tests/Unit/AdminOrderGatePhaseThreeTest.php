<?php

namespace Tests\Unit;

use App\Events\OrderStatusEvent;
use App\Models\Order;
use App\Models\OrderInsurance;
use App\Models\OrderRiskInvestigation;
use App\Models\OrderShippingDecision;
use App\Models\Seller;
use App\Models\SellerOrderInsurance;
use App\Services\AdminOrderGateService;
use App\Services\CustomerPostPurchaseInsuranceService;
use App\Services\CustomerPurchaseWalletPolicyService;
use App\Services\InsuranceBalanceGovernanceService;
use App\Services\OrderShippingDecisionService;
use App\Services\SellerOrderInsuranceService;
use App\Support\Commerce\OrderCommerceState;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AdminOrderGatePhaseThreeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([OrderStatusEvent::class]);
        Schema::create('business_settings', fn (Blueprint $t) => [$t->id(), $t->string('type')->unique(), $t->text('value')->nullable(), $t->timestamps()]);
        \DB::table('business_settings')->insert(['type'=>'language','value'=>json_encode([['code'=>'en','name'=>'English','default'=>true,'direction'=>'ltr']]),'created_at'=>now(),'updated_at'=>now()]);
        Schema::create('post_purchase_invoices', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('order_id'), $t->string('status')->nullable()]);
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('f_name')->nullable(), $t->string('l_name')->nullable(), $t->decimal('wallet_balance', 24, 3)->default(0), $t->timestamps()]);
        Schema::create('sellers', fn (Blueprint $t) => [$t->id(), $t->string('status')->default('approved'), $t->timestamps()]);
        Schema::create('orders', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('customer_id')->nullable(); $t->unsignedBigInteger('seller_id'); $t->string('seller_is')->default('seller');
            $t->string('payment_status')->default('paid'); $t->decimal('order_amount', 24, 3); $t->string('order_status')->default('pending'); $t->boolean('checked')->default(false);
            $t->string('commerce_flow_version')->default('admin-gated-v2'); $t->string('commerce_flow_status')->nullable(); $t->timestamp('commerce_flow_status_updated_at')->nullable();
            $t->string('admin_order_review_status')->default('pending'); $t->unsignedBigInteger('admin_order_reviewed_by')->nullable(); $t->timestamp('admin_order_reviewed_at')->nullable(); $t->text('admin_order_review_note')->nullable();
            $t->string('operational_blocked_by')->nullable(); $t->text('operational_block_reason')->nullable();
            $t->string('shipping_fulfillment_mode')->nullable(); $t->string('shipping_assignment_status')->default('pending'); $t->string('shipping_operational_status')->nullable(); $t->string('shipping_responsible_party')->nullable();
            $t->decimal('shipping_customer_cost', 24, 3)->nullable(); $t->decimal('shipping_seller_cost', 24, 3)->nullable(); $t->decimal('shipping_seller_entitlement', 24, 3)->nullable();
            $t->timestamps();
        });
        Schema::create('order_insurances', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('order_id')->unique(); $t->unsignedBigInteger('customer_id'); $t->decimal('amount',24,3); $t->decimal('order_amount',24,3); $t->string('payment_status');
            $t->string('status'); $t->decimal('confiscated_amount',24,3)->default(0); $t->string('purchase_refund_status')->default('not_applicable'); $t->timestamp('purchase_refund_due_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
        });
        Schema::create('seller_order_insurances', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('order_id')->unique(); $t->unsignedBigInteger('seller_id'); $t->decimal('amount',24,3); $t->decimal('order_amount',24,3); $t->decimal('percentage',8,3)->default(0);
            $t->string('amount_source')->default('default_rule'); $t->string('calculation_type')->nullable(); $t->decimal('calculation_value',24,3)->nullable(); $t->string('status'); $t->string('payment_status');
            $t->text('admin_decision_reason')->nullable(); $t->decimal('confiscated_amount',24,3)->default(0); $t->timestamps();
        });
        Schema::create('order_shipping_decisions', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('admin_id')->nullable(); $t->string('previous_mode')->nullable(); $t->string('mode'); $t->string('assignment_status');
            $t->decimal('customer_cost',24,3)->nullable(); $t->decimal('seller_cost',24,3)->nullable(); $t->decimal('seller_entitlement',24,3)->nullable(); $t->string('service_name')->nullable(); $t->string('tracking_number')->nullable(); $t->date('expected_delivery_date')->nullable(); $t->text('instructions')->nullable(); $t->text('note')->nullable(); $t->timestamps();
        });
        Schema::create('order_admin_gate_decisions', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('admin_id')->nullable(); $t->string('action'); $t->string('status_before')->nullable(); $t->string('status_after')->nullable();
            $t->unsignedBigInteger('seller_order_insurance_id')->nullable(); $t->unsignedBigInteger('shipping_decision_id')->nullable(); $t->string('seller_insurance_source')->nullable(); $t->string('seller_insurance_calculation_type')->nullable();
            $t->decimal('seller_insurance_calculation_value',24,3)->nullable(); $t->decimal('seller_insurance_amount',24,3)->nullable(); $t->text('override_reason')->nullable(); $t->text('note')->nullable(); $t->json('snapshot')->nullable(); $t->timestamps();
        });
        Schema::create('order_risk_investigations', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('opened_by_admin_id')->nullable(); $t->unsignedBigInteger('resolved_by_admin_id')->nullable(); $t->string('status'); $t->string('risk_level'); $t->text('reason'); $t->json('evidence')->nullable();
            $t->boolean('customer_insurance_frozen')->default(false); $t->boolean('seller_insurance_frozen')->default(false); $t->string('previous_flow_status')->nullable(); $t->string('resolution_action')->nullable(); $t->text('resolution_note')->nullable(); $t->timestamp('opened_at'); $t->timestamp('resolved_at')->nullable(); $t->timestamps();
        });
        Schema::create('order_risk_investigation_messages', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('investigation_id'), $t->unsignedBigInteger('admin_id')->nullable(), $t->string('recipient_type'), $t->unsignedBigInteger('recipient_id')->nullable(), $t->text('message'), $t->unsignedBigInteger('support_ticket_id')->nullable(), $t->timestamps()]);
        Schema::create('support_tickets', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('customer_id')->nullable(); $t->string('subject')->nullable(); $t->string('type')->nullable(); $t->string('purpose')->nullable(); $t->string('priority')->nullable(); $t->text('description')->nullable(); $t->text('reply')->nullable(); $t->string('status')->nullable(); $t->string('review_status')->nullable(); $t->json('attachment')->nullable(); $t->timestamps(); });
        Schema::create('notifications', fn (Blueprint $t) => [$t->id(), $t->string('sent_by'), $t->string('sent_to'), $t->unsignedBigInteger('seller_id')->nullable(), $t->string('title')->nullable(), $t->text('description')->nullable(), $t->unsignedInteger('notification_count')->default(1), $t->string('image')->nullable(), $t->boolean('status')->default(true), $t->timestamps()]);
        Schema::create('order_admin_purchase_refunds', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('order_id')->unique(), $t->unsignedBigInteger('customer_id'), $t->decimal('amount',24,3), $t->string('status'), $t->timestamp('due_at'), $t->timestamp('completed_at')->nullable(), $t->string('reference')->nullable(), $t->text('reason'), $t->unsignedBigInteger('admin_id')->nullable(), $t->timestamps()]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('post_purchase_invoices');
        foreach (['order_admin_purchase_refunds','notifications','support_tickets','order_risk_investigation_messages','order_risk_investigations','order_admin_gate_decisions','order_shipping_decisions','seller_order_insurances','order_insurances','orders','sellers','users','business_settings'] as $table) Schema::dropIfExists($table);
        parent::tearDown();
    }

    public function test_admin_assignment_saves_seller_insurance_shipping_and_audited_snapshot(): void
    {
        [$service, $sellerInsurance, $shipping] = $this->service();
        $order = $this->order();
        $sellerInsurance->shouldReceive('createFromAdminDecision')->once()->andReturnUsing(function (Order $order): SellerOrderInsurance {
            return SellerOrderInsurance::query()->create(['order_id'=>$order->id,'seller_id'=>$order->seller_id,'amount'=>125,'order_amount'=>2500,'percentage'=>5,'amount_source'=>'admin_override','calculation_type'=>'percentage','calculation_value'=>5,'status'=>'pending_payment','payment_status'=>'unpaid','admin_decision_reason'=>'Risk adjustment']);
        });
        $shipping->shouldReceive('assign')->once()->andReturnUsing(function (Order $order): OrderShippingDecision {
            $order->update(['shipping_assignment_status'=>'assigned','shipping_fulfillment_mode'=>'admin_shipping']);
            return OrderShippingDecision::query()->create(['order_id'=>$order->id,'mode'=>'admin_shipping','assignment_status'=>'assigned','customer_cost'=>50,'seller_cost'=>20,'tracking_number'=>'TRK-1']);
        });

        $decision = $service->saveAssignment($order, $this->assignment(), 7);

        $this->assertSame('assignment_saved', $decision->action);
        $this->assertSame(125.0, $decision->seller_insurance_amount);
        $this->assertSame(OrderCommerceState::ADMIN_ASSIGNMENT_IN_PROGRESS, $order->fresh()->commerce_flow_status);
        $this->assertSame('TRK-1', data_get($decision->snapshot, 'shipping.tracking_number'));
    }

    public function test_pending_review_is_hidden_and_unpaid_order_cannot_be_released(): void
    {
        $order = $this->order();
        $order->update(['payment_status'=>'unpaid','admin_order_review_status'=>'pending_admin_review']);
        $this->assertFalse(app(\App\Services\SellerOrderVisibilityService::class)->canSellerView($order->fresh()));
        [$service] = $this->service();
        $this->expectException(\DomainException::class);
        $service->releaseToSeller($order, 'Approve', 7);
    }

    public function test_release_requires_both_decisions_and_then_sends_only_after_admin_audit(): void
    {
        [$service] = $this->service();
        $order = $this->order();
        SellerOrderInsurance::query()->create(['order_id'=>$order->id,'seller_id'=>$order->seller_id,'amount'=>100,'order_amount'=>2500,'status'=>'pending_payment','payment_status'=>'unpaid']);
        OrderShippingDecision::query()->create(['order_id'=>$order->id,'mode'=>'seller_shipping','assignment_status'=>'assigned']);
        $order->update(['commerce_flow_status'=>OrderCommerceState::ADMIN_ASSIGNMENT_IN_PROGRESS,'shipping_assignment_status'=>'assigned','checked'=>1]);

        $decision = $service->releaseToSeller($order->fresh(), 'Verified and assigned', 9);

        $this->assertSame(OrderCommerceState::SELLER_INSURANCE_PENDING, $order->fresh()->commerce_flow_status);
        $this->assertSame('approved', $order->fresh()->admin_order_review_status);
        $this->assertSame(0, (int) $order->fresh()->checked);
        $this->assertSame('released_to_seller', $decision->action);
        Event::assertDispatched(OrderStatusEvent::class);
    }

    public function test_open_investigation_freezes_flow_and_is_idempotent(): void
    {
        [$service] = $this->service();
        $order = $this->order();
        $first = $service->openInvestigation($order, ['risk_level'=>'high','reason'=>'Repeated suspicious attempts'], 2);
        $second = $service->openInvestigation($order->fresh(), ['risk_level'=>'critical','reason'=>'Duplicate'], 3);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(OrderCommerceState::UNDER_INVESTIGATION, $order->fresh()->commerce_flow_status);
        $this->assertTrue($first->customer_insurance_frozen);
        $this->assertDatabaseCount('order_risk_investigations', 1);
    }

    public function test_investigation_messages_go_only_through_admin_support_channels(): void
    {
        [$service] = $this->service();
        $investigation = $service->openInvestigation($this->order(), ['risk_level'=>'medium','reason'=>'Manual verification'], 2);
        $customerMessage = $service->sendInvestigationMessage($investigation, 'customer', 'Please contact administration.', 2);
        $sellerMessage = $service->sendInvestigationMessage($investigation, 'seller', 'The order is being reviewed.', 2);

        $this->assertNotNull($customerMessage->support_ticket_id);
        $this->assertNull($sellerMessage->support_ticket_id);
        $this->assertDatabaseHas('support_tickets', ['purpose'=>'order_investigation','customer_id'=>1]);
        $this->assertDatabaseHas('notifications', ['sent_to'=>'seller','seller_id'=>1]);
    }

    public function test_final_fake_order_decision_schedules_purchase_refund_and_closes_investigation(): void
    {
        [$service, , , , $customerFlow] = $this->service();
        $order = $this->order();
        $investigation = $service->openInvestigation($order, ['risk_level'=>'critical','reason'=>'Fake order'], 2);
        $customerFlow->shouldReceive('schedulePurchaseRefundForInvestigation')->once()->with(Mockery::type(OrderInsurance::class), 'Confirmed fake order');

        $resolved = $service->resolveInvestigation($investigation, 'cancel_and_refund', 'Confirmed fake order', 4);

        $this->assertSame(OrderRiskInvestigation::STATUS_RESOLVED, $resolved->status);
        $this->assertSame('cancel_and_refund', $resolved->resolution_action);
        $this->assertDatabaseHas('order_admin_gate_decisions', ['order_id'=>$order->id,'action'=>'investigation_resolved']);
    }

    public function test_phase_three_routes_migration_dashboard_and_feature_are_present(): void
    {
        $routes = file_get_contents(base_path('routes/admin/routes.php'));
        $migration = file_get_contents(database_path('migrations/2026_08_14_000003_create_admin_order_gate_and_investigations.php'));
        $shortcut = file_get_contents(resource_path('views/admin-views/system/partials/operational-shortcuts.blade.php'));
        $sellerApi = file_get_contents(app_path('Http/Controllers/RestAPI/v3/seller/OrderController.php'));
        $sellerList = file_get_contents(resource_path('views/vendor-views/order/list.blade.php'));
        $this->assertStringContainsString("prefix('admin-gate')", $routes);
        $this->assertStringContainsString("order_admin_gate_decisions", $migration);
        $this->assertStringContainsString("order_risk_investigations", $migration);
        $this->assertStringContainsString("admin.orders.admin-gate.index", $shortcut);
        $this->assertStringContainsString('restrictedSellerPayload', $sellerApi);
        $this->assertStringContainsString('details_locked_until_seller_insurance_payment', $sellerList);
        $this->assertTrue(config('order_commerce.features.admin_review_before_seller'));
    }

    public function test_invoice_footer_does_not_expose_contact_details(): void
    {
        $generator = new class { use \App\Traits\PdfGenerator; };
        $footer = $generator::footerHtml('admin');

        $this->assertStringNotContainsString('@', $footer);
        $this->assertStringContainsString(url('/'), $footer);
    }

    private function order(): Order
    {
        \DB::table('users')->insert(['id'=>1,'f_name'=>'Customer','wallet_balance'=>0,'created_at'=>now(),'updated_at'=>now()]);
        Seller::query()->create(['id'=>1,'status'=>'approved']);
        $order = Order::query()->create(['customer_id'=>1,'seller_id'=>1,'seller_is'=>'seller','payment_status'=>'paid','order_amount'=>2500,'commerce_flow_status'=>OrderCommerceState::PENDING_ADMIN_REVIEW]);
        OrderInsurance::query()->create(['order_id'=>$order->id,'customer_id'=>1,'amount'=>250,'order_amount'=>2500,'payment_status'=>'paid','status'=>'held']);
        return $order->fresh(['seller','insurance']);
    }

    private function assignment(): array
    {
        return ['seller_insurance_mode'=>'manual','seller_insurance_calculation_type'=>'percentage','seller_insurance_calculation_value'=>5,'shipping_mode'=>'admin_shipping','shipping_customer_cost'=>50,'shipping_seller_cost'=>20,'shipping_tracking_number'=>'TRK-1'];
    }

    private function service(): array
    {
        $seller = Mockery::mock(SellerOrderInsuranceService::class);
        $seller->shouldReceive('preview')->byDefault()->andReturn(['applicable'=>true,'amount'=>125,'calculation_type'=>'percentage','calculation_value'=>5]);
        $shipping = Mockery::mock(OrderShippingDecisionService::class);
        $governance = Mockery::mock(InsuranceBalanceGovernanceService::class);
        $customerFlow = Mockery::mock(CustomerPostPurchaseInsuranceService::class);
        $purchaseWallet = Mockery::mock(CustomerPurchaseWalletPolicyService::class);
        return [new AdminOrderGateService($seller,$shipping,$governance,$customerFlow,$purchaseWallet),$seller,$shipping,$governance,$customerFlow,$purchaseWallet];
    }
}
