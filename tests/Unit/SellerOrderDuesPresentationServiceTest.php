<?php
namespace Tests\Unit;

use App\Services\SellerOrderDuesPresentationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerOrderDuesPresentationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->string('seller_is'); $t->string('order_status')->default('pending');
            $t->string('shipping_responsibility')->nullable(); $t->decimal('shipping_seller_entitlement')->nullable();
            $t->timestamp('sales_settlement_due_at')->nullable(); $t->timestamp('shipping_settlement_due_at')->nullable();
            $t->timestamp('expected_delivery_date')->nullable();
        });
        Schema::create('seller_order_insurances', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('seller_id'); $t->decimal('amount'); $t->string('status');
            $t->timestamp('reusable_at')->nullable(); $t->timestamp('reusable_released_at')->nullable();
        });
        Schema::create('seller_settlements', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->integer('order_id'); $t->string('status'); $t->timestamp('due_at')->nullable();
        });
        Schema::create('order_transactions', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->string('seller_is'); $t->integer('order_id'); $t->decimal('seller_amount'); $t->decimal('delivery_charge')->default(0);
        });
    }

    public function test_entitlements_are_scoped_paginated_and_do_not_invent_maturity_dates(): void
    {
        for ($id = 1; $id <= 21; $id++) DB::table('orders')->insert(['id'=>$id,'seller_id'=>7,'seller_is'=>'seller','expected_delivery_date'=>'2026-09-10']);
        DB::table('orders')->insert(['id'=>99,'seller_id'=>8,'seller_is'=>'seller']);
        $service = app(SellerOrderDuesPresentationService::class);
        $page = $service->page(7);
        $this->assertSame(21, $page->total());
        $this->assertCount(20, $page->items());
        $this->assertSame(21, $page->items()[0]['order_id']);
        $this->assertNull($page->items()[0]['sales_due_at']);
        $this->assertNull($page->items()[0]['sales_amount']);
        $this->assertSame(1, $service->page(7, 2)->items()[0]['order_id']);
    }

    public function test_shipping_is_only_a_seller_entitlement_and_insurance_dates_are_preserved(): void
    {
        DB::table('orders')->insert(['id'=>1,'seller_id'=>7,'seller_is'=>'seller','shipping_responsibility'=>'sellerwise_shipping','shipping_seller_entitlement'=>85]);
        DB::table('order_transactions')->insert(['seller_id'=>7,'seller_is'=>'seller','order_id'=>1,'seller_amount'=>120,'delivery_charge'=>200]);
        DB::table('seller_order_insurances')->insert(['seller_id'=>7,'order_id'=>1,'amount'=>30,'status'=>'paid','reusable_at'=>'2026-10-01']);
        DB::table('seller_settlements')->insert(['seller_id'=>7,'order_id'=>1,'status'=>'held','due_at'=>'2026-10-02']);
        $row = app(SellerOrderDuesPresentationService::class)->page(7)->items()[0];
        $this->assertSame(85.0, $row['shipping_amount']);
        $this->assertSame(120.0, $row['sales_amount']);
        $this->assertSame(30.0, $row['insurance_amount']);
        $this->assertStringStartsWith('2026-10-01', $row['insurance_reuse_at']);
        $this->assertStringStartsWith('2026-10-02', $row['sales_due_at']);
        DB::table('orders')->where('id',1)->update(['shipping_responsibility'=>'inhouse_shipping']);
        $this->assertSame(0.0, app(SellerOrderDuesPresentationService::class)->page(7)->items()[0]['shipping_amount']);
    }

    public function test_financial_history_filters_before_pagination_and_keeps_sellers_isolated(): void
    {
        Schema::create('seller_ledger_entries', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->string('reporting_category'); $t->string('bucket');
            $t->string('direction'); $t->decimal('amount'); $t->string('event_type'); $t->string('reference_code'); $t->json('metadata')->nullable(); $t->timestamps();
        });
        for ($i=1; $i<=5; $i++) DB::table('seller_ledger_entries')->insert(['seller_id'=>7,'reporting_category'=>'shipping','bucket'=>'pending','direction'=>'credit','amount'=>20,'event_type'=>'shipping','reference_code'=>'S'.$i]);
        DB::table('seller_ledger_entries')->insert(['seller_id'=>8,'reporting_category'=>'shipping','bucket'=>'pending','direction'=>'credit','amount'=>999,'event_type'=>'shipping','reference_code'=>'OTHER']);
        DB::table('seller_ledger_entries')->insert(['seller_id'=>7,'reporting_category'=>'insurance','bucket'=>'pending','direction'=>'credit','amount'=>30,'event_type'=>'insurance','reference_code'=>'INS']);
        $ledger = \Mockery::mock(\App\Services\SellerLedgerService::class);
        $ledger->shouldReceive('summary')->with(7)->andReturn(['available'=>10]);
        $service = new \App\Services\SellerFinancePresentationService($ledger);
        $result = $service->overview(7, 2, 2, 'shipping');
        $this->assertSame(5, $result['pagination']['total']);
        $this->assertSame(3, $result['pagination']['last_page']);
        $this->assertSame(['S3','S2'], array_column($result['tabs']['balance'], 'reference_code'));
        $this->assertCount(2, $result['tabs']['shipping']);
        $this->assertSame([], $result['tabs']['insurance']);
    }
}
