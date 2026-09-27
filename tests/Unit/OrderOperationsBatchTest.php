<?php

namespace Tests\Unit;

use App\Models\EgyptShippingZoneRate;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\SellerSettlement;
use App\Services\EgyptShippingRateService;
use App\Services\OrderShippingDecisionService;
use App\Services\OrderWorkflowService;
use App\Services\SellerSettlementService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderOperationsBatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('business_settings', function (Blueprint $t) { $t->id(); $t->string('type')->unique(); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('sellers', function (Blueprint $t) { $t->id(); $t->string('app_language')->nullable(); $t->timestamps(); });
        Schema::create('shops', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->string('address')->nullable(); $t->string('contact')->nullable(); $t->timestamps(); });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id')->nullable(); $t->boolean('is_guest')->default(false);
            $t->unsignedBigInteger('seller_id')->nullable(); $t->string('seller_is')->nullable(); $t->string('order_status')->default('pending');
            $t->string('operational_blocked_by')->nullable(); $t->text('operational_block_reason')->nullable(); $t->dateTime('operational_status_updated_at')->nullable();
            $t->unsignedInteger('seller_queue_priority')->default(1000); $t->unsignedInteger('seller_queue_position')->nullable();
            $t->dateTime('seller_queue_override_at')->nullable(); $t->unsignedBigInteger('seller_queue_override_by')->nullable(); $t->text('seller_queue_override_reason')->nullable();
            $t->string('shipping_fulfillment_mode')->default('pending'); $t->string('shipping_assignment_status')->default('pending');
            $t->string('shipping_operational_status')->default('pending_assignment'); $t->string('shipping_responsible_party')->nullable();
            $t->decimal('shipping_cost', 24, 3)->nullable(); $t->decimal('shipping_customer_cost', 24, 3)->nullable(); $t->decimal('shipping_seller_cost', 24, 3)->nullable();
            $t->string('delivery_service_name')->nullable(); $t->string('third_party_delivery_tracking_id')->nullable(); $t->string('shipment_reference')->nullable();
            $t->json('shipping_address_data')->nullable(); $t->json('pickup_address_snapshot')->nullable(); $t->json('customer_address_snapshot')->nullable(); $t->json('shipping_price_snapshot')->nullable();
            $t->string('return_responsible_party')->nullable(); $t->decimal('return_shipping_cost', 24, 3)->nullable(); $t->string('return_tracking_number')->nullable();
            $t->string('customer_delivery_confirmation_status')->default('not_applicable'); $t->dateTime('customer_delivery_confirmation_due_at')->nullable();
            $t->dateTime('customer_delivery_confirmed_at')->nullable(); $t->dateTime('customer_delivery_confirmation_expired_at')->nullable(); $t->timestamps();
        });
        Schema::create('order_logistics_events', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('order_id'); $t->string('event_type'); $t->string('previous_order_status')->nullable(); $t->string('order_status')->nullable();
            $t->string('blocked_by')->nullable(); $t->string('reason_code')->nullable(); $t->string('event_key')->nullable()->unique(); $t->string('shipping_status')->nullable();
            $t->string('responsible_party')->nullable(); $t->string('actor_type')->nullable(); $t->unsignedBigInteger('actor_id')->nullable();
            $t->decimal('customer_shipping_cost', 24, 3)->nullable(); $t->decimal('seller_shipping_cost', 24, 3)->nullable(); $t->decimal('return_shipping_cost', 24, 3)->nullable();
            $t->string('tracking_number')->nullable(); $t->text('note')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
        });
        Schema::create('order_operational_alerts', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('seller_id')->nullable(); $t->string('alert_type'); $t->string('level');
            $t->string('order_status')->nullable(); $t->dateTime('due_at')->nullable(); $t->dateTime('notified_at')->nullable(); $t->dateTime('resolved_at')->nullable();
            $t->string('idempotency_key')->unique(); $t->json('metadata')->nullable(); $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) { $t->id(); $t->string('sent_by')->nullable(); $t->string('sent_to')->nullable(); $t->unsignedBigInteger('seller_id')->nullable(); $t->string('title')->nullable(); $t->text('description')->nullable(); $t->integer('notification_count')->default(0); $t->string('image')->nullable(); $t->boolean('status')->default(true); $t->timestamps(); });
        Schema::create('egypt_shipping_zone_rates', function (Blueprint $t) {
            $t->id(); $t->string('country_code', 2)->default('EG'); $t->string('governorate')->nullable(); $t->string('district')->nullable(); $t->string('area')->nullable(); $t->string('street')->nullable();
            $t->decimal('normal_cost', 24, 3)->nullable(); $t->decimal('sigma_cost', 24, 3)->nullable(); $t->decimal('included_distance_km', 12, 3)->default(0);
            $t->decimal('price_per_km', 24, 3)->default(0); $t->decimal('peak_multiplier', 8, 3)->default(1); $t->decimal('sigma_surcharge', 24, 3)->default(0);
            $t->boolean('normal_available')->default(true); $t->boolean('sigma_available')->default(true); $t->boolean('status')->default(true); $t->timestamps();
        });
        Schema::create('seller_settlements', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('order_id')->unique(); $t->decimal('amount', 40, 20); $t->string('status'); $t->timestamp('delivered_at'); $t->timestamp('dispute_until')->nullable(); $t->timestamp('due_at')->nullable(); $t->timestamp('released_at')->nullable(); $t->timestamp('reversed_at')->nullable(); $t->unsignedBigInteger('reviewed_by_admin_id')->nullable(); $t->text('review_note')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); });
        Schema::create('refund_requests', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('order_details_id')->nullable(); $t->unsignedBigInteger('customer_id')->nullable(); $t->string('status'); $t->decimal('amount', 24, 3); $t->string('change_by')->nullable(); $t->timestamps(); });
        Schema::create('seller_settlement_disputes', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_settlement_id'); $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('seller_id'); $t->string('reference_type'); $t->unsignedBigInteger('reference_id'); $t->decimal('amount', 40, 20); $t->string('status'); $t->text('reason'); $t->unsignedBigInteger('resolved_by_admin_id')->nullable(); $t->dateTime('resolved_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); $t->unique(['reference_type', 'reference_id']); });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        foreach (['seller_settlement_disputes','refund_requests','seller_settlements','egypt_shipping_zone_rates','notifications','order_operational_alerts','order_logistics_events','orders','shops','sellers','business_settings'] as $table) Schema::dropIfExists($table);
        parent::tearDown();
    }

    public function test_state_matrix_queue_override_and_delay_alerts_are_idempotent(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');
        DB::table('sellers')->insert(['id' => 5, 'created_at' => now(), 'updated_at' => now()]);
        $first = Order::create(['seller_id' => 5, 'seller_is' => 'seller', 'order_status' => 'pending', 'operational_status_updated_at' => now()->subHours(2)]);
        $second = Order::create(['seller_id' => 5, 'seller_is' => 'seller', 'order_status' => 'confirmed', 'operational_status_updated_at' => now()->subHours(2)]);
        $service = app(OrderWorkflowService::class);
        $service->refreshSellerQueue(5);
        $this->assertSame(1, $first->fresh()->seller_queue_position);
        $service->overrideQueue($second, 1, 9, 'Urgent medical shipment');
        $this->assertSame(1, $second->fresh()->seller_queue_position);
        $this->expectException(DomainException::class);
        $service->assertTransition('pending', 'delivered', 'seller');
    }

    public function test_delay_and_confirmation_jobs_are_retry_safe(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');
        DB::table('sellers')->insert(['id' => 5, 'created_at' => now(), 'updated_at' => now()]);
        Order::create(['seller_id' => 5, 'seller_is' => 'seller', 'order_status' => 'pending', 'operational_status_updated_at' => now()->subHours(3)]);
        $delivery = Order::create(['customer_id' => 8, 'order_status' => 'delivered', 'customer_delivery_confirmation_status' => 'pending', 'customer_delivery_confirmation_due_at' => now()->subMinute()]);
        $service = app(OrderWorkflowService::class);
        $this->assertSame(['warning' => 0, 'escalation' => 1], $service->monitorSellerDelays());
        $this->assertSame(['warning' => 0, 'escalation' => 0], $service->monitorSellerDelays());
        $this->assertSame(1, $service->expireCustomerConfirmations());
        $this->assertSame(0, $service->expireCustomerConfirmations());
        $this->assertSame('expired', $delivery->fresh()->customer_delivery_confirmation_status);
    }

    public function test_shipping_quote_combines_street_distance_peak_and_sigma_once(): void
    {
        EgyptShippingZoneRate::create(['governorate' => 'Cairo', 'district' => 'Nasr City', 'street' => 'Tayaran', 'normal_cost' => 50, 'sigma_cost' => 70, 'included_distance_km' => 5, 'price_per_km' => 4, 'peak_multiplier' => 1.5, 'sigma_surcharge' => 10]);
        $quote = app(EgyptShippingRateService::class)->quote(['country' => 'Egypt', 'state' => 'Cairo', 'city' => 'Nasr City', 'street' => 'Tayaran'], 'sigma', 8, true);
        $this->assertSame(279.0, $quote['total']); // Sigma is always 3 × the normal quote: ((50 + 3*4) * 1.5) * 3
        $this->assertSame(12.0, $quote['distance_cost']);
    }

    public function test_shipment_identity_and_return_dispute_are_unique(): void
    {
        DB::table('sellers')->insert(['id' => 5, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shops')->insert(['seller_id' => 5, 'address' => 'Cairo warehouse', 'contact' => '+201000000000', 'created_at' => now(), 'updated_at' => now()]);
        $order = Order::create(['seller_id' => 5, 'seller_is' => 'seller', 'order_status' => 'delivered', 'shipping_address_data' => ['address' => 'Nasr City', 'country' => 'Egypt']]);
        $shipment = app(OrderShippingDecisionService::class)->ensureShipmentIdentity($order);
        $this->assertStringStartsWith('SHP-', $shipment->shipment_reference);
        $this->assertSame('Cairo warehouse', $shipment->pickup_address_snapshot['address']);

        $settlement = SellerSettlement::create(['seller_id' => 5, 'order_id' => $order->id, 'amount' => 200, 'status' => 'held', 'delivered_at' => now(), 'due_at' => now()->addDays(45)]);
        $refund = RefundRequest::create(['order_id' => $order->id, 'customer_id' => 8, 'status' => 'approved', 'amount' => 75, 'change_by' => 'admin']);
        $service = app(SellerSettlementService::class);
        $first = $service->disputeForReturnRefusal($refund->load('order'), 'Seller refused pickup');
        $second = $service->disputeForReturnRefusal($refund->fresh()->load('order'), 'Retry');
        $this->assertSame($first->id, $second->id);
        $this->assertSame('disputed', $settlement->fresh()->status);
        $this->assertDatabaseCount('seller_settlement_disputes', 1);
    }
}
