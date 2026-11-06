<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Models\SellerBalanceDeposit;
use App\Models\SellerLedgerEntry;
use App\Services\SellerBalanceDepositService;
use App\Services\SellerLedgerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerBalanceDepositServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('sellers', function (Blueprint $table) { $table->id(); $table->string('email')->nullable(); $table->timestamps(); });
        Schema::create('seller_wallets', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('seller_id'); $table->decimal('total_earning', 40, 20)->default(0); $table->decimal('pending_withdraw', 40, 20)->default(0); $table->decimal('withdrawn', 40, 20)->default(0); $table->timestamps(); });
        Schema::create('seller_ledger_entries', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('seller_id'); $table->string('bucket'); $table->string('direction'); $table->decimal('amount', 40, 20); $table->string('event_type'); $table->string('group_key'); $table->char('idempotency_key', 64)->unique(); $table->string('reference_type')->nullable(); $table->unsignedBigInteger('reference_id')->nullable(); $table->json('metadata')->nullable(); $table->char('previous_hash', 64)->nullable(); $table->char('entry_hash', 64); $table->unsignedBigInteger('created_by')->nullable(); $table->timestamps(); });
        Schema::create('seller_balance_deposits', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('seller_id'); $table->decimal('amount', 40, 20); $table->string('currency_code')->default('USD'); $table->string('source'); $table->string('status'); $table->char('payment_request_id', 36)->nullable(); $table->string('payment_method')->nullable(); $table->string('transaction_reference')->nullable(); $table->json('offline_information')->nullable(); $table->json('payment_proof')->nullable(); $table->text('payment_note')->nullable(); $table->text('review_note')->nullable(); $table->text('rejection_reason')->nullable(); $table->json('metadata')->nullable(); $table->unsignedBigInteger('reviewed_by_admin_id')->nullable(); $table->timestamp('reviewed_at')->nullable(); $table->timestamp('paid_at')->nullable(); $table->timestamp('reversed_at')->nullable(); $table->unsignedBigInteger('reversed_by_admin_id')->nullable(); $table->string('idempotency_key')->unique(); $table->timestamps(); });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('seller_balance_deposits'); Schema::dropIfExists('seller_ledger_entries'); Schema::dropIfExists('seller_wallets'); Schema::dropIfExists('sellers'); parent::tearDown();
    }

    public function test_payment_callback_is_idempotent_and_reversal_is_a_separate_ledger_event(): void
    {
        $seller = Seller::create(['email' => 'seller@example.com']);
        $deposit = app(SellerBalanceDepositService::class)->createOffline($seller, ['amount' => 125, 'currency_code' => 'USD']);
        $service = app(SellerBalanceDepositService::class);
        $service->markPaid($deposit, ['transaction_id' => 'gateway-1']);
        $service->markPaid($deposit->fresh(), ['transaction_id' => 'gateway-1']);
        $this->assertSame(125.0, app(SellerLedgerService::class)->summary($seller->id)[SellerLedgerService::OPERATING]);
        $this->assertSame(1, SellerLedgerEntry::where('seller_id', $seller->id)->where('event_type', 'operating_deposit')->count());

        $service->reverse($deposit->fresh(), 7, 'Returned by administration');
        $this->assertSame(0.0, app(SellerLedgerService::class)->summary($seller->id)[SellerLedgerService::OPERATING]);
        $this->expectException(\DomainException::class);
        $service->reverse($deposit->fresh(), 7, 'Duplicate reversal');
    }

    public function test_rejection_retries_preserve_first_review_and_never_create_credit(): void
    {
        $seller = Seller::create(['email' => 'rejection@example.test']);
        $service = app(SellerBalanceDepositService::class);
        $deposit = $service->createOffline($seller, ['amount' => 125, 'currency_code' => 'EGP']);
        $service->reject($deposit, 7, 'Transfer not received');
        $first = $deposit->fresh()->getAttributes();
        $service->reject($deposit, 8, 'Repeated request');
        $this->assertSame($first, $deposit->fresh()->getAttributes());
        $this->assertSame(0, SellerLedgerEntry::count());
        $this->expectException(\DomainException::class);
        $service->markPaid($deposit, ['transaction_id' => 'stale-approval']);
    }

    public function test_stale_rejection_cannot_override_a_paid_deposit(): void
    {
        $seller = Seller::create(['email' => 'paid-before-reject@example.test']);
        $service = app(SellerBalanceDepositService::class);
        $deposit = $service->createOffline($seller, ['amount' => 125, 'currency_code' => 'EGP']);
        $service->markPaid($deposit, ['transaction_id' => 'confirmed-transfer']);
        try { $service->reject($deposit, 7, 'Stale rejection'); $this->fail(); }
        catch (\DomainException $e) { $this->assertSame('seller_balance_deposit_cannot_be_rejected', $e->getMessage()); }
        $this->assertSame('paid', $deposit->fresh()->status);
        $this->assertSame(125.0, app(SellerLedgerService::class)->summary($seller->id)['operating']);
    }

    public function test_offline_review_notifies_seller_once_for_approval_and_rejection(): void
    {
        Schema::create('business_settings', fn (Blueprint $t) => [$t->id(), $t->string('type'), $t->text('value')]);
        \Illuminate\Support\Facades\DB::table('business_settings')->insert([['type'=>'currency_model','value'=>'single_currency'],['type'=>'system_default_currency','value'=>'1']]);
        Schema::create('currencies', fn (Blueprint $t) => [$t->id(), $t->string('code')]);
        \Illuminate\Support\Facades\DB::table('currencies')->insert(['id'=>1,'code'=>'EGP']);
        Schema::create('notifications', function (Blueprint $table) {
            $table->id(); $table->string('sent_by'); $table->string('sent_to'); $table->unsignedBigInteger('seller_id');
            $table->string('customer_event_key')->unique(); $table->string('image')->nullable();
            $table->string('title'); $table->text('description'); $table->integer('notification_count'); $table->boolean('status'); $table->timestamps();
        });
        $seller = Seller::create(['email' => 'notifications@example.test']);
        $service = app(SellerBalanceDepositService::class);
        $approved = $service->createOffline($seller, ['amount' => 125, 'currency_code' => 'EGP']);
        $service->markPaid($approved, ['transaction_id' => 'reference-one']);
        $service->markPaid($approved, ['transaction_id' => 'reference-one']);
        $rejected = $service->createOffline($seller, ['amount' => 50, 'currency_code' => 'EGP']);
        $service->reject($rejected, 7, 'Not received');
        $service->reject($rejected, 7, 'Not received');
        // Current production sends pending acknowledgements as well as final review decisions.
        $this->assertSame(4, \Illuminate\Support\Facades\DB::table('notifications')->where('seller_id', $seller->id)->count());
        $this->assertSame(125.0, app(SellerLedgerService::class)->summary($seller->id)['operating']);
    }

    public function test_historical_insurance_payment_moves_once_to_operating_and_cannot_be_reversed_as_insurance(): void
    {
        $seller = Seller::create(['email' => 'insurance-seller@example.com']);
        $service = app(SellerBalanceDepositService::class);
        $deposit = SellerBalanceDeposit::create([
            'seller_id' => $seller->id, 'source' => 'offline', 'status' => 'pending', 'idempotency_key' => 'historical-1',
            'amount' => 300,
            'currency_code' => 'USD',
            'metadata' => ['wallet_target' => 'insurance'],
        ]);

        $service->markPaid($deposit);
        $summary = app(SellerLedgerService::class)->summary($seller->id);
        $this->assertSame(0.0, $summary[SellerLedgerService::ORDER_INSURANCE_CREDIT]);
        $this->assertSame(300.0, $summary[SellerLedgerService::OPERATING]);
        $service->markPaid($deposit->fresh());
        $summary = app(SellerLedgerService::class)->summary($seller->id);
        $this->assertSame(0.0, $summary[SellerLedgerService::ORDER_INSURANCE_CREDIT]);
        $this->assertSame(1, SellerLedgerEntry::where('event_type', 'seller_insurance_credit_deposit')->count());
        $this->assertSame(2, SellerLedgerEntry::where('event_type', 'vendor_insurance_retirement_to_operating')->count());
        $this->assertSame('insurance', data_get($deposit->fresh()->metadata, 'wallet_target'));
        $this->assertSame('operating', data_get($deposit->fresh()->metadata, 'insurance_retirement.destination'));
        $this->expectException(\DomainException::class);
        $service->reverse($deposit->fresh(), 7, 'Must use a separately approved operating adjustment');
    }

    public function test_new_insurance_funding_is_rejected_without_creating_a_request(): void
    {
        $seller = Seller::create(['email' => 'retired@example.com']);
        foreach (['offline', 'digital'] as $type) {
            try {
                $service = app(SellerBalanceDepositService::class);
                $type === 'offline' ? $service->createOffline($seller, ['amount' => 100, 'wallet_target' => 'insurance'])
                    : $service->createDigital($seller, 100, 'EGP', 'gateway', 'insurance');
                $this->fail('Retired insurance funding was accepted');
            } catch (\DomainException $exception) {
                $this->assertSame('seller_insurance_retired', $exception->getMessage());
            }
        }
        $this->assertSame(0, SellerBalanceDeposit::count());
        $this->assertSame(0, SellerLedgerEntry::count());
    }

    public function test_paid_source_settlement_preserves_funding_and_is_idempotent(): void
    {
        $seller = Seller::create(['email' => 'paid@example.com']);
        $deposit = SellerBalanceDeposit::create(['seller_id' => $seller->id, 'amount' => 1000, 'currency_code' => 'EGP',
            'source' => 'offline', 'status' => 'paid', 'idempotency_key' => 'historical-paid', 'metadata' => ['wallet_target' => 'insurance']]);
        $funding = app(SellerLedgerService::class)->post($seller->id, 'seller_insurance_credit_deposit', 'seller-balance-deposit:'.$deposit->id.':credit',
            [['bucket' => SellerLedgerService::ORDER_INSURANCE_CREDIT, 'direction' => 'credit', 'amount' => 1000]], SellerBalanceDeposit::class, $deposit->id)->first();
        $original = $funding->fresh()->getAttributes();
        $service = app(\App\Services\SellerInsuranceRetirementService::class);
        $service->settle($deposit, 'Explicit owner approval');
        $service->settle($deposit->fresh(), 'Explicit owner approval');
        $this->assertSame($original, $funding->fresh()->getAttributes());
        $this->assertSame(3, SellerLedgerEntry::count());
        $summary = app(SellerLedgerService::class)->summary($seller->id);
        $this->assertSame(1000.0, $summary[SellerLedgerService::OPERATING]);
        $this->assertSame(0.0, $summary[SellerLedgerService::ORDER_INSURANCE_CREDIT]);
        $this->assertSame(0.0, $summary[SellerLedgerService::AVAILABLE]);
    }

    public function test_unfunded_paid_source_fails_without_creating_money(): void
    {
        $seller = Seller::create(['email' => 'unfunded@example.com']);
        $deposit = SellerBalanceDeposit::create(['seller_id' => $seller->id, 'amount' => 1000, 'currency_code' => 'EGP',
            'source' => 'offline', 'status' => 'paid', 'idempotency_key' => 'unfunded-paid', 'metadata' => ['wallet_target' => 'insurance']]);
        try {
            app(\App\Services\SellerInsuranceRetirementService::class)->settle($deposit, 'Explicit approval');
            $this->fail('Unfunded source credited money');
        } catch (\DomainException $exception) {
            $this->assertSame('seller_insurance_retirement_funding_mismatch', $exception->getMessage());
        }
        $this->assertSame(0, SellerLedgerEntry::count());
        $this->assertNull(data_get($deposit->fresh()->metadata, 'insurance_retirement'));
    }

    public function test_command_preview_is_read_only_and_apply_retries_do_not_duplicate_money(): void
    {
        $seller = Seller::create(['email' => 'command@example.com']);
        $deposit = SellerBalanceDeposit::create(['seller_id' => $seller->id, 'amount' => 1000, 'currency_code' => 'EGP',
            'source' => 'offline', 'status' => 'pending', 'idempotency_key' => 'command-pending', 'metadata' => ['wallet_target' => 'insurance']]);
        $path = tempnam(sys_get_temp_dir(), 'vendor-retirement-test-');
        try {
            file_put_contents($path, json_encode(['destination' => 'operating', 'authorization' => 'Owner confirms receipt',
                'deposits' => [['id' => $deposit->id, 'seller_id' => $seller->id, 'amount' => 1000, 'currency_code' => 'EGP',
                    'expected_status' => 'pending', 'receipt_confirmed' => true]]]));
            $this->artisan('vendor:retire-insurance', ['manifest' => $path])->assertSuccessful();
            $this->assertSame(0, SellerLedgerEntry::count());
            $this->assertSame('pending', $deposit->fresh()->status);
            $this->artisan('vendor:retire-insurance', ['manifest' => $path, '--apply' => true])->assertSuccessful();
            $this->artisan('vendor:retire-insurance', ['manifest' => $path, '--apply' => true])->assertSuccessful();
            $this->assertSame(3, SellerLedgerEntry::count());
            $this->assertSame(1000.0, app(SellerLedgerService::class)->summary($seller->id)[SellerLedgerService::OPERATING]);
        } finally { unlink($path); }
    }

    public function test_command_rolls_back_first_settlement_when_second_source_changes(): void
    {
        $seller = Seller::create(['email' => 'atomic@example.com']);
        $items = [];
        foreach ([1, 2] as $index) {
            $deposit = SellerBalanceDeposit::create(['seller_id' => $seller->id, 'amount' => 1000, 'currency_code' => 'EGP',
                'source' => 'offline', 'status' => 'pending', 'idempotency_key' => 'atomic-'.$index, 'metadata' => ['wallet_target' => 'insurance']]);
            $items[] = ['id' => $deposit->id, 'seller_id' => $seller->id, 'amount' => $index === 1 ? 1000 : 999,
                'currency_code' => 'EGP', 'expected_status' => 'pending', 'receipt_confirmed' => true];
        }
        $path = tempnam(sys_get_temp_dir(), 'vendor-retirement-test-');
        try {
            file_put_contents($path, json_encode(['destination' => 'operating', 'authorization' => 'Owner confirms receipt', 'deposits' => $items]));
            try {
                \Illuminate\Support\Facades\Artisan::call('vendor:retire-insurance', ['manifest' => $path, '--apply' => true]);
                $this->fail('Changed second source accepted');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('Settlement precondition changed', $exception->getMessage());
            }
            $this->assertSame(0, SellerLedgerEntry::count());
            $this->assertSame(2, SellerBalanceDeposit::where('status', 'pending')->count());
        } finally { unlink($path); }
    }
}
