<?php

namespace Tests\Unit;

use App\Services\OrderCancellationSettlementService;
use App\Services\SellerLedgerService;
use App\Services\VendorDebtService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class OrderCancellationDebtTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('order_work_items', function (Blueprint $t) { $t->id(); $t->integer('order_id'); $t->integer('order_detail_id'); $t->integer('seller_id'); $t->string('status'); $t->timestamps(); });
        Schema::create('order_work_item_events', function (Blueprint $t) { $t->id(); $t->integer('work_item_id'); $t->integer('order_id'); $t->integer('order_detail_id'); $t->integer('actor_id'); $t->string('actor_type'); $t->string('event_key'); $t->string('status_before'); $t->string('status_after'); $t->json('snapshot'); $t->timestamp('created_at'); });
        Schema::create('order_logistics_events', function (Blueprint $t) { $t->id(); $t->integer('order_id'); $t->string('order_status'); $t->string('actor_type'); $t->integer('actor_id'); $t->timestamps(); });
        (require base_path('database/migrations/2026_11_08_130000_create_order_cancellation_settlements.php'))->up();
        DB::table('order_work_items')->insert(['id'=>4, 'order_id'=>10, 'order_detail_id'=>3, 'seller_id'=>7, 'status'=>'canceled']);
        DB::table('order_logistics_events')->insert(['order_id'=>10, 'order_status'=>'canceled', 'actor_type'=>'seller', 'actor_id'=>7, 'created_at'=>now(), 'updated_at'=>now()]);
        $ledger = Mockery::mock(SellerLedgerService::class); $ledger->shouldReceive('lockSellerFinancialState')->andReturnNull();
        $this->app->instance(SellerLedgerService::class, $ledger);
    }

    public function test_fee_uses_debt_ledger_and_records_collected_and_outstanding_amounts(): void
    {
        $debt = Mockery::mock(VendorDebtService::class);
        $debt->shouldReceive('createAdministrativeDebt')->once()->andReturn((object) ['id'=>12]);
        $this->app->instance(VendorDebtService::class, $debt);
        $ledger = Mockery::mock(SellerLedgerService::class); $ledger->shouldReceive('lockSellerFinancialState')->andReturnNull();
        $ledger->shouldReceive('summary')->andReturn([SellerLedgerService::AVAILABLE=>40]);
        $ledger->shouldReceive('post')->once()->andReturn(collect()); $this->app->instance(SellerLedgerService::class, $ledger);
        $receipt = app(OrderCancellationSettlementService::class)->chargeSeller(4, 70, 'إلغاء التاجر', 9);
        $this->assertEquals(40, $receipt->seller_debited_amount);
        $this->assertEquals(30, $receipt->seller_debt_amount);
        $this->assertSame('vendor-debt-charge:12', $receipt->financial_reference);
    }

    public function test_existing_automatic_penalty_is_linked_without_assessing_it_again(): void
    {
        Schema::create('vendor_fulfillment_requests', function (Blueprint $t) { $t->id(); $t->integer('order_id'); $t->integer('seller_id'); $t->string('status'); $t->integer('penalty_charge_id')->nullable(); $t->timestamp('canceled_at')->nullable(); });
        Schema::create('vendor_fulfillment_request_items', function (Blueprint $t) { $t->id(); $t->integer('request_id'); $t->integer('order_detail_id'); });
        Schema::create('vendor_fulfillment_events', function (Blueprint $t) { $t->id(); $t->integer('request_id'); $t->string('event_type'); $t->json('metadata'); $t->timestamp('created_at'); });
        Schema::create('vendor_debt_charges', function (Blueprint $t) { $t->id(); $t->integer('seller_id'); $t->decimal('amount'); $t->decimal('wallet_collected'); $t->decimal('debt_assessed'); });
        DB::table('vendor_fulfillment_requests')->insert(['id'=>5,'order_id'=>10,'seller_id'=>7,'status'=>'canceled','penalty_charge_id'=>12]);
        DB::table('vendor_fulfillment_request_items')->insert(['request_id'=>5,'order_detail_id'=>3]);
        DB::table('vendor_fulfillment_events')->insert(['request_id'=>5,'event_type'=>'canceled','metadata'=>'{"actor":"seller"}','created_at'=>now()]);
        DB::table('vendor_debt_charges')->insert(['id'=>12,'seller_id'=>7,'amount'=>70,'wallet_collected'=>40,'debt_assessed'=>30]);
        $debt = Mockery::mock(VendorDebtService::class); $debt->shouldNotReceive('assess');
        $this->app->instance(VendorDebtService::class, $debt);
        $receipt = app(OrderCancellationSettlementService::class)->chargeSeller(4, 70, 'إلغاء التاجر', 9);
        $this->assertSame('fulfillment-cancellation-5', $receipt->financial_reference);
        $this->assertEquals(30, $receipt->seller_debt_amount);
        $this->assertSame(1, DB::table('vendor_debt_charges')->count());
        try {
            app(OrderCancellationSettlementService::class)->chargeSeller(4, 70, 'تكرار', 9);
            $this->fail('Duplicate review must not create another receipt.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertSame(1, DB::table('order_cancellation_settlements')->count());
        }
    }

    public function test_shortfall_is_saved_in_real_vendor_debt_ledger(): void
    {
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_06_000002_create_vendor_debt_ledger.php')->up();
        $ledger = Mockery::mock(SellerLedgerService::class);
        $ledger->shouldReceive('lockSellerFinancialState')->andReturnNull();
        $ledger->shouldReceive('summary')->andReturn([SellerLedgerService::AVAILABLE => 40]);
        $ledger->shouldReceive('post')->once()->andReturn(collect());
        $this->app->instance(SellerLedgerService::class, $ledger);
        $this->app->forgetInstance(VendorDebtService::class);
        $receipt = app(OrderCancellationSettlementService::class)->chargeSeller(4, 70, 'إلغاء التاجر', 9);
        $this->assertEquals(30, DB::table('vendor_debt_charges')->value('outstanding'));
        $this->assertSame(0, DB::table('vendor_debt_repayments')->count());
        $this->assertEquals(30, $receipt->seller_debt_amount);
    }
}
