<?php

namespace Tests\Unit;

use App\Events\OrderStatusEvent;
use App\Models\CustomerInsuranceLedgerEntry;
use App\Models\Order;
use App\Models\OrderInsurance;
use App\Services\CustomerPostPurchaseInsuranceService;
use App\Support\Commerce\OrderCommerceState;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerPostPurchaseInsuranceFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('order_commerce.features.customer_insurance_after_purchase', true);
        config()->set('customer_purchase_packages.enabled', false);
        Event::fake([OrderStatusEvent::class]);

        Schema::create('business_settings', function (Blueprint $table): void {
            $table->id(); $table->string('type')->unique(); $table->text('value')->nullable(); $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id(); $table->string('name')->nullable(); $table->string('email')->nullable();
            $table->string('phone')->nullable(); $table->string('password')->nullable();
            $table->decimal('wallet_balance', 24, 3)->default(0); $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id')->nullable(); $table->boolean('is_guest')->default(false);
            $table->unsignedBigInteger('seller_id')->nullable(); $table->string('seller_is')->default('seller');
            $table->string('payment_status')->default('unpaid'); $table->string('payment_method')->nullable();
            $table->string('transaction_ref')->nullable(); $table->decimal('order_amount', 24, 3)->default(0);
            $table->string('order_status')->default('pending'); $table->string('commerce_flow_version')->default('legacy-v1');
            $table->string('commerce_flow_status')->nullable(); $table->timestamp('commerce_flow_status_updated_at')->nullable();
            $table->string('admin_order_review_status')->default('legacy_not_required');
            $table->string('operational_blocked_by')->nullable(); $table->text('operational_block_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('order_details', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('order_id'); $table->unsignedBigInteger('product_id')->nullable();
            $table->string('variant')->nullable(); $table->unsignedInteger('qty')->default(1);
            $table->boolean('is_stock_decreased')->default(false); $table->string('delivery_status')->nullable(); $table->timestamps();
        });
        Schema::create('order_insurances', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('order_id')->unique(); $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('insurance_rule_id')->nullable(); $table->unsignedBigInteger('insurance_incentive_id')->nullable();
            $table->decimal('amount', 24, 3); $table->decimal('original_amount', 24, 3)->default(0);
            $table->decimal('discount_amount', 24, 3)->default(0); $table->decimal('order_amount', 24, 3);
            $table->decimal('threshold_amount', 24, 3)->default(0); $table->string('calculation_type');
            $table->decimal('calculation_value', 24, 3); $table->string('payment_method'); $table->string('payment_status');
            $table->timestamp('payment_due_at')->nullable(); $table->timestamp('payment_completed_at')->nullable();
            $table->unsignedBigInteger('support_ticket_id')->nullable(); $table->string('balance_use_policy')->default('insurance_only');
            $table->string('purchase_refund_status')->default('not_applicable'); $table->timestamp('purchase_refund_due_at')->nullable();
            $table->timestamp('purchase_refund_completed_at')->nullable(); $table->string('purchase_refund_reference')->nullable();
            $table->string('confiscation_status')->default('none'); $table->decimal('confiscated_amount', 24, 3)->default(0);
            $table->string('status'); $table->unsignedBigInteger('admin_id')->nullable(); $table->text('admin_note')->nullable();
            $table->unsignedInteger('maturity_days')->default(90); $table->timestamp('matures_at')->nullable();
            $table->timestamp('matured_at')->nullable(); $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable(); $table->json('rule_snapshot')->nullable(); $table->timestamps();
        });
        Schema::create('customer_insurance_ledger_entries', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->unsignedBigInteger('order_insurance_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable(); $table->string('entry_type');
            $table->decimal('credit', 24, 3)->default(0); $table->decimal('debit', 24, 3)->default(0);
            $table->string('reference')->unique(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->uuid('transaction_id');
            $table->string('reference')->nullable()->unique(); $table->string('transaction_type')->nullable();
            $table->string('payment_method')->nullable(); $table->decimal('credit', 24, 3)->default(0);
            $table->decimal('debit', 24, 3)->default(0); $table->decimal('admin_bonus', 24, 3)->default(0);
            $table->decimal('balance', 24, 3)->default(0); $table->timestamps();
        });
        Schema::create('customer_purchase_limit_transactions', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('amount', 24, 3)->default(0); $table->string('transaction_type');
            $table->string('reference')->unique(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id')->nullable(); $table->string('subject')->nullable();
            $table->string('type')->nullable(); $table->string('purpose')->nullable(); $table->string('priority')->default('medium');
            $table->text('description')->nullable(); $table->text('reply')->nullable(); $table->string('status')->default('open');
            $table->string('review_status')->nullable(); $table->text('review_note')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable(); $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('closed_at')->nullable(); $table->timestamp('archived_at')->nullable(); $table->json('attachment')->nullable();
            $table->timestamps();
        });

        DB::table('users')->insert(['id' => 1, 'name' => 'Customer', 'wallet_balance' => 0, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['customer_order_insurance_payment_deadline_hours' => 48, 'customer_purchase_refund_delay_days' => 0] as $type => $value) {
            DB::table('business_settings')->insert(['type' => $type, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('business_settings')->insert([
            'type' => 'language',
            'value' => json_encode([['code' => 'en', 'name' => 'English', 'default' => true, 'direction' => 'ltr']]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        foreach (['support_tickets', 'customer_purchase_limit_transactions', 'wallet_transactions', 'customer_insurance_ledger_entries', 'order_insurances', 'order_details', 'orders', 'users', 'business_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_purchase_payment_never_creates_another_insurance_requirement(): void
    {
        $order=$this->order('unpaid',2000,'purchase-ref-100');
        $service=app(CustomerPostPurchaseInsuranceService::class);
        $this->assertNull($service->initializeNewOrder($order,$this->calculation(200)));
        $order->update(['payment_status'=>'paid']);
        $this->assertNull($service->activateAfterPurchasePayment($order->fresh(),$this->calculation(200)));
        $this->assertDatabaseCount('order_insurances',0);
        $this->assertEquals(2000,$order->fresh()->order_amount);
        $this->assertSame('purchase-ref-100',$order->fresh()->transaction_ref);
        $this->assertFalse($service->payload($order)['required']);
    }

    public function test_old_insurance_expiry_cannot_cancel_paid_orders(): void
    {
        $order=$this->order('paid',2000);
        $service=app(CustomerPostPurchaseInsuranceService::class);
        $this->assertSame(0,$service->expireDue());
        $this->assertSame('pending',$order->fresh()->order_status);
        $this->assertDatabaseCount('wallet_transactions',0);
    }

    public function test_retired_insurance_cannot_accept_wallet_or_offline_payment(): void
    {
        $service=app(CustomerPostPurchaseInsuranceService::class);
        foreach ([fn()=>$service->payFromInsuranceBalance(new OrderInsurance()), fn()=>$service->submitOfflinePayment(new OrderInsurance(),['reference'=>'retired'])] as $action) {
            try { $action(); $this->fail('Retired insurance payment accepted'); }
            catch(DomainException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
        $this->assertDatabaseCount('wallet_transactions',0);
        $this->assertDatabaseCount('customer_insurance_ledger_entries',0);
    }
    public function test_phase_two_routes_schedules_and_seller_visibility_guards_are_present(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web/routes.php'));
        $apiRoutes = file_get_contents(base_path('routes/rest_api/v1/api.php'));
        $schedule = file_get_contents(base_path('bootstrap/app.php'));
        $sellerWeb = file_get_contents(app_path('Http/Controllers/Vendor/Order/OrderController.php'));
        $sellerApi = file_get_contents(app_path('Http/Controllers/RestAPI/v3/seller/OrderController.php'));

        $this->assertStringContainsString("prefix' => 'post-purchase-invoices'", $webRoutes);
        $this->assertStringContainsString("prefix' => 'customer/post-purchase-invoices'", $apiRoutes);
        $this->assertStringContainsString('post-purchase-invoices:expire', $schedule);
        $this->assertStringContainsString('commerce:release-matured-refunds', $schedule);
        $this->assertStringContainsString("'seller_visible_only' => true", $sellerWeb);
        $this->assertStringContainsString("'seller_visible_only' => true", $sellerApi);
    }

    private function order(string $paymentStatus, float $amount, string $reference = 'purchase-ref'): Order
    {
        return Order::query()->create([
            'customer_id' => 1, 'is_guest' => false, 'seller_id' => 9, 'seller_is' => 'seller',
            'payment_status' => $paymentStatus, 'payment_method' => 'digital_payment',
            'transaction_ref' => $reference, 'order_amount' => $amount, 'order_status' => 'pending',
        ]);
    }

    private function calculation(float $amount): array
    {
        return [
            'applicable' => true, 'amount' => $amount, 'original_amount' => $amount, 'discount_amount' => 0,
            'threshold' => 1000, 'type' => 'percentage', 'value' => 10, 'orderAmount' => 2000,
            'maturityDays' => 90, 'rule_id' => null, 'rule_code' => 'test-rule', 'rule_snapshot' => [],
            'incentive_id' => null, 'incentive_snapshot' => null, 'withdrawable' => false,
            'allowed_uses_after_maturity' => ['insurance_payment'],
        ];
    }
}
