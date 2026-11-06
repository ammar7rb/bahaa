<?php

namespace Tests\Unit;

use App\Models\SellerLedgerEntry;
use App\Models\SellerWallet;
use App\Services\SellerLedgerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class SellerLedgerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
        Schema::create('seller_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->decimal('total_earning', 40, 20)->default(0);
            $table->decimal('pending_withdraw', 40, 20)->default(0);
            $table->decimal('withdrawn', 40, 20)->default(0);
            $table->decimal('commission_given', 40, 20)->default(0);
            $table->decimal('delivery_charge_earned', 40, 20)->default(0);
            $table->decimal('collected_cash', 40, 20)->default(0);
            $table->decimal('total_tax_collected', 40, 20)->default(0);
            $table->timestamps();
        });
        Schema::create('seller_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('bucket', 32);
            $table->string('direction', 8);
            $table->decimal('amount', 40, 20);
            $table->string('event_type', 64);
            $table->string('group_key', 191);
            $table->char('idempotency_key', 64)->unique();
            $table->string('reference_type', 128)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('metadata')->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('entry_hash', 64);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('seller_ledger_entries');
        Schema::dropIfExists('seller_wallets');
        Schema::dropIfExists('sellers');
        parent::tearDown();
    }

    public function test_post_is_idempotent_and_summary_keeps_buckets_separate(): void
    {
        $service = app(SellerLedgerService::class);

        $service->post(
            sellerId: 7,
            eventType: 'operating_deposit',
            groupKey: 'deposit:abc',
            movements: [
                ['bucket' => SellerLedgerService::OPERATING, 'direction' => 'credit', 'amount' => 100],
            ],
        );
        $service->post(
            sellerId: 7,
            eventType: 'operating_deposit',
            groupKey: 'deposit:abc',
            movements: [
                ['bucket' => SellerLedgerService::OPERATING, 'direction' => 'credit', 'amount' => 100],
            ],
        );

        $summary = $service->summary(7);
        $this->assertSame(100.0, $summary[SellerLedgerService::OPERATING]);
        $this->assertSame(0.0, $summary[SellerLedgerService::AVAILABLE]);
        $this->assertSame(1, SellerLedgerEntry::where('seller_id', 7)->count());
    }

    public function test_legacy_wallet_updates_are_captured_without_mixing_operating_balance(): void
    {
        $seller = \App\Models\Seller::create();
        $wallet = SellerWallet::create(['seller_id' => $seller->id]);
        $wallet->total_earning = 125;
        $wallet->save();

        $summary = app(SellerLedgerService::class)->summary($seller->id);
        $this->assertSame(125.0, $summary[SellerLedgerService::SALES_TOTAL]);
        $this->assertSame(125.0, $summary[SellerLedgerService::AVAILABLE]);
        $this->assertSame(0.0, $summary[SellerLedgerService::OPERATING]);
    }

    public function test_entries_cannot_be_updated_or_deleted(): void
    {
        $entry = app(SellerLedgerService::class)->post(
            sellerId: 9,
            eventType: 'sale',
            groupKey: 'order:1',
            movements: [
                ['bucket' => SellerLedgerService::PENDING, 'direction' => 'credit', 'amount' => 10],
            ],
        )->first();

        $this->expectException(LogicException::class);
        $entry->delete();
    }
}
