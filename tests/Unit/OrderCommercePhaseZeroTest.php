<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderInsurance;
use App\Models\SellerOrderInsurance;
use App\Services\OrderCommerceContractService;
use App\Services\OrderCommerceFeatureService;
use App\Support\Commerce\OrderCommerceState;
use Carbon\Carbon;
use InvalidArgumentException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderCommercePhaseZeroTest extends TestCase
{
    public function test_every_flow_state_has_an_owner_and_only_known_transitions(): void
    {
        $definitions = OrderCommerceState::definitions();
        $transitions = OrderCommerceState::transitions();
        $actors = ['customer', 'admin', 'seller', 'payment', 'system'];

        $this->assertSame(array_keys($definitions), OrderCommerceState::states());
        $this->assertEqualsCanonicalizing(array_keys($definitions), array_keys($transitions));

        foreach ($definitions as $state => $definition) {
            $this->assertContains($definition['owner'], $actors, "State {$state} has an unknown owner.");
            $this->assertIsBool($definition['terminal']);
            $this->assertNotSame('', $definition['description']);

            foreach ($transitions[$state] as $target => $allowedActors) {
                $this->assertArrayHasKey($target, $definitions, "Transition {$state} -> {$target} targets an unknown state.");
                $this->assertNotEmpty($allowedActors);
                foreach ($allowedActors as $actor) {
                    $this->assertContains($actor, $actors);
                    $this->assertTrue(OrderCommerceState::canTransition($state, $target, $actor));
                }
            }
        }

        $this->assertFalse(OrderCommerceState::canTransition(
            OrderCommerceState::CUSTOMER_INSURANCE_PENDING,
            OrderCommerceState::RELEASED_TO_SELLER,
            OrderCommerceState::ACTOR_CUSTOMER,
        ));
    }

    public function test_completed_commerce_features_are_enabled_and_later_phases_remain_disabled(): void
    {
        $service = app(OrderCommerceFeatureService::class);
        $features = $service->all();

        $this->assertSame('2026-08-v2', $service->contractVersion());
        $this->assertCount(8, $features);
        foreach ($features as $feature => $enabled) {
            $expected = in_array($feature, ['insurance_only_balances', 'customer_insurance_after_purchase', 'admin_review_before_seller'], true);
            $this->assertSame($expected, $enabled, "Only completed commerce features may be enabled by default.");
            $this->assertSame($expected, $service->enabled($feature));
        }

        $this->expectException(InvalidArgumentException::class);
        $service->enabled('unknown_feature');
    }

    public function test_restricted_seller_contract_exposes_only_masked_order_and_insurance_summary(): void
    {
        $order = (new Order())->forceFill([
            'id' => 120045,
            'order_amount' => 2500,
            'commerce_flow_status' => OrderCommerceState::SELLER_INSURANCE_PENDING,
            'shipping_address_data' => ['address' => 'must never be serialized'],
        ]);
        $insurance = (new SellerOrderInsurance())->forceFill([
            'amount' => 250,
            'status' => SellerOrderInsurance::STATUS_PENDING_PAYMENT,
            'payment_status' => 'unpaid',
            'amount_source' => 'admin_override',
            'balance_use_policy' => 'insurance_only',
            'expires_at' => Carbon::parse('2026-08-17 12:00:00'),
        ]);

        $payload = app(OrderCommerceContractService::class)->restrictedSellerPayload($order, $insurance);
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->assertSame('***045', $payload['order_reference']);
        $this->assertSame('045', $payload['order_last_three_digits']);
        $this->assertTrue($payload['details_locked']);
        $this->assertSame(250.0, $payload['seller_insurance']['amount']);
        $this->assertStringNotContainsString('shipping_address', $encoded);
        $this->assertStringNotContainsString('customer', $encoded);
        $this->assertStringNotContainsString('product', $encoded);
        $this->assertStringNotContainsString('120045', $payload['order_reference']);
    }

    public function test_customer_insurance_contract_separates_purchase_and_insurance_values(): void
    {
        $order = (new Order())->forceFill([
            'id' => 77,
            'order_amount' => 1500,
            'commerce_flow_status' => OrderCommerceState::CUSTOMER_INSURANCE_PENDING,
        ]);
        $insurance = (new OrderInsurance())->forceFill([
            'amount' => 150,
            'order_amount' => 1500,
            'threshold_amount' => 1000,
            'calculation_type' => 'percentage',
            'calculation_value' => 10,
            'payment_status' => 'unpaid',
            'status' => 'pending_payment',
            'payment_due_at' => Carbon::parse('2026-08-17 12:00:00'),
            'balance_use_policy' => 'insurance_only',
            'purchase_refund_status' => 'not_applicable',
        ]);

        $payload = app(OrderCommerceContractService::class)->customerInsurancePayload($order, $insurance);

        $this->assertSame(1500.0, $payload['purchase_amount']);
        $this->assertSame(150.0, $payload['insurance']['amount']);
        $this->assertSame(10.0, $payload['insurance']['calculation_value']);
        $this->assertSame('insurance_only', $payload['insurance']['balance_use_policy']);
        $this->assertTrue($payload['order_is_suspended_until_insurance_payment']);
        $this->assertTrue($payload['support_available']);
    }

    public function test_dashboard_contains_fixed_shortcuts_for_the_merged_admin_centers(): void
    {
        $view = file_get_contents(resource_path('views/admin-views/system/partials/operational-shortcuts.blade.php'));

        $this->assertIsString($view);
        foreach ([
            'admin.support-ticket.view',
            'admin.activation-center.index',
            'admin.payment-reviews.index',
            'admin.banner.list',
            'admin.business-settings.priority-setup.index',
        ] as $route) {
            $this->assertStringContainsString($route, $view);
        }
    }

    public function test_phase_zero_migration_is_additive_and_marks_existing_records_as_legacy(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_status')->default('pending');
            $table->timestamps();
        });
        Schema::create('order_insurances', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_status')->default('unpaid');
        });
        Schema::create('seller_order_insurances', function (Blueprint $table): void {
            $table->id();
            $table->decimal('amount', 24, 3)->default(0);
        });
        Schema::create('seller_product_promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('status')->default('active');
        });

        Order::query()->create(['order_status' => 'pending']);

        $migration = require database_path('migrations/2026_08_14_000001_add_order_commerce_phase_zero_contract.php');
        $migration->up();

        foreach ([
            'orders' => ['commerce_flow_version', 'commerce_flow_status', 'admin_order_review_status'],
            'order_insurances' => ['payment_due_at', 'balance_use_policy', 'purchase_refund_status', 'confiscation_status'],
            'seller_order_insurances' => ['amount_source', 'balance_use_policy', 'confiscation_status'],
            'seller_product_promotions' => ['approval_status', 'placement_scope', 'approved_position'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "Missing {$table}.{$column}");
            }
        }

        $legacy = Order::query()->firstOrFail();
        $this->assertSame('legacy-v1', $legacy->commerce_flow_version);
        $this->assertNull($legacy->commerce_flow_status);
        $this->assertSame('legacy_not_required', $legacy->admin_order_review_status);

        foreach (['seller_product_promotions', 'seller_order_insurances', 'order_insurances', 'orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
