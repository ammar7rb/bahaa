<?php

namespace Tests\Unit;

use App\Models\InsuranceIncentive;
use App\Models\InsuranceRule;
use App\Models\InsuranceSubjectOverride;
use App\Models\OrderInsurance;
use App\Services\CustomerInsuranceBalanceService;
use App\Services\CustomerInsuranceIncentiveService;
use App\Services\InsuranceRuleEngineService;
use App\Services\OrderInsuranceService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InsuranceRulesAndIncentivesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('business_settings', function (Blueprint $table): void {
            $table->id(); $table->string('type')->unique(); $table->text('value')->nullable(); $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id(); $table->string('name')->nullable(); $table->string('email')->nullable();
            $table->string('phone')->nullable(); $table->string('password')->nullable();
            $table->decimal('wallet_balance', 24, 3)->default(0); $table->timestamps();
        });
        Schema::create('insurance_rules', function (Blueprint $table): void {
            $table->id(); $table->string('subject_type'); $table->string('name'); $table->string('code');
            $table->string('rule_type')->default('default'); $table->unsignedInteger('priority')->default(1000);
            $table->boolean('is_active')->default(true); $table->decimal('minimum_order_amount', 24, 3)->default(0);
            $table->decimal('maximum_order_amount', 24, 3)->nullable(); $table->unsignedInteger('minimum_account_age_days')->default(0);
            $table->unsignedInteger('maximum_account_age_days')->nullable(); $table->string('calculation_type');
            $table->decimal('calculation_value', 24, 6); $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
            $table->unique(['subject_type', 'code']);
        });
        Schema::create('insurance_subject_overrides', function (Blueprint $table): void {
            $table->id(); $table->string('subject_type'); $table->unsignedBigInteger('subject_id');
            $table->string('mode'); $table->string('calculation_type')->nullable();
            $table->decimal('calculation_value', 24, 3)->nullable(); $table->string('reason');
            $table->boolean('is_active')->default(true); $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps(); $table->unique(['subject_type', 'subject_id']);
        });
        Schema::create('insurance_incentives', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('code')->unique(); $table->string('incentive_type');
            $table->string('value_type'); $table->decimal('value', 24, 6); $table->decimal('maximum_amount', 24, 3)->nullable();
            $table->unsignedInteger('priority')->default(1000); $table->boolean('is_active')->default(true);
            $table->boolean('new_accounts_only')->default(false); $table->unsignedInteger('minimum_account_age_days')->default(0);
            $table->unsignedInteger('maximum_account_age_days')->nullable(); $table->unsignedInteger('usage_limit_per_customer')->default(1);
            $table->timestamp('starts_at')->nullable(); $table->timestamp('ends_at')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('insurance_incentive_redemptions', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('insurance_incentive_id'); $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('order_insurance_id')->nullable(); $table->string('redemption_type');
            $table->decimal('amount', 24, 3); $table->string('reference')->unique(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('order_insurances', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('order_id'); $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('insurance_rule_id')->nullable(); $table->unsignedBigInteger('insurance_incentive_id')->nullable();
            $table->decimal('amount', 24, 3); $table->decimal('original_amount', 24, 3)->default(0);
            $table->decimal('discount_amount', 24, 3)->default(0); $table->decimal('order_amount', 24, 3);
            $table->decimal('threshold_amount', 24, 3); $table->string('calculation_type'); $table->decimal('calculation_value', 24, 3);
            $table->unsignedInteger('maturity_days')->default(90); $table->string('payment_method'); $table->string('payment_status');
            $table->string('status'); $table->unsignedBigInteger('admin_id')->nullable(); $table->text('admin_note')->nullable();
            $table->timestamp('refunded_at')->nullable(); $table->timestamp('matures_at')->nullable(); $table->timestamp('matured_at')->nullable();
            $table->json('metadata')->nullable(); $table->json('rule_snapshot')->nullable(); $table->timestamps();
        });
        Schema::create('customer_insurance_ledger_entries', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->unsignedBigInteger('order_insurance_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable(); $table->string('entry_type'); $table->decimal('credit', 24, 3)->default(0);
            $table->decimal('debit', 24, 3)->default(0); $table->string('reference')->unique(); $table->json('metadata')->nullable(); $table->timestamps();
        });

        foreach ([
            'customer_order_insurance_status' => 1, 'customer_order_insurance_scope' => 'all', 'customer_order_insurance_threshold' => 1000,
            'customer_order_insurance_calculation_type' => 'percentage', 'customer_order_insurance_calculation_value' => 2,
            'customer_order_insurance_maturity_days' => 90,
        ] as $type => $value) {
            DB::table('business_settings')->insert(['type' => $type, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        foreach (['customer_insurance_ledger_entries', 'order_insurances', 'insurance_incentive_redemptions', 'insurance_subject_overrides',
            'insurance_incentives', 'insurance_rules', 'users', 'business_settings'] as $table) Schema::dropIfExists($table);
        parent::tearDown();
    }

    public function test_customer_and_seller_rules_are_independent_and_priority_ordered(): void
    {
        $this->rule('customer', 'customer-default', 1000, 0, null, 'percentage', 2);
        $this->rule('customer', 'customer-high-value', 10, 1000, null, 'percentage', 7);
        $this->rule('seller', 'seller-default', 1000, 0, null, 'fixed', 150);
        $engine = app(InsuranceRuleEngineService::class);

        $customer = $engine->resolve('customer', 2000);
        $seller = $engine->resolve('seller', 2000);

        $this->assertSame('customer-high-value', $customer['rule_code']);
        $this->assertSame(140.0, $customer['amount']);
        $this->assertSame('seller-default', $seller['rule_code']);
        $this->assertSame(150.0, $seller['amount']);
    }

    public function test_account_age_rule_is_selected_only_inside_its_configured_range(): void
    {
        Carbon::setTestNow('2026-08-11 10:00:00');
        $this->rule('customer', 'customer-default', 1000, 0, null, 'percentage', 2);
        $rule = $this->rule('customer', 'new-customer', 5, 0, null, 'percentage', 10);
        $rule->update(['minimum_account_age_days' => 0, 'maximum_account_age_days' => 30]);
        $engine = app(InsuranceRuleEngineService::class);

        $this->assertSame('new-customer', $engine->resolve('customer', 100, now()->subDays(10))['rule_code']);
        $this->assertSame('customer-default', $engine->resolve('customer', 100, now()->subDays(60))['rule_code']);
    }

    public function test_welcome_credit_and_premium_discount_are_separate_idempotent_records(): void
    {
        $customerId = DB::table('users')->insertGetId(['name' => 'Customer', 'created_at' => now(), 'updated_at' => now()]);
        InsuranceIncentive::query()->create([
            'name' => 'Welcome 50', 'code' => 'welcome-50', 'incentive_type' => 'welcome_credit',
            'value_type' => 'fixed', 'value' => 50, 'priority' => 1, 'is_active' => true,
            'new_accounts_only' => true, 'minimum_account_age_days' => 0, 'maximum_account_age_days' => 30,
            'usage_limit_per_customer' => 1,
        ]);
        InsuranceIncentive::query()->create([
            'name' => 'Premium 25%', 'code' => 'premium-25', 'incentive_type' => 'premium_discount',
            'value_type' => 'percentage', 'value' => 25, 'maximum_amount' => 30, 'priority' => 1,
            'is_active' => true, 'new_accounts_only' => false, 'minimum_account_age_days' => 0,
            'usage_limit_per_customer' => 1,
        ]);
        $incentives = app(CustomerInsuranceIncentiveService::class);

        $this->assertSame(50.0, $incentives->grantEligibleWelcomeCredits($customerId));
        $this->assertSame(0.0, $incentives->grantEligibleWelcomeCredits($customerId));
        $discount = $incentives->discountFor($customerId, 200);
        $this->assertSame(30.0, $discount['discount_amount']);
        $this->assertSame(50.0, app(CustomerInsuranceBalanceService::class)->summary($customerId)['available_balance']);
        $this->assertDatabaseCount('insurance_incentive_redemptions', 1);
        $this->assertDatabaseHas('customer_insurance_ledger_entries', ['customer_id' => $customerId, 'entry_type' => 'welcome_credit', 'credit' => 50]);
    }

    public function test_account_override_can_exempt_or_require_a_special_insurance_amount(): void
    {
        $this->rule('customer', 'customer-default', 1000, 0, null, 'percentage', 2);
        $engine = app(InsuranceRuleEngineService::class);

        InsuranceSubjectOverride::query()->create([
            'subject_type' => 'customer', 'subject_id' => 17, 'mode' => 'exempt',
            'reason' => 'Approved exception', 'is_active' => true,
        ]);
        $exempt = $engine->resolve('customer', 2000, null, null, 17);
        $this->assertFalse($exempt['applicable']);
        $this->assertSame(0.0, $exempt['amount']);
        $this->assertSame('account-exemption', $exempt['rule_code']);

        InsuranceSubjectOverride::query()->where('subject_id', 17)->update([
            'mode' => 'required', 'calculation_type' => 'fixed', 'calculation_value' => 75,
        ]);
        $required = $engine->resolve('customer', 2000, null, null, 17);
        $this->assertTrue($required['applicable']);
        $this->assertSame(75.0, $required['amount']);
        $this->assertSame('account-required-insurance', $required['rule_code']);
    }

    public function test_selected_scope_does_not_insure_new_customers_until_they_are_included(): void
    {
        DB::table('business_settings')->where('type', 'customer_order_insurance_scope')->update(['value' => 'selected']);
        $customerId = DB::table('users')->insertGetId(['name' => 'New customer', 'email' => 'new@example.test', 'created_at' => now(), 'updated_at' => now()]);
        $service = app(OrderInsuranceService::class);

        $this->assertFalse($service->calculate(2000, $customerId)['applicable']);

        InsuranceSubjectOverride::query()->create([
            'subject_type' => 'customer', 'subject_id' => $customerId,
            'mode' => 'included', 'reason' => 'Admin selection', 'is_active' => true,
        ]);
        $this->assertTrue($service->calculate(2000, $customerId)['applicable']);
        $this->assertSame(40.0, $service->calculate(2000, $customerId)['amount']);

        InsuranceSubjectOverride::query()->where('subject_id', $customerId)->update([
            'mode' => 'required', 'calculation_type' => 'fixed', 'calculation_value' => 75,
        ]);
        $this->assertSame(75.0, $service->calculate(2000, $customerId)['amount']);
    }

    public function test_saving_one_insurance_menu_does_not_change_the_other(): void
    {
        session(['local' => 'en']);
        DB::table('business_settings')->insert([
            'type' => 'seller_order_insurance_status', 'value' => '1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(\App\Http\Controllers\Admin\Insurance\InsuranceConfigurationController::class);
        $controller->updateMaster(\Illuminate\Http\Request::create('/', 'POST', [
            'subject_type' => 'customer', 'customer_scope' => 'selected',
            'customer_maturity_days' => 90, 'customer_payment_deadline_hours' => 72,
            'customer_purchase_refund_delay_days' => 7,
        ]));
        $this->assertDatabaseHas('business_settings', ['type' => 'seller_order_insurance_status', 'value' => '1']);

        $controller->updateMaster(\Illuminate\Http\Request::create('/', 'POST', [
            'subject_type' => 'seller', 'seller_scope' => 'selected',
            'seller_pending_days' => 3, 'seller_reuse_after_days' => 90,
        ]));
        $this->assertDatabaseHas('business_settings', ['type' => 'customer_order_insurance_scope', 'value' => 'selected']);
        $this->assertDatabaseHas('business_settings', ['type' => 'customer_order_insurance_status', 'value' => '0']);
    }

    public function test_created_order_keeps_rule_and_discount_snapshots_after_admin_changes(): void
    {
        $customerId = DB::table('users')->insertGetId(['name' => 'Customer', 'created_at' => now()->subDays(90), 'updated_at' => now()]);
        $rule = $this->rule('customer', 'customer-10-percent', 1, 0, null, 'percentage', 10);
        InsuranceIncentive::query()->create([
            'name' => 'Ten off', 'code' => 'ten-off', 'incentive_type' => 'premium_discount',
            'value_type' => 'fixed', 'value' => 10, 'priority' => 1, 'is_active' => true,
            'new_accounts_only' => false, 'minimum_account_age_days' => 0, 'usage_limit_per_customer' => 1,
        ]);
        $service = app(OrderInsuranceService::class);
        $calculation = $service->calculate(2000, $customerId);
        $insurance = $service->createForOrder(901, $customerId, $calculation, 'offline_payment', 'unpaid');
        $rule->update(['calculation_value' => 50]);

        $this->assertSame(200.0, $insurance->original_amount);
        $this->assertSame(10.0, $insurance->discount_amount);
        $this->assertSame(190.0, $insurance->amount);
        $this->assertSame(10.0, (float) $insurance->rule_snapshot['calculation_value']);
        $this->assertDatabaseHas('insurance_incentive_redemptions', [
            'customer_id' => $customerId, 'order_insurance_id' => $insurance->id,
            'redemption_type' => 'premium_discount', 'amount' => 10,
        ]);
    }

    private function rule(string $subject, string $code, int $priority, float $minimum, ?float $maximum, string $type, float $value): InsuranceRule
    {
        return InsuranceRule::query()->create([
            'subject_type' => $subject, 'name' => $code, 'code' => $code, 'rule_type' => $priority < 1000 ? 'tier' : 'default',
            'priority' => $priority, 'is_active' => true, 'minimum_order_amount' => $minimum,
            'maximum_order_amount' => $maximum, 'minimum_account_age_days' => 0,
            'calculation_type' => $type, 'calculation_value' => $value,
        ]);
    }
}
