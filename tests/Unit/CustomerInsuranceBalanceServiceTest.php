<?php

namespace Tests\Unit;

use App\Models\OrderInsurance;
use App\Models\User;
use App\Services\CustomerInsuranceBalanceService;
use App\Services\OrderInsuranceService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerInsuranceBalanceServiceTest extends TestCase
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
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->decimal('wallet_balance', 24, 3)->default(0);
            $table->timestamps();
        });
        Schema::create('order_insurances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id');
            $table->decimal('amount', 24, 3);
            $table->decimal('order_amount', 24, 3);
            $table->decimal('threshold_amount', 24, 3);
            $table->string('calculation_type');
            $table->decimal('calculation_value', 24, 3);
            $table->unsignedInteger('maturity_days')->default(90);
            $table->string('payment_method');
            $table->string('payment_status');
            $table->string('status');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->text('admin_note')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->dateTime('matures_at')->nullable();
            $table->dateTime('matured_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_insurance_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('order_insurance_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('entry_type', 40);
            $table->decimal('credit', 40, 20)->default(0);
            $table->decimal('debit', 40, 20)->default(0);
            $table->string('reference', 120)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $settings = [
            'customer_order_insurance_status' => '1',
            'customer_order_insurance_threshold' => '1000',
            'customer_order_insurance_calculation_type' => 'fixed',
            'customer_order_insurance_calculation_value' => '75',
            'customer_order_insurance_maturity_days' => '60',
        ];
        foreach ($settings as $type => $value) {
            DB::table('business_settings')->insert([
                'type' => $type,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('customer_insurance_ledger_entries');
        Schema::dropIfExists('order_insurances');
        Schema::dropIfExists('users');
        Schema::dropIfExists('business_settings');
        parent::tearDown();
    }

    public function test_verified_insurance_deposit_is_idempotent_and_does_not_credit_purchase_wallet(): void
    {
        $customerId = DB::table('users')->insertGetId(['wallet_balance' => 120]);
        $payment = ['id' => 'deposit-123', 'payer_id' => $customerId, 'is_paid' => 1,
            'attribute' => 'customer_insurance_deposit', 'payment_amount' => 75,
            'currency_code' => 'USD', 'payment_method' => 'stripe'];
        $service = app(\App\Services\CustomerInsuranceDepositService::class);
        $service->complete($payment);
        $service->complete($payment);
        $this->assertSame(1, DB::table('customer_insurance_ledger_entries')->count());
        $this->assertEquals(75, DB::table('customer_insurance_ledger_entries')->sum('credit'));
        $this->assertEquals(120, DB::table('users')->where('id', $customerId)->value('wallet_balance'));
    }

    public function test_unpaid_or_purchase_payment_cannot_credit_insurance_balance(): void
    {
        $service = app(\App\Services\CustomerInsuranceDepositService::class);
        $service->complete(['is_paid' => 0, 'attribute' => 'customer_insurance_deposit']);
        $service->complete(['is_paid' => 1, 'attribute' => 'add_funds_to_wallet']);
        $this->assertSame(0, DB::table('customer_insurance_ledger_entries')->count());
    }

    public function test_invalid_insurance_deposit_amount_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        app(\App\Services\CustomerInsuranceDepositService::class)->complete([
            'id' => 'invalid-deposit', 'payer_id' => 1, 'is_paid' => 1,
            'attribute' => 'customer_insurance_deposit', 'payment_amount' => -10,
        ]);
    }

    public function test_insurance_applies_only_above_the_admin_threshold(): void
    {
        $service = app(OrderInsuranceService::class);

        $this->assertFalse($service->calculate(1000)['applicable']);
        $calculation = $service->calculate(1000.01);
        $this->assertTrue($calculation['applicable']);
        $this->assertSame(75.0, $calculation['amount']);
        $this->assertSame(60, $calculation['maturityDays']);
    }

    public function test_zero_threshold_means_every_positive_order_and_never_disables_insurance(): void
    {
        DB::table('business_settings')
            ->where('type', 'customer_order_insurance_threshold')
            ->update(['value' => '0']);

        $service = app(OrderInsuranceService::class);

        $this->assertFalse($service->appliesToAmount(0));
        $this->assertTrue($service->appliesToAmount(0.01));
        $this->assertFalse($service->calculate(0)['applicable']);
        $this->assertTrue($service->calculate(0.01)['applicable']);
        $this->assertSame(75.0, $service->calculate(0.01)['amount']);
    }

    public function test_percentage_and_fixed_customer_insurance_keep_the_same_threshold_contract(): void
    {
        $service = app(OrderInsuranceService::class);

        $this->assertSame(75.0, $service->calculate(1000.01)['amount']);

        DB::table('business_settings')
            ->where('type', 'customer_order_insurance_calculation_type')
            ->update(['value' => 'percentage']);
        DB::table('business_settings')
            ->where('type', 'customer_order_insurance_calculation_value')
            ->update(['value' => '10']);

        $this->assertFalse($service->calculate(1000)['applicable']);
        $calculation = $service->calculate(1200);
        $this->assertTrue($calculation['applicable']);
        $this->assertSame(120.0, $calculation['amount']);
    }

    public function test_paid_insurance_remains_held_until_maturity_then_becomes_insurance_only_balance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00'));
        $customer = $this->makeCustomer(500);
        $service = app(OrderInsuranceService::class);
        $insurance = $service->createForOrder(1001, $customer->id, $service->calculate(1500), 'offline_payment', 'paid');
        $balance = app(CustomerInsuranceBalanceService::class);

        $this->assertSame(0, $balance->releaseDue());
        $summary = $balance->summary($customer->id);
        $this->assertSame(['available_balance' => 0.0, 'held_balance' => 75.0], array_intersect_key($summary, ['available_balance' => true, 'held_balance' => true]));
        $this->assertSame(500.0, $customer->fresh()->wallet_balance);
        $this->assertSame(['insurance_payment'], $summary['allowed_uses']);

        $insurance->update(['matures_at' => now()->subSecond()]);

        $this->assertSame(1, $balance->releaseDue());
        $summary = $balance->summary($customer->id);
        $this->assertSame(75.0, $summary['available_balance']);
        $this->assertSame(0.0, $summary['held_balance']);
        $this->assertSame('matured', $insurance->fresh()->status);
        $this->assertSame(500.0, $customer->fresh()->wallet_balance);
        $this->assertDatabaseHas('customer_insurance_ledger_entries', [
            'customer_id' => $customer->id,
            'order_insurance_id' => $insurance->id,
            'entry_type' => 'matured_credit',
            'credit' => 75,
        ]);
    }

    public function test_matured_balance_cannot_pay_products_and_can_pay_insurance_idempotently(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00'));
        $customer = $this->makeCustomer(500);
        $orderInsurance = app(OrderInsuranceService::class);
        $balance = app(CustomerInsuranceBalanceService::class);

        $held = $orderInsurance->createForOrder(1001, $customer->id, $orderInsurance->calculate(1500), 'offline_payment', 'paid');
        $matured = $orderInsurance->createForOrder(1002, $customer->id, $orderInsurance->calculate(1500), 'offline_payment', 'paid');
        $matured->update(['matures_at' => now()->subSecond()]);
        $balance->releaseDue();

        try {
            $balance->reserveForPurchase($customer->id, 75, 'checkout-1003');
            $this->fail('Insurance balance must not pay for products.');
        } catch (DomainException $exception) {
            $this->assertSame('customer_insurance_balance_is_restricted_to_insurance_payment', $exception->getMessage());
        }

        $futureInsurance = $orderInsurance->createForOrder(1003, $customer->id, $orderInsurance->calculate(1500), 'insurance_balance', 'unpaid');
        $this->assertSame(75.0, $balance->debitForInsurance($futureInsurance, 'insurance-payment-1003'));
        $this->assertSame(75.0, $balance->debitForInsurance($futureInsurance, 'insurance-payment-1003'));
        $summary = $balance->summary($customer->id);
        $this->assertSame(0.0, $summary['available_balance']);
        $this->assertSame(75.0, $summary['held_balance']);
        $this->assertSame(500.0, $customer->fresh()->wallet_balance);
        $this->assertSame('held', $held->fresh()->status);
        $this->assertSame('matured', $matured->fresh()->status);
        $this->assertDatabaseCount('customer_insurance_ledger_entries', 4);
    }

    private function makeCustomer(float $walletBalance): User
    {
        return User::query()->create([
            'name' => 'Insurance Test Customer',
            'email' => 'insurance@example.com',
            'phone' => '+201000000000',
            'wallet_balance' => $walletBalance,
        ]);
    }
}
