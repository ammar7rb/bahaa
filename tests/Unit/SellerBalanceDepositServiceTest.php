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
