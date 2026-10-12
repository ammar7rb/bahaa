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
            'customer_order_insurance_scope' => 'all',
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

    public function test_old_enabled_setting_cannot_enable_customer_insurance(): void
    {
        $this->assertFalse(app(OrderInsuranceService::class)->isEnabled());
        $this->assertSame(0, app(CustomerInsuranceBalanceService::class)->releaseDue());
    }

    public function test_retired_balance_cannot_be_debited_or_credited_or_held(): void
    {
        $service = app(CustomerInsuranceBalanceService::class);
        $actions = [
            fn () => $service->debitForInsurance(new OrderInsurance(), 'old-debit'),
            fn () => $service->debitForGovernance(1, 50, 'old-admin-debit'),
            fn () => $service->creditFromGovernance(1, 50, 'old-admin-credit'),
            fn () => $service->recordHold(new OrderInsurance()),
            fn () => $service->reserveForPurchase(1, 50, 'old-product'),
        ];
        foreach ($actions as $action) {
            try { $action(); $this->fail('Retired insurance mutation was accepted'); }
            catch (DomainException $exception) { $this->assertNotEmpty($exception->getMessage()); }
        }
        $this->assertSame(0, DB::table('customer_insurance_ledger_entries')->count());
    }

    public function test_historical_reversal_remains_idempotent_without_touching_purchase_funds(): void
    {
        DB::table('users')->insert(['id' => 1, 'wallet_balance' => 120]);
        DB::table('customer_insurance_ledger_entries')->insert([
            'customer_id' => 1, 'entry_type' => 'purchase_debit', 'credit' => 0,
            'debit' => 50, 'reference' => 'historical-product-debit',
        ]);
        $service = app(CustomerInsuranceBalanceService::class);
        $service->reversePurchaseDebit(1, 'historical-product-debit', 'refund');
        $service->reversePurchaseDebit(1, 'historical-product-debit', 'refund');
        $this->assertSame(2, DB::table('customer_insurance_ledger_entries')->count());
        $this->assertEquals(50, DB::table('customer_insurance_ledger_entries')->sum('credit'));
        $this->assertEquals(120, DB::table('users')->value('wallet_balance'));
    }
}