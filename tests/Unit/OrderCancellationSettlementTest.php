<?php

namespace Tests\Unit;

use App\Services\OrderCancellationSettlementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderCancellationSettlementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->decimal('wallet_balance', 24, 3)->default(0); $table->timestamps(); });
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->boolean('is_guest')->default(false);
            $table->string('payment_status'); $table->string('order_status');
            $table->string('customer_cancel_request_status')->nullable();
            $table->string('commerce_flow_status')->nullable();
            $table->timestamp('commerce_flow_status_updated_at')->nullable();
            $table->decimal('order_amount', 24, 3); $table->decimal('init_order_amount', 24, 3); $table->timestamps();
        });
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('transaction_id')->unique();
            $table->string('reference'); $table->string('transaction_type'); $table->string('payment_method');
            foreach (['credit', 'debit', 'admin_bonus', 'balance'] as $column) $table->decimal($column, 24, 3)->default(0);
            $table->timestamps();
        });
        foreach (['order_admin_purchase_refunds', 'order_refund_ledgers', 'refund_requests'] as $name) {
            Schema::create($name, function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('order_id'); });
        }
        Schema::create('order_work_items', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('order_id'); $table->unsignedBigInteger('order_detail_id');
            $table->unsignedBigInteger('seller_id'); $table->string('status'); $table->timestamps();
        });
        Schema::create('order_work_item_events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('work_item_id'); $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_detail_id'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type'); $table->string('event_key'); $table->string('status_before')->nullable();
            $table->string('status_after')->nullable(); $table->json('snapshot')->nullable(); $table->timestamp('created_at');
        });
        Schema::create('order_logistics_events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('order_id'); $table->string('order_status');
            $table->string('actor_type'); $table->unsignedBigInteger('actor_id'); $table->timestamps();
        });
        Schema::create('sellers', function (Blueprint $table) { $table->id(); $table->timestamps(); });
        Schema::create('seller_wallets', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seller_id');
            foreach (['total_earning', 'pending_withdraw', 'withdrawn'] as $column) $table->decimal($column, 24, 3)->default(0);
            $table->timestamps();
        });
        (require base_path('database/migrations/2026_08_08_000001_create_seller_ledger_entries_table.php'))->up();
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_06_000002_create_vendor_debt_ledger.php')->up();
        (require base_path('database/migrations/2026_11_08_130000_create_order_cancellation_settlements.php'))->up();
        DB::table('users')->insert(['id' => 1, 'wallet_balance' => 0]);
        DB::table('orders')->insert(['id' => 10, 'customer_id' => 1, 'is_guest' => 0, 'payment_status' => 'paid',
            'order_status' => 'canceled', 'order_amount' => 100, 'init_order_amount' => 100, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('order_logistics_events')->insert(['order_id' => 10, 'order_status' => 'canceled',
            'actor_type' => 'customer', 'actor_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_refund_is_atomic_capped_and_idempotent(): void
    {
        DB::table('order_work_items')->insert(['id'=>4, 'order_id'=>10, 'order_detail_id'=>3,
            'seller_id'=>7, 'status'=>'canceled', 'created_at'=>now(), 'updated_at'=>now()]);
        $service = app(OrderCancellationSettlementService::class);
        $row = $service->refundCustomer(10, 80, 15, 5, now()->addMonth(), 'طلب العميل الإلغاء', 7);
        $this->assertEquals(80, $row->refund_amount);
        $this->assertEquals(80, DB::table('users')->where('id', 1)->value('wallet_balance'));
        $this->assertSame(1, DB::table('wallet_transactions')->count());
        $snapshot = json_decode(DB::table('order_work_item_events')->where('event_key', 'customer_cancellation_refunded')->value('snapshot'), true);
        $this->assertEquals(0, $snapshot['financial_before']['customer_wallet']);
        $this->assertEquals(80, $snapshot['financial_after']['customer_wallet']);
        try { $service->refundCustomer(10, 80, 15, 5, now()->addMonth(), 'تكرار', 7); $this->fail('Duplicate refund accepted'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('cancellation', $exception->errors()); }
        $this->assertSame(1, DB::table('wallet_transactions')->count());
    }

    public function test_existing_refund_route_blocks_a_second_credit(): void
    {
        DB::table('order_refund_ledgers')->insert(['order_id' => 10]);
        $this->expectException(ValidationException::class);
        app(OrderCancellationSettlementService::class)->refundCustomer(10, 80, 15, 5, now()->addMonth(), 'طلب العميل الإلغاء', 7);
    }

    public function test_admin_cancellation_cannot_be_recorded_as_customer_refund(): void
    {
        DB::table('order_logistics_events')->where('order_id', 10)->update(['actor_type' => 'admin', 'actor_id' => 7]);
        $this->expectException(ValidationException::class);
        app(OrderCancellationSettlementService::class)->refundCustomer(10, 80, 15, 5, now()->addMonth(), 'طلب العميل الإلغاء', 7);
    }

    public function test_unpaid_penalty_reduction_preserves_wallet_and_is_not_repeated(): void
    {
        DB::table('sellers')->insert(['id' => 7]);
        DB::table('seller_wallets')->insert(['seller_id' => 7, 'total_earning' => 0]);
        $ledger = app(\App\Services\SellerLedgerService::class);
        $ledger->post(7, 'qa_credit', 'qa-reduction-credit', [['bucket'=>\App\Services\SellerLedgerService::OPERATING, 'direction'=>'credit', 'amount'=>40]], 'qa', 1);
        $service = app(\App\Services\VendorDebtService::class);
        $charge = $service->assess(7, 'cancellation_penalty', 'test', '1', 'test-penalty', 70, ['reason'=>'test'], 9);
        $service->reduceUnpaidCharge(7, $charge->id, 20, 9, 'admin review');
        $this->assertEquals(10, $service->outstanding(7));
        $this->assertEquals(0, $ledger->summary(7)[\App\Services\SellerLedgerService::OPERATING]);
        try { $service->reduceUnpaidCharge(7, $charge->id, 20, 9, 'retry'); $this->fail('Repeated reduction accepted'); }
        catch (\DomainException $e) { $this->assertStringContainsString('مسبقًا', $e->getMessage()); }
        $this->assertEquals(10, $service->outstanding(7));
    }

    public function test_reduction_refunds_prior_wallet_collection_once(): void
    {
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_09_120000_create_vendor_penalty_refunds.php')->up();
        DB::table('sellers')->insert(['id'=>7]);
        DB::table('seller_wallets')->insert(['seller_id'=>7,'total_earning'=>0]);
        $ledger = app(\App\Services\SellerLedgerService::class);
        $ledger->post(7, 'qa_credit', 'qa-wallet-refund', [['bucket'=>\App\Services\SellerLedgerService::OPERATING,'direction'=>'credit','amount'=>100]], 'qa', 1);
        $debt = app(\App\Services\VendorDebtService::class);
        $charge = $debt->assess(7, 'cancellation_penalty', 'test', '1', 'qa-wallet-charge', 100, ['reason'=>'test'], 9);
        $debt->reduceUnpaidCharge(7, $charge->id, 30, 9, 'review');
        $this->assertEquals(30, $ledger->summary(7)[\App\Services\SellerLedgerService::OPERATING]);
        $this->assertEquals(30, DB::table('vendor_penalty_refunds')->sum('amount'));
        try { $debt->reduceUnpaidCharge(7, $charge->id, 30, 9, 'retry'); $this->fail('Duplicate refund'); }
        catch (\DomainException $e) { $this->assertStringContainsString('مسبقًا', $e->getMessage()); }
        $this->assertEquals(30, $ledger->summary(7)[\App\Services\SellerLedgerService::OPERATING]);
    }

    public function test_seller_fee_debits_operating_balance_and_records_remainder_as_debt(): void
    {
        DB::table('sellers')->insert(['id' => 7]);
        DB::table('seller_wallets')->insert(['seller_id' => 7, 'total_earning' => 40]);
        $ledger = app(\App\Services\SellerLedgerService::class);
        $ledger->post(7, 'qa_credit', 'qa-operating-credit', [['bucket'=>\App\Services\SellerLedgerService::OPERATING, 'direction'=>'credit', 'amount'=>40]], 'qa', 1);
        DB::table('order_work_items')->insert(['id' => 4, 'order_id' => 10, 'order_detail_id' => 3,
            'seller_id' => 7, 'status' => 'canceled', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('order_logistics_events')->insert(['order_id' => 10, 'order_status' => 'canceled',
            'actor_type' => 'seller', 'actor_id' => 7, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(OrderCancellationSettlementService::class);
        $row = $service->chargeSeller(4, 70, 'إلغاء التاجر بعد القبول', 9);
        $this->assertEquals(40, $row->seller_debited_amount);
        $this->assertEquals(30, $row->seller_debt_amount);
        $this->assertEquals(30, DB::table('vendor_debt_charges')->value('outstanding'));
        $this->assertEquals(0, $ledger->summary(7)[\App\Services\SellerLedgerService::OPERATING]);
        $this->assertEquals(40, $ledger->summary(7)[\App\Services\SellerLedgerService::AVAILABLE]);
        $this->assertSame(1, DB::table('order_work_item_events')->where('event_key', 'seller_cancellation_fee_assessed')->count());
        try { $service->chargeSeller(4, 70, 'إعادة', 9); $this->fail('Duplicate seller fee accepted'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('cancellation', $exception->errors()); }
    }
}
