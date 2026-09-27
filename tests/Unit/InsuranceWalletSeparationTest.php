<?php

namespace Tests\Unit;

use App\Models\CustomerInsuranceLedgerEntry;
use App\Models\InsuranceBalanceAction;
use App\Models\OrderInsurance;
use App\Models\SellerLedgerEntry;
use App\Models\SellerOrderInsurance;
use App\Services\CustomerPurchaseWalletPolicyService;
use App\Services\InsuranceBalanceGovernanceService;
use App\Services\InsuranceWalletReconciliationService;
use App\Services\SellerLedgerService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InsuranceWalletSeparationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table): void { $table->id(); $table->decimal('wallet_balance', 24, 3)->default(0); $table->timestamps(); });
        Schema::create('sellers', function (Blueprint $table): void { $table->id(); $table->string('f_name')->nullable(); $table->timestamps(); });
        Schema::create('seller_wallets', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('seller_id'); $table->decimal('total_earning', 24, 3)->default(0); $table->decimal('pending_withdraw', 24, 3)->default(0); $table->decimal('withdrawn', 24, 3)->default(0); $table->timestamps(); });
        Schema::create('customer_insurance_ledger_entries', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->unsignedBigInteger('order_insurance_id')->nullable(); $table->unsignedBigInteger('order_id')->nullable();
            $table->string('entry_type', 40); $table->decimal('credit', 40, 20)->default(0); $table->decimal('debit', 40, 20)->default(0); $table->string('reference', 120)->unique(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('seller_ledger_entries', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('seller_id'); $table->string('bucket', 32); $table->string('direction', 8); $table->decimal('amount', 40, 20); $table->string('event_type', 64); $table->string('reporting_category', 64)->nullable(); $table->string('group_key', 191); $table->char('idempotency_key', 64)->unique(); $table->string('reference_type', 128)->nullable(); $table->unsignedBigInteger('reference_id')->nullable(); $table->string('reference_code')->nullable(); $table->json('metadata')->nullable(); $table->char('previous_hash', 64)->nullable(); $table->char('entry_hash', 64); $table->unsignedBigInteger('created_by')->nullable(); $table->timestamps();
        });
        Schema::create('insurance_balance_actions', function (Blueprint $table): void {
            $table->id(); $table->string('subject_type', 20); $table->unsignedBigInteger('subject_id'); $table->string('action', 40); $table->decimal('amount', 40, 20); $table->unsignedBigInteger('order_id')->nullable(); $table->unsignedBigInteger('order_insurance_id')->nullable(); $table->unsignedBigInteger('seller_order_insurance_id')->nullable(); $table->unsignedBigInteger('parent_action_id')->nullable(); $table->string('reference', 191)->unique(); $table->text('reason'); $table->json('evidence')->nullable(); $table->json('metadata')->nullable(); $table->unsignedBigInteger('admin_id')->nullable(); $table->timestamps();
        });
        Schema::create('order_insurances', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('order_id'); $table->unsignedBigInteger('customer_id'); $table->decimal('amount', 40, 20); $table->decimal('confiscated_amount', 40, 20)->default(0); $table->string('payment_status'); $table->string('confiscation_status')->default('none'); $table->unsignedBigInteger('confiscated_by_admin_id')->nullable(); $table->timestamp('confiscated_at')->nullable(); $table->text('confiscation_reason')->nullable(); $table->timestamp('matured_at')->nullable(); $table->timestamps();
        });
        Schema::create('seller_order_insurances', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('order_id'); $table->unsignedBigInteger('seller_id'); $table->decimal('amount', 40, 20); $table->decimal('confiscated_amount', 40, 20)->default(0); $table->string('payment_status'); $table->string('confiscation_status')->default('none'); $table->unsignedBigInteger('confiscated_by_admin_id')->nullable(); $table->timestamp('confiscated_at')->nullable(); $table->text('confiscation_reason')->nullable(); $table->timestamp('reusable_released_at')->nullable(); $table->timestamps();
        });
        Schema::create('wallet_transactions', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('user_id'); $table->string('transaction_type')->nullable(); $table->timestamps(); });

        DB::table('users')->insert(['id' => 1, 'wallet_balance' => 500, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sellers')->insert(['id' => 1, 'f_name' => 'Seller', 'created_at' => now(), 'updated_at' => now()]);
        CustomerInsuranceLedgerEntry::query()->create(['customer_id' => 1, 'entry_type' => 'matured_credit', 'credit' => 100, 'debit' => 0, 'reference' => 'customer-opening']);
        app(SellerLedgerService::class)->post(1, 'seller_order_insurance_credit_released', 'seller-opening', [['bucket' => SellerLedgerService::ORDER_INSURANCE_CREDIT, 'direction' => 'credit', 'amount' => 100]]);
    }

    protected function tearDown(): void
    {
        foreach (['wallet_transactions','seller_order_insurances','order_insurances','insurance_balance_actions','seller_ledger_entries','customer_insurance_ledger_entries','seller_wallets','sellers','users'] as $table) Schema::dropIfExists($table);
        parent::tearDown();
    }

    public function test_purchase_wallet_and_insurance_wallet_policies_are_one_way(): void
    {
        $policy = app(CustomerPurchaseWalletPolicyService::class);
        $this->assertSame(['product_purchase', 'purchase_refund'], $policy->contract()['allowed_uses']);
        $policy->assertTransactionAllowed('order_place');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('purchase_wallet_cannot_pay_insurance');
        $policy->assertTransactionAllowed('customer_insurance_payment');
    }

    public function test_customer_hold_partial_release_confiscation_and_reversal_are_audited_and_idempotent(): void
    {
        $service = app(InsuranceBalanceGovernanceService::class);
        $hold = $service->hold('customer', 1, 60, 'customer-hold-1', 'Suspicious order', 9, ['order_id' => 501]);
        $this->assertSame($hold->id, $service->hold('customer', 1, 60, 'customer-hold-1', 'Suspicious order', 9)->id);
        $service->releaseHold($hold, 20, 'customer-release-1', 'Investigation partial release', 9);
        $confiscation = $service->confiscateHold($hold, 40, 'customer-confiscate-1', 'Confirmed abuse', 9);
        $service->reverseConfiscation($confiscation, 10, 'customer-reverse-1', 'Appeal accepted in part', 10);

        $net = (float) CustomerInsuranceLedgerEntry::query()->selectRaw('SUM(credit-debit) AS balance')->value('balance');
        $this->assertSame(70.0, $net);
        $this->assertSame(0.0, $service->heldBalance('customer', 1));
        $this->assertDatabaseCount('insurance_balance_actions', 4);
        $this->assertSame(500.0, (float) DB::table('users')->where('id', 1)->value('wallet_balance'));
    }

    public function test_seller_insurance_governance_never_touches_operating_balance(): void
    {
        $service = app(InsuranceBalanceGovernanceService::class);
        $hold = $service->hold('seller', 1, 50, 'seller-hold-1', 'Order review', 9);
        $service->releaseHold($hold, 20, 'seller-release-1', 'Partial release', 9);
        $service->confiscateHold($hold, 30, 'seller-confiscate-1', 'Confirmed violation', 9);

        $summary = app(SellerLedgerService::class)->summary(1);
        $this->assertSame(70.0, $summary[SellerLedgerService::ORDER_INSURANCE_CREDIT]);
        $this->assertSame(0.0, $summary[SellerLedgerService::OPERATING]);
        $this->assertSame(0, SellerLedgerEntry::query()->where('bucket', SellerLedgerService::OPERATING)->where('event_type', 'like', '%insurance%')->count());
    }

    public function test_customer_paid_insurance_record_can_be_partially_confiscated_before_maturity_without_double_debit(): void
    {
        $insurance = OrderInsurance::query()->create(['order_id' => 701, 'customer_id' => 1, 'amount' => 80, 'payment_status' => 'paid']);
        $service = app(InsuranceBalanceGovernanceService::class);
        $confiscation = $service->confiscateCustomerInsurance($insurance, 30, 'customer-record-confiscate', 'Confirmed abuse', 9);

        $this->assertFalse((bool) data_get($confiscation->metadata, 'balance_debited'));
        $this->assertSame(30.0, (float) $insurance->fresh()->confiscated_amount);
        $this->assertSame(100.0, (float) CustomerInsuranceLedgerEntry::query()->selectRaw('SUM(credit-debit) AS balance')->value('balance'));

        $service->reverseConfiscation($confiscation, 10, 'customer-record-reverse', 'Partial appeal accepted', 10);
        $this->assertSame(20.0, (float) $insurance->fresh()->confiscated_amount);
        $this->assertSame(100.0, (float) CustomerInsuranceLedgerEntry::query()->selectRaw('SUM(credit-debit) AS balance')->value('balance'));
    }

    public function test_seller_paid_insurance_record_can_be_confiscated_before_reuse_without_touching_operating_or_credit_twice(): void
    {
        $insurance = SellerOrderInsurance::query()->create(['order_id' => 702, 'seller_id' => 1, 'amount' => 70, 'payment_status' => 'paid']);
        $service = app(InsuranceBalanceGovernanceService::class);
        $service->confiscateSellerInsurance($insurance, 40, 'seller-record-confiscate', 'Confirmed abuse', 9);

        $this->assertSame(40.0, (float) $insurance->fresh()->confiscated_amount);
        $summary = app(SellerLedgerService::class)->summary(1);
        $this->assertSame(100.0, $summary[SellerLedgerService::ORDER_INSURANCE_CREDIT]);
        $this->assertSame(0.0, $summary[SellerLedgerService::OPERATING]);
    }

    public function test_released_customer_insurance_record_debits_and_reverses_the_insurance_wallet_exactly_once(): void
    {
        $insurance = OrderInsurance::query()->create(['order_id' => 703, 'customer_id' => 1, 'amount' => 50, 'payment_status' => 'paid', 'matured_at' => now()]);
        $service = app(InsuranceBalanceGovernanceService::class);
        $confiscation = $service->confiscateCustomerInsurance($insurance, 20, 'released-record-confiscate', 'Confirmed abuse', 9);
        $this->assertTrue((bool) data_get($confiscation->metadata, 'balance_debited'));
        $this->assertSame(80.0, (float) CustomerInsuranceLedgerEntry::query()->selectRaw('SUM(credit-debit) AS balance')->value('balance'));

        $service->reverseConfiscation($confiscation, 5, 'released-record-reverse', 'Partial appeal', 10);
        $this->assertSame(85.0, (float) CustomerInsuranceLedgerEntry::query()->selectRaw('SUM(credit-debit) AS balance')->value('balance'));
        $this->assertSame(15.0, (float) $insurance->fresh()->confiscated_amount);
    }

    public function test_reconciliation_reports_separate_balances_and_no_new_cross_wallet_violation(): void
    {
        $summary = app(InsuranceWalletReconciliationService::class)->summary();
        $this->assertSame(100.0, $summary['customer_insurance_balance']);
        $this->assertSame(100.0, $summary['seller_insurance_balance']);
        $this->assertSame(0, $summary['purchase_wallet_insurance_violations']);
        $this->assertSame(0, $summary['new_customer_ledger_policy_violations']);
        $this->assertTrue($summary['is_reconciled']);
    }

    public function test_phase_one_migration_is_additive_and_enforces_insurance_only_policy(): void
    {
        Schema::drop('insurance_balance_actions');
        Schema::drop('seller_order_insurances');
        Schema::drop('order_insurances');
        Schema::create('order_insurances', function (Blueprint $table): void { $table->id(); $table->string('balance_use_policy')->default('legacy_mixed'); });
        Schema::create('seller_order_insurances', function (Blueprint $table): void { $table->id(); $table->string('balance_use_policy')->default('legacy_mixed'); });
        DB::table('order_insurances')->insert(['balance_use_policy' => 'legacy_mixed']);
        DB::table('seller_order_insurances')->insert(['balance_use_policy' => 'legacy_mixed']);

        $migration = require database_path('migrations/2026_08_14_000002_create_insurance_balance_governance.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('insurance_balance_actions'));
        $this->assertSame('insurance_only', DB::table('order_insurances')->value('balance_use_policy'));
        $this->assertSame('insurance_only', DB::table('seller_order_insurances')->value('balance_use_policy'));

        Schema::dropIfExists('seller_order_insurances');
        Schema::dropIfExists('order_insurances');
    }
}
