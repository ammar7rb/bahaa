<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerOrderInsurance;
use App\Services\SellerLedgerService;
use App\Services\SellerOrderInsuranceService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerOrderInsuranceServiceTest extends TestCase
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
        Schema::create('sellers', function (Blueprint $table): void {
            $table->id();
            $table->string('status')->default('approved');
            $table->timestamps();
        });
        Schema::create('seller_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->decimal('total_earning', 24, 3)->default(0);
            $table->decimal('pending_withdraw', 24, 3)->default(0);
            $table->decimal('withdrawn', 24, 3)->default(0);
            $table->timestamps();
        });
        Schema::create('seller_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('bucket', 32);
            $table->string('direction', 8);
            $table->decimal('amount', 40, 20);
            $table->string('event_type', 64);
            $table->string('reporting_category', 64)->nullable();
            $table->string('group_key', 191);
            $table->char('idempotency_key', 64)->unique();
            $table->string('reference_type', 128)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_code')->nullable();
            $table->json('metadata')->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('entry_hash', 64);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('seller_is')->default('seller');
            $table->string('order_status')->default('pending');
            $table->decimal('order_amount', 24, 3)->default(0);
            $table->timestamps();
        });
        Schema::create('seller_order_insurances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_id');
            $table->decimal('amount', 24, 3);
            $table->string('amount_source')->default('legacy_rule');
            $table->string('admin_override_type')->nullable();
            $table->decimal('admin_override_value', 24, 3)->nullable();
            $table->unsignedBigInteger('admin_decided_by')->nullable();
            $table->timestamp('admin_decided_at')->nullable();
            $table->text('admin_decision_reason')->nullable();
            $table->string('balance_use_policy')->default('insurance_only');
            $table->unsignedBigInteger('insurance_rule_id')->nullable();
            $table->decimal('order_amount', 24, 3);
            $table->decimal('percentage', 8, 4);
            $table->string('calculation_type')->nullable();
            $table->decimal('calculation_value', 24, 3)->nullable();
            $table->json('rule_snapshot')->nullable();
            $table->string('status');
            $table->string('payment_status');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->uuid('payment_request_id')->nullable();
            $table->unsignedSmallInteger('pending_days')->default(3);
            $table->unsignedSmallInteger('reuse_after_days')->default(90);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('reusable_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reusable_released_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->text('admin_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'seller_id']);
        });
        Schema::create('seller_order_insurance_decisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_order_insurance_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('action');
            $table->string('status_before');
            $table->string('status_after');
            $table->text('reason');
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        foreach ([
            'seller_order_insurance_status' => '1',
            'seller_order_insurance_scope' => 'all',
            'seller_order_insurance_percentage' => '5',
            'seller_order_insurance_pending_days' => '4',
            'seller_order_insurance_reuse_after_days' => '90',
            'seller_order_insurance_expiry_action' => 'admin_review',
        ] as $type => $value) {
            DB::table('business_settings')->insert(['type' => $type, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('seller_order_insurance_decisions');
        Schema::dropIfExists('seller_order_insurances');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('seller_ledger_entries');
        Schema::dropIfExists('seller_wallets');
        Schema::dropIfExists('sellers');
        Schema::dropIfExists('business_settings');
        parent::tearDown();
    }

    public function test_insurance_is_calculated_from_the_order_total_and_hides_details_until_payment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
        $seller = Seller::query()->create(['status' => 'approved']);
        $order = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 2000]);
        $service = app(SellerOrderInsuranceService::class);

        $insurance = $service->getOrCreate($order, $seller);

        $this->assertSame(100.0, $insurance->amount);
        $this->assertSame(5.0, $insurance->percentage);
        $this->assertSame(4, $insurance->pending_days);
        $this->assertTrue($insurance->expires_at->equalTo(now()->addDays(4)));
        $this->assertSame(90, $insurance->reuse_after_days);
        $this->assertFalse($service->canViewDetails($insurance));
        $this->assertSame($insurance->id, $service->getOrCreate($order, $seller)->id);

        $paid = $service->markPaid($insurance, 'offline_payment', 'ADMIN-APPROVED', 9);
        $this->assertSame(SellerOrderInsurance::STATUS_PAID, $paid->status);
        $this->assertTrue($paid->reusable_at->equalTo(now()->addDays(90)));
        $this->assertTrue($service->canViewDetails($paid));
    }

    public function test_selected_scope_does_not_create_insurance_for_unselected_seller(): void
    {
        DB::table('business_settings')->where('type', 'seller_order_insurance_scope')->update(['value' => 'selected']);
        $seller = Seller::query()->create(['status' => 'approved']);
        $order = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 2000]);
        $service = app(SellerOrderInsuranceService::class);

        $this->assertFalse($service->preview($order, $seller)['applicable']);
        $this->assertNull($service->getOrCreate($order, $seller));
        $this->assertDatabaseCount('seller_order_insurances', 0);
    }

    public function test_insurance_credit_is_unavailable_before_maturity_and_can_pay_after_release(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
        $seller = Seller::query()->create(['status' => 'approved']);
        $service = app(SellerOrderInsuranceService::class);
        $ledger = app(SellerLedgerService::class);
        $firstOrder = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 2000]);
        $firstInsurance = $service->markPaid($service->getOrCreate($firstOrder, $seller), 'offline_payment');

        $this->assertSame(0, $service->releaseReusableCredits());
        $this->assertSame(0.0, $ledger->summary($seller->id)[SellerLedgerService::ORDER_INSURANCE_CREDIT]);
        $pendingOrder = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 2000]);
        $pendingInsurance = $service->getOrCreate($pendingOrder, $seller);
        try {
            $service->payFromReusableCredit($pendingInsurance);
            $this->fail('Unmatured insurance must not be available to pay another order.');
        } catch (DomainException $exception) {
            $this->assertSame('seller_order_insurance_credit_insufficient', $exception->getMessage());
        }
        $this->assertSame(SellerOrderInsurance::STATUS_PENDING_PAYMENT, $pendingInsurance->fresh()->status);

        Carbon::setTestNow($firstInsurance->reusable_at->copy()->addSecond());
        $this->assertSame(1, $service->releaseReusableCredits());
        $this->assertSame(100.0, $ledger->summary($seller->id)[SellerLedgerService::ORDER_INSURANCE_CREDIT]);

        $secondOrder = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 2000]);
        $secondInsurance = $service->payFromReusableCredit($service->getOrCreate($secondOrder, $seller));
        $this->assertSame(SellerOrderInsurance::STATUS_PAID, $secondInsurance->status);
        $this->assertSame(0.0, $ledger->summary($seller->id)[SellerLedgerService::ORDER_INSURANCE_CREDIT]);
    }

    public function test_insurance_credit_payment_rejects_a_partial_balance(): void
    {
        $seller = Seller::query()->create(['status' => 'approved']);
        $order = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 2000]);
        $insurance = app(SellerOrderInsuranceService::class)->getOrCreate($order, $seller);
        app(SellerLedgerService::class)->post(
            sellerId: $seller->id,
            eventType: 'seller_order_insurance_credit_released',
            groupKey: 'partial-credit',
            movements: [['bucket' => SellerLedgerService::ORDER_INSURANCE_CREDIT, 'direction' => 'credit', 'amount' => 40]],
        );

        try {
            app(SellerOrderInsuranceService::class)->payFromReusableCredit($insurance);
            $this->fail('Partial insurance credit must not pay the order insurance.');
        } catch (DomainException $exception) {
            $this->assertSame('seller_order_insurance_credit_insufficient', $exception->getMessage());
        }
        $this->assertSame(SellerOrderInsurance::STATUS_PENDING_PAYMENT, $insurance->fresh()->status);
        $this->assertSame(40.0, app(SellerLedgerService::class)->summary($seller->id)[SellerLedgerService::ORDER_INSURANCE_CREDIT]);
    }

    public function test_admin_can_override_seller_insurance_with_audited_percentage_or_fixed_amount(): void
    {
        $seller = Seller::query()->create(['status' => 'approved']);
        $order = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 2000]);
        $service = app(SellerOrderInsuranceService::class);

        $percentage = $service->createFromAdminDecision($order, $seller, [
            'mode' => 'manual', 'calculation_type' => 'percentage',
            'calculation_value' => 7.5, 'reason' => 'Risk tier adjustment',
        ], 11);

        $this->assertSame(150.0, $percentage->amount);
        $this->assertSame('admin_override', $percentage->amount_source);
        $this->assertSame('percentage', $percentage->admin_override_type);
        $this->assertSame(7.5, $percentage->admin_override_value);
        $this->assertSame(11, $percentage->admin_decided_by);
        $this->assertSame('Risk tier adjustment', $percentage->admin_decision_reason);
    }

    public function test_zero_manual_amount_waives_insurance(): void
    {
        $seller = Seller::query()->create(['status' => 'approved']);
        $order = Order::query()->create(['seller_id'=>$seller->id,'seller_is'=>'seller','order_amount'=>1200]);
        $insurance = app(SellerOrderInsuranceService::class)->createFromAdminDecision($order, $seller, [
            'mode'=>'manual','calculation_type'=>'fixed','calculation_value'=>0,'reason'=>'Exempt this order',
        ], 8);
        $this->assertSame(SellerOrderInsurance::STATUS_WAIVED, $insurance->status);
        $this->assertSame('waived', $insurance->payment_status);
        $this->assertEquals(0, $insurance->amount);
    }

    public function test_expired_unpaid_insurance_remains_locked_for_an_administrative_decision(): void
    {
        $seller = Seller::query()->create(['status' => 'approved']);
        $order = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 1200]);
        $service = app(SellerOrderInsuranceService::class);
        $insurance = $service->getOrCreate($order, $seller);
        $insurance->update(['expires_at' => now()->subSecond()]);

        $this->assertSame(1, $service->expireDue());
        $this->assertSame(SellerOrderInsurance::STATUS_EXPIRED, $insurance->fresh()->status);
        $this->assertFalse($service->canViewDetails($insurance->fresh()));
    }

    public function test_operating_balance_can_never_pay_seller_order_insurance(): void
    {
        $seller = Seller::query()->create(['status' => 'approved']);
        $order = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 1200]);
        $service = app(SellerOrderInsuranceService::class);
        $insurance = $service->getOrCreate($order, $seller);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('seller_operating_balance_cannot_pay_insurance');
        $service->payFromOperatingBalance($insurance);
    }

    public function test_admin_can_extend_an_expired_insurance_with_an_audited_reason(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-11 09:00:00'));
        $seller = Seller::query()->create(['status' => 'approved']);
        $order = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 1200]);
        $service = app(SellerOrderInsuranceService::class);
        $insurance = $service->getOrCreate($order, $seller);
        $insurance->update(['status' => SellerOrderInsurance::STATUS_EXPIRED, 'expired_at' => now()]);

        $decision = $service->decideExpired($insurance, 'extend', 'Customer support verified a delay.', 7, 5);
        $updated = $insurance->fresh();

        $this->assertSame('extend', $decision->action);
        $this->assertSame(7, $decision->admin_id);
        $this->assertSame(SellerOrderInsurance::STATUS_PENDING_PAYMENT, $updated->status);
        $this->assertNull($updated->expired_at);
        $this->assertTrue($updated->expires_at->equalTo(now()->addDays(5)));
        $this->assertFalse($service->canViewDetails($updated));
    }

    public function test_waiver_is_the_only_expired_decision_that_opens_details_without_payment(): void
    {
        $seller = Seller::query()->create(['status' => 'approved']);
        $order = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 1200]);
        $service = app(SellerOrderInsuranceService::class);
        $insurance = $service->getOrCreate($order, $seller);
        $insurance->update(['status' => SellerOrderInsurance::STATUS_EXPIRED, 'expired_at' => now()]);

        $service->decideExpired($insurance, 'waive', 'Approved administrative exemption.', 8);
        $updated = $insurance->fresh();

        $this->assertSame(SellerOrderInsurance::STATUS_WAIVED, $updated->status);
        $this->assertSame('waived', $updated->payment_status);
        $this->assertTrue($service->canViewDetails($updated));
        $this->assertDatabaseHas('seller_order_insurance_decisions', [
            'seller_order_insurance_id' => $insurance->id,
            'action' => 'waive',
            'status_before' => SellerOrderInsurance::STATUS_EXPIRED,
            'status_after' => SellerOrderInsurance::STATUS_WAIVED,
        ]);
    }

    public function test_cancellation_decision_is_audited_without_bypassing_the_standard_order_cancellation_flow(): void
    {
        $seller = Seller::query()->create(['status' => 'approved']);
        $order = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 1200]);
        $service = app(SellerOrderInsuranceService::class);
        $insurance = $service->getOrCreate($order, $seller);
        $insurance->update(['status' => SellerOrderInsurance::STATUS_EXPIRED, 'expired_at' => now()]);

        $decision = $service->decideExpired($insurance, 'request_cancellation', 'Operations must verify stock and refund first.', 9);

        $this->assertSame('request_cancellation', $decision->action);
        $this->assertTrue((bool) data_get($decision->payload, 'cancellation_requested'));
        $this->assertSame('order_cancellation', data_get($insurance->fresh()->metadata, 'administrative_queue'));
        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertFalse($service->canViewDetails($insurance->fresh()));
    }

    public function test_offline_payment_waits_for_admin_review_and_can_be_rejected_for_a_new_attempt(): void
    {
        $seller = Seller::query()->create(['status' => 'approved']);
        $order = Order::query()->create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 2000]);
        $service = app(SellerOrderInsuranceService::class);
        $insurance = $service->getOrCreate($order, $seller);

        $pendingReview = $service->submitOfflinePayment($insurance, ['method_id' => 8, 'method_name' => 'Bank transfer']);
        $this->assertSame(SellerOrderInsurance::STATUS_PENDING_REVIEW, $pendingReview->status);
        $this->assertSame('offline_payment', $pendingReview->payment_method);
        $this->assertFalse($service->canViewDetails($pendingReview));

        $retry = $service->rejectOfflinePayment($pendingReview, 4, 'proof is not clear');
        $this->assertSame(SellerOrderInsurance::STATUS_PENDING_PAYMENT, $retry->status);
        $this->assertNull($retry->payment_method);
        $this->assertSame('rejected', data_get($retry->metadata, 'offline_payment_review.action'));
    }
}
