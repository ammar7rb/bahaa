<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\ShippingMethod;
use App\Services\OrderShippingDecisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderShippingDecisionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('shipping_responsibility')->nullable();
            $table->string('shipping_fulfillment_mode')->default('pending');
            $table->string('shipping_assignment_status')->default('pending');
            $table->unsignedBigInteger('shipping_method_id')->nullable();
            $table->decimal('shipping_cost', 24, 3)->nullable();
            $table->decimal('shipping_customer_cost', 24, 3)->nullable();
            $table->decimal('shipping_seller_cost', 24, 3)->nullable();
            $table->decimal('shipping_seller_entitlement', 24, 3)->nullable();
            $table->dateTime('shipping_decided_at')->nullable();
            $table->unsignedBigInteger('shipping_decided_by')->nullable();
            $table->text('shipping_decision_note')->nullable();
            $table->string('delivery_service_name')->nullable();
            $table->string('third_party_delivery_tracking_id')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->string('shipping_operational_status')->default('pending_assignment');
            $table->string('shipping_responsible_party')->nullable();
            $table->string('return_responsible_party')->nullable();
            $table->decimal('return_shipping_cost', 24, 3)->nullable();
            $table->string('return_tracking_number')->nullable();
            $table->text('return_reason')->nullable();
            $table->dateTime('return_started_at')->nullable();
            $table->dateTime('return_completed_at')->nullable();
            $table->string('customer_delivery_confirmation_status')->default('not_applicable');
            $table->dateTime('customer_delivery_confirmation_due_at')->nullable();
            $table->dateTime('customer_delivery_confirmed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('order_logistics_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('event_type');
            $table->string('shipping_status')->nullable();
            $table->string('responsible_party')->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->decimal('customer_shipping_cost', 24, 3)->nullable();
            $table->decimal('seller_shipping_cost', 24, 3)->nullable();
            $table->decimal('return_shipping_cost', 24, 3)->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('creator_type')->default('admin');
            $table->string('title')->nullable();
            $table->decimal('cost', 24, 3)->default(0);
            $table->string('duration')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('order_shipping_decisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('previous_mode')->nullable();
            $table->string('mode');
            $table->string('assignment_status')->default('assigned');
            $table->decimal('customer_cost', 24, 3)->nullable();
            $table->decimal('seller_cost', 24, 3)->nullable();
            $table->decimal('seller_entitlement', 24, 3)->nullable();
            $table->string('service_name')->nullable();
            $table->string('tracking_number')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->text('instructions')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('order_logistics_events');
        Schema::dropIfExists('order_shipping_decisions');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('orders');
        parent::tearDown();
    }

    public function test_admin_shipping_decision_is_saved_with_audit_snapshot(): void
    {
        $order = Order::create(['shipping_cost' => 20]);
        $decision = app(OrderShippingDecisionService::class)->assign($order, [
            'mode' => 'admin_shipping',
            'customer_cost' => 35,
            'seller_cost' => 10,
            'seller_entitlement' => 0,
            'service_name' => 'Company Fleet',
            'tracking_number' => 'BA-100',
            'expected_delivery_date' => '2026-08-12',
            'instructions' => 'Company handles delivery.',
            'note' => 'Approved by operations.',
        ], 7);

        $fresh = $order->fresh();
        $this->assertSame('admin_shipping', $fresh->shipping_fulfillment_mode);
        $this->assertSame('assigned', $fresh->shipping_assignment_status);
        $this->assertSame(35.0, $fresh->shipping_customer_cost);
        $this->assertSame(35.0, $fresh->shipping_cost);
        $this->assertSame('BA-100', $fresh->third_party_delivery_tracking_id);
        $this->assertSame('admin_shipping', $decision->mode);
        $this->assertSame('pending', $decision->previous_mode);
    }

    public function test_a_second_decision_keeps_the_previous_mode_in_history(): void
    {
        $order = Order::create();
        $service = app(OrderShippingDecisionService::class);
        $service->assign($order, ['mode' => 'seller_shipping', 'customer_cost' => 15, 'note' => 'Seller selected.']);
        $second = $service->assign($order->fresh(), ['mode' => 'admin_shipping', 'customer_cost' => 25, 'service_name' => 'Admin fleet', 'note' => 'Changed by admin.']);

        $this->assertSame('seller_shipping', $second->previous_mode);
        $this->assertSame(2, $order->shippingDecisionHistory()->count());
    }

    public function test_active_admin_shipping_method_sets_service_and_default_customer_cost(): void
    {
        $method = ShippingMethod::create([
            'creator_type' => 'admin',
            'title' => 'Egypt Express',
            'cost' => 42,
            'duration' => '2 days',
            'status' => true,
        ]);
        $order = Order::create();

        $decision = app(OrderShippingDecisionService::class)->assign($order, [
            'mode' => 'admin_shipping',
            'shipping_method_id' => $method->id,
            'customer_cost' => '',
            'note' => 'Selected from the active company list.',
        ], 9);

        $fresh = $order->fresh();
        $this->assertSame($method->id, $fresh->shipping_method_id);
        $this->assertSame('Egypt Express', $fresh->delivery_service_name);
        $this->assertSame(42.0, $fresh->shipping_customer_cost);
        $this->assertSame('Egypt Express', $decision->service_name);
    }

    public function test_quick_assignment_supports_seller_or_an_active_admin_method(): void
    {
        $method = ShippingMethod::create([
            'creator_type' => 'admin',
            'title' => 'Quick Egypt Express',
            'cost' => 31,
            'status' => true,
        ]);
        $service = app(OrderShippingDecisionService::class);

        $sellerOrder = Order::create();
        $sellerDecision = $service->quickAssign($sellerOrder, 'seller_shipping', 4, 'Quick seller assignment.');
        $this->assertSame('seller_shipping', $sellerDecision->mode);
        $this->assertSame('seller', $sellerOrder->fresh()->shipping_responsible_party);

        $companyOrder = Order::create();
        $companyDecision = $service->quickAssign($companyOrder, 'admin_method:'.$method->id, 4, 'Quick company assignment.');
        $this->assertSame('admin_shipping', $companyDecision->mode);
        $this->assertSame($method->id, $companyOrder->fresh()->shipping_method_id);
        $this->assertSame(31.0, $companyOrder->fresh()->shipping_customer_cost);
    }
}
