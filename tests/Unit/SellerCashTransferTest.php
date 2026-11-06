<?php

namespace Tests\Unit;

use App\Models\SellerLedgerEntry;
use App\Services\SellerCashTransferService;
use App\Services\SellerLedgerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerCashTransferTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('sellers', fn (Blueprint $t) => $t->id());
        Schema::create('seller_wallets', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('seller_id'); $t->decimal('total_earning', 20, 2)->default(0); $t->decimal('pending_withdraw', 20, 2)->default(0); $t->decimal('withdrawn', 20, 2)->default(0); $t->timestamps(); });
        Schema::create('business_settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->text('value')->nullable(); $t->timestamps(); });
        (require database_path('migrations/2026_08_08_000001_create_seller_ledger_entries_table.php'))->up();
        DB::table('sellers')->insert(['id' => 1]);
        DB::table('seller_wallets')->insert(['seller_id' => 1, 'total_earning' => 100]);
        DB::table('business_settings')->insert(['type' => SellerCashTransferService::SETTING, 'value' => json_encode(['enabled' => true, 'type' => 'percent', 'value' => 10])]);
        app(SellerLedgerService::class)->post(1, 'test_sales', 'opening-test', [['bucket' => 'available', 'direction' => 'credit', 'amount' => 100]], initializeLegacy: false);
    }

    public function test_transfer_is_atomic_fee_aware_and_idempotent(): void
    {
        $service = app(SellerCashTransferService::class);
        $quote = $service->quote(40);
        $service->transfer(1, 40, 'c7d913a6-7613-4df1-9e0e-6a4c1769754d', $quote);
        $service->transfer(1, 40, 'c7d913a6-7613-4df1-9e0e-6a4c1769754d', $quote);
        $summary = app(SellerLedgerService::class)->summary(1, false);
        $this->assertSame(60.0, $summary['available']);
        $this->assertSame(36.0, $summary['operating']);
        $this->assertSame(60.0, (float) DB::table('seller_wallets')->value('total_earning'));
        $this->assertSame(2, SellerLedgerEntry::where('event_type', 'sales_to_operating_transfer')->count());
        $this->assertSame(4.0, (float) SellerLedgerEntry::where('event_type', 'sales_to_operating_transfer')->first()->metadata['quote']['fee']);
    }

    public function test_changed_fee_and_insufficient_balance_do_not_create_movements(): void
    {
        $service = app(SellerCashTransferService::class);
        $quote = $service->quote(50);
        DB::table('business_settings')->where('type', SellerCashTransferService::SETTING)->update(['value' => json_encode(['enabled' => true, 'type' => 'fixed', 'value' => 2])]);
        try { $service->transfer(1, 50, '67a63cb4-9c6f-453d-9f4a-1b6cf360da5b', $quote); $this->fail('Stale quote accepted'); }
        catch (\DomainException $e) { $this->assertSame('sales_cash_transfer_quote_changed', $e->getMessage()); }
        try { $service->transfer(1, 200, 'c7d913a6-7613-4df1-9e0e-6a4c1769754d', $service->quote(200)); $this->fail('Insufficient balance accepted'); }
        catch (\DomainException $e) { $this->assertSame('sales_cash_transfer_insufficient', $e->getMessage()); }
        $this->assertSame(1, SellerLedgerEntry::count());
        $this->assertSame(100.0, (float) DB::table('seller_wallets')->value('total_earning'));
    }

    public function test_fixed_fee_and_unreconciled_wallet_never_touch_money(): void
    {
        DB::table('business_settings')->where('type', SellerCashTransferService::SETTING)->update(['value' => json_encode(['enabled' => true, 'type' => 'fixed', 'value' => 3])]);
        $service = app(SellerCashTransferService::class);
        $this->assertSame(17.0, $service->quote(20)['net']);
        DB::table('seller_wallets')->where('seller_id', 1)->update(['total_earning' => 5]);
        try { $service->transfer(1, 20, '67a63cb4-9c6f-453d-9f4a-1b6cf360da5b', $service->quote(20)); $this->fail('Unreconciled wallet accepted'); }
        catch (\DomainException $e) { $this->assertSame('sales_cash_transfer_reconciliation_required', $e->getMessage()); }
        $this->assertSame(1, SellerLedgerEntry::count());
        $this->assertSame(5.0, (float) DB::table('seller_wallets')->value('total_earning'));
    }

    public function test_reused_request_key_cannot_change_amount_or_fee(): void
    {
        $service = app(SellerCashTransferService::class);
        $quote = $service->quote(40);
        $key = 'c7d913a6-7613-4df1-9e0e-6a4c1769754d';
        $service->transfer(1, 40, $key, $quote);
        try { $service->transfer(1, 30, $key, $service->quote(30)); $this->fail('Changed amount accepted'); }
        catch (\DomainException $e) { $this->assertSame('sales_cash_transfer_retry_mismatch', $e->getMessage()); }
        $quote['fee'] = 1;
        try { $service->transfer(1, 40, $key, $quote); $this->fail('Changed quote accepted'); }
        catch (\DomainException $e) { $this->assertSame('sales_cash_transfer_retry_mismatch', $e->getMessage()); }
        $this->assertSame(3, SellerLedgerEntry::count());
    }

    public function test_ambiguous_fee_configuration_disables_transfers(): void
    {
        DB::table('business_settings')->insert(['type' => SellerCashTransferService::SETTING, 'value' => json_encode(['enabled' => true, 'type' => 'fixed', 'value' => 1])]);
        $service = app(SellerCashTransferService::class);
        $this->assertFalse($service->settings()['enabled']);
        try { $service->quote(20); $this->fail('Ambiguous fee configuration accepted'); }
        catch (\DomainException $e) { $this->assertSame('sales_cash_transfer_unavailable', $e->getMessage()); }
        $this->assertSame(1, SellerLedgerEntry::count());
    }

    public function test_fee_is_rounded_to_visible_currency_and_replay_does_not_revalidate_exchange_rate(): void
    {
        $service = app(SellerCashTransferService::class);
        DB::table('business_settings')->where('type', SellerCashTransferService::SETTING)->update(['value' => json_encode(['enabled' => true, 'type' => 'percent', 'value' => 3])]);
        $quote = $service->quote(10.17);
        $this->assertSame(0.31, $quote['fee']);
        $this->assertSame(9.86, $quote['net']);
        $key = 'c7d913a6-7613-4df1-9e0e-6a4c1769754d';
        $service->transfer(1, 10.17, $key, $quote, fn () => null);
        $again = $service->transfer(1, 10.17, $key, $quote, fn () => throw new \RuntimeException('rate changed'));
        $this->assertEquals($quote, $again);
        $this->assertSame(89.83, app(SellerLedgerService::class)->summary(1, false)['available']);
    }

    public function test_fee_and_net_sum_to_displayed_amount_in_multi_currency_mode(): void
    {
        Schema::create('currencies', function (Blueprint $table) { $table->id(); $table->string('code'); $table->decimal('exchange_rate', 12, 4); });
        DB::table('currencies')->insert([['id' => 1, 'code' => 'EGP', 'exchange_rate' => 50], ['id' => 2, 'code' => 'USD', 'exchange_rate' => 1]]);
        DB::table('business_settings')->insert([['type' => 'currency_model', 'value' => 'multi_currency'], ['type' => 'system_default_currency', 'value' => '1']]);
        DB::table('business_settings')->where('type', SellerCashTransferService::SETTING)->update(['value' => json_encode(['enabled' => true, 'type' => 'percent', 'value' => 3])]);
        \Illuminate\Support\Facades\Cache::flush();
        $quote = app(SellerCashTransferService::class)->quote((float) \App\Utils\Convert::usd(10.17));
        $this->assertSame(10.17, \App\Utils\Convert::default($quote['amount']));
        $this->assertSame(0.31, \App\Utils\Convert::default($quote['fee']));
        $this->assertSame(9.86, \App\Utils\Convert::default($quote['net']));
        Schema::dropIfExists('currencies');
        \Illuminate\Support\Facades\Cache::flush();
    }
}
