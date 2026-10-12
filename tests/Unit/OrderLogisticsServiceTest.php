<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderLogisticsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderLogisticsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->boolean('is_guest')->default(false);
            $table->string('order_status')->default('pending');
            $table->string('shipping_fulfillment_mode')->default('pending');
            $table->string('shipping_operational_status')->default('pending_assignment');
            $table->string('shipping_responsible_party')->nullable();
            $table->decimal('shipping_cost', 24, 3)->nullable();
            $table->decimal('shipping_customer_cost', 24, 3)->nullable();
            $table->decimal('shipping_seller_cost', 24, 3)->nullable();
            $table->string('delivery_service_name')->nullable();
            $table->string('third_party_delivery_tracking_id')->nullable();
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

        DB::table('business_settings')->insert([
            'type' => 'customer_delivery_confirmation_days',
            'value' => '5',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('order_logistics_events');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('business_settings');
        parent::tearDown();
    }

    public function test_delivery_opens_customer_confirmation_for_the_admin_configured_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00'));
        $order = $this->makeOrder();

        app(OrderLogisticsService::class)->recordOrderStatus($order, 'delivered', 'admin', 12);

        $fresh = $order->fresh();
        $this->assertSame('delivered', $fresh->shipping_operational_status);
        $this->assertSame('pending', $fresh->customer_delivery_confirmation_status);
        $this->assertTrue($fresh->customer_delivery_confirmation_due_at->equalTo(now()->addDays(5)));
        $this->assertDatabaseHas('order_logistics_events', [
            'order_id' => $order->id,
            'event_type' => 'shipping_status_updated',
            'actor_type' => 'admin',
            'actor_id' => 12,
        ]);
    }

    public function test_customer_can_confirm_only_their_delivered_order(): void
    {
        $order = $this->makeOrder(['order_status' => 'delivered', 'customer_delivery_confirmation_status' => 'pending']);

        app(OrderLogisticsService::class)->confirmCustomerReceipt($order, 77);

        $fresh = $order->fresh();
        $this->assertSame('confirmed', $fresh->customer_delivery_confirmation_status);
        $this->assertNotNull($fresh->customer_delivery_confirmed_at);
        $this->assertDatabaseHas('order_logistics_events', [
            'order_id' => $order->id,
            'event_type' => 'customer_receipt_confirmed',
            'actor_type' => 'customer',
            'actor_id' => 77,
        ]);
    }

    public function test_admin_return_records_cost_responsibility_and_audit_event(): void
    {
        $order = $this->makeOrder();

        app(OrderLogisticsService::class)->registerReturn($order, [
            'return_responsible_party' => 'seller',
            'return_shipping_cost' => 35.5,
            'return_tracking_number' => 'RET-100',
            'return_reason' => 'Customer returned the order.',
        ], 12);

        $fresh = $order->fresh();
        $this->assertSame('return_in_transit', $fresh->shipping_operational_status);
        $this->assertSame('seller', $fresh->return_responsible_party);
        $this->assertSame(35.5, $fresh->return_shipping_cost);
        $this->assertSame('RET-100', $fresh->return_tracking_number);
        $this->assertDatabaseHas('order_logistics_events', [
            'order_id' => $order->id,
            'event_type' => 'return_registered',
            'responsible_party' => 'seller',
            'actor_id' => 12,
        ]);
    }

    private function makeOrder(array $attributes = []): Order
    {
        return Order::query()->create(array_merge([
            'customer_id' => 77,
            'is_guest' => false,
            'order_status' => 'processing',
            'shipping_fulfillment_mode' => 'admin_shipping',
            'shipping_operational_status' => 'assigned',
            'shipping_responsible_party' => 'company',
            'shipping_customer_cost' => 30,
            'third_party_delivery_tracking_id' => 'BA-100',
        ], $attributes));
    }
}
