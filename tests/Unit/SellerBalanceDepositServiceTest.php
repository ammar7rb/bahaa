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

    public function test_reduced_penalty_refund_is_reversed_without_double_credit(): void
    {
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_06_000002_create_vendor_debt_ledger.php')->up();
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_09_120000_create_vendor_penalty_refunds.php')->up();
        $seller = Seller::create(['email'=>'refund-test@example.test']);
        $debt = app(\App\Services\VendorDebtService::class);
        $charge = $debt->assess($seller->id, 'cancellation_penalty', 'test', '1', 'test-charge', 100, ['reason'=>'test'], 9);
        $service = app(SellerBalanceDepositService::class);
        $deposit = $service->confirmAdministrativePayment($seller, '70', 'qa-refund-bank', 'qa-refund', 9, 'Test');
        $this->assertEquals(30, $debt->outstanding($seller->id));
        $debt->reduceUnpaidCharge($seller->id, $charge->id, 50, 9, 'Admin review');
        $this->assertEquals(0, $debt->outstanding($seller->id));
        $this->assertEquals(20, app(SellerLedgerService::class)->summary($seller->id)[SellerLedgerService::OPERATING]);
        $this->assertEquals(20, \Illuminate\Support\Facades\DB::table('vendor_penalty_refunds')->sum('amount'));
        try { $debt->reduceUnpaidCharge($seller->id, $charge->id, 50, 9, 'Retry'); $this->fail('Repeated refund'); }
        catch (\DomainException $e) { $this->assertStringContainsString('مسبقًا', $e->getMessage()); }
        $ledger = app(SellerLedgerService::class);
        $ledger->post($seller->id, 'qa_spend', 'qa-refund-spend', [['bucket'=>SellerLedgerService::OPERATING,'direction'=>'debit','amount'=>20]], 'qa', 1);
        try { $service->reverse($deposit->fresh(), 9, 'Insufficient balance'); $this->fail('Reversal without funds accepted'); }
        catch (\Throwable $e) { $this->assertNotSame('Reversal without funds accepted', $e->getMessage()); }
        $this->assertEquals(0, $debt->outstanding($seller->id));
        $this->assertNull(\Illuminate\Support\Facades\DB::table('vendor_deposit_allocations')->where('deposit_id', $deposit->id)->value('reversed_at'));
        $ledger->post($seller->id, 'qa_credit', 'qa-refund-replenish', [['bucket'=>SellerLedgerService::OPERATING,'direction'=>'credit','amount'=>20]], 'qa', 1);
        $service->reverse($deposit->fresh(), 9, 'Test reversal');
        $this->assertEquals(50, $debt->outstanding($seller->id));
        $this->assertEquals(0, app(SellerLedgerService::class)->summary($seller->id)[SellerLedgerService::OPERATING]);
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

    public function test_verified_admin_funding_is_once_only_and_rejects_reused_reference(): void
    {
        $seller = Seller::create(['email' => 'admin-fund@example.com']);
        $service = app(SellerBalanceDepositService::class);
        $first = $service->confirmAdministrativePayment($seller, '75.00', 'verified-transfer-1', 'request-1', 9, 'Verified bank receipt');
        $again = $service->confirmAdministrativePayment($seller, '75.00', 'verified-transfer-1', 'request-1', 9, 'Verified bank receipt');
        $this->assertSame($first->id, $again->id);
        $this->assertSame(75.0, app(SellerLedgerService::class)->summary($seller->id)[SellerLedgerService::OPERATING]);
        $this->assertSame(1, SellerLedgerEntry::where('seller_id', $seller->id)->where('event_type', 'operating_deposit')->count());
        $this->expectException(\DomainException::class);
        $service->confirmAdministrativePayment($seller, '75.00', 'verified-transfer-1', 'request-2', 9, 'Another receipt');
    }

    public function test_verified_funding_settles_oldest_administrative_debt_before_wallet_credit(): void
    {
        Schema::create('vendor_debt_charges', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->string('kind'); $t->string('source_type'); $t->string('source_id'); $t->char('idempotency_key',64)->unique(); $t->char('fingerprint',64); $t->decimal('amount',24,8); $t->decimal('wallet_collected',24,8); $t->decimal('debt_assessed',24,8); $t->decimal('outstanding',24,8); $t->json('rule_snapshot'); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamp('assessed_at'); $t->timestamps(); });
        Schema::create('vendor_deposit_allocations', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('deposit_id')->unique(); $t->unsignedBigInteger('seller_id'); $t->decimal('gross_amount',24,8); $t->decimal('debt_recovered',24,8); $t->decimal('net_wallet_credit',24,8); $t->decimal('remaining_debt',24,8); $t->timestamp('confirmed_at'); $t->timestamp('reversed_at')->nullable(); $t->timestamps(); });
        Schema::create('vendor_debt_repayments', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('charge_id'); $t->unsignedBigInteger('deposit_id')->nullable(); $t->string('collection_type'); $t->decimal('amount',24,8); $t->char('event_key',64)->unique(); $t->timestamp('reversed_at')->nullable(); $t->timestamps(); });
        Schema::create('vendor_debt_events', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('charge_id')->nullable(); $t->unsignedBigInteger('deposit_id')->nullable(); $t->string('event_type'); $t->decimal('amount',24,8); $t->char('event_key',64)->unique(); $t->timestamp('created_at'); });
        try {
            $seller = Seller::create(['email' => 'debt-seller@example.com']);
            $debt = app(\App\Services\VendorDebtService::class);
            $old = $debt->createAdministrativeDebt($seller->id, '60', 'Old charge', 'debt-1', 9);
            $new = $debt->createAdministrativeDebt($seller->id, '50', 'New charge', 'debt-2', 9);
            $service = app(SellerBalanceDepositService::class);
            $first = $service->confirmAdministrativePayment($seller, '80', 'bank-80', 'fund-1', 9, 'Verified');
            $receipt = $debt->depositReceipt($first->id);
            $this->assertSame(80.0, (float) $receipt['debt_recovered']);
            $this->assertSame(0.0, app(SellerLedgerService::class)->summary($seller->id)[SellerLedgerService::OPERATING]);
            $this->assertSame(0.0, (float) \Illuminate\Support\Facades\DB::table('vendor_debt_charges')->find($old->id)->outstanding);
            $this->assertSame(30.0, (float) \Illuminate\Support\Facades\DB::table('vendor_debt_charges')->find($new->id)->outstanding);
            $second = $service->confirmAdministrativePayment($seller, '50', 'bank-50', 'fund-2', 9, 'Verified');
            $this->assertSame(30.0, (float) $debt->depositReceipt($second->id)['debt_recovered']);
            $this->assertSame(20.0, app(SellerLedgerService::class)->summary($seller->id)[SellerLedgerService::OPERATING]);
        } finally {
            foreach (['vendor_debt_events','vendor_debt_repayments','vendor_deposit_allocations','vendor_debt_charges'] as $table) Schema::dropIfExists($table);
        }
    }

    public function test_insurance_deposit_credits_and_reverses_only_the_insurance_wallet(): void
    {
        $seller = Seller::create(['email' => 'insurance-seller@example.com']);
        $service = app(SellerBalanceDepositService::class);
        $deposit = $service->createOffline($seller, [
            'amount' => 300,
            'currency_code' => 'USD',
            'wallet_target' => 'insurance',
        ]);

        $service->markPaid($deposit);
        $summary = app(SellerLedgerService::class)->summary($seller->id);
        $this->assertSame(300.0, $summary[SellerLedgerService::ORDER_INSURANCE_CREDIT]);
        $this->assertSame(0.0, $summary[SellerLedgerService::OPERATING]);

        $service->reverse($deposit->fresh(), 7, 'Insurance deposit reversed');
        $summary = app(SellerLedgerService::class)->summary($seller->id);
        $this->assertSame(0.0, $summary[SellerLedgerService::ORDER_INSURANCE_CREDIT]);
        $this->assertSame(1, SellerLedgerEntry::where('event_type', 'seller_insurance_credit_deposit')->count());
        $this->assertSame(1, SellerLedgerEntry::where('event_type', 'seller_insurance_credit_deposit_reversal')->count());
    }
}
