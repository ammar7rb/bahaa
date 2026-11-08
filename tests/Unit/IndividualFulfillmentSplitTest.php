<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Services\SellerLedgerService;
use App\Services\SellerOrderVisibilityService;
use App\Services\VendorFulfillmentDecisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class IndividualFulfillmentSplitTest extends TestCase
{
    public function test_selected_line_splits_approved_goods_and_shipping_without_changing_total(): void
    {
        Schema::create('orders', function (Blueprint $t) { $t->id(); $t->integer('seller_id'); });
        Schema::create('vendor_fulfillment_requests', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('seller_id'); $t->string('portion'); $t->string('status');
            $t->decimal('vendor_amount', 24, 8); $t->decimal('shipping_amount', 24, 8); $t->timestamps();
        });
        Schema::create('vendor_fulfillment_request_items', function (Blueprint $t) {
            $t->id(); $t->integer('request_id'); $t->integer('order_detail_id');
        });
        Schema::create('order_details', function (Blueprint $t) {
            $t->id(); $t->integer('qty'); $t->decimal('vendor_unit_price_snapshot', 24, 8);
        });
        Schema::create('order_work_items', function (Blueprint $t) {
            $t->id(); $t->integer('order_detail_id'); $t->decimal('vendor_net_amount', 24, 8); $t->decimal('shipping_vendor_cost', 24, 8);
        });
        Schema::create('vendor_fulfillment_events', function (Blueprint $t) {
            $t->id(); $t->integer('request_id'); $t->string('event_type'); $t->json('metadata'); $t->timestamp('created_at');
        });
        DB::table('orders')->insert(['id'=>1, 'seller_id'=>2]);
        DB::table('vendor_fulfillment_requests')->insert(['id'=>1, 'order_id'=>1, 'seller_id'=>2, 'portion'=>'advertising',
            'status'=>'available', 'vendor_amount'=>40, 'shipping_amount'=>10, 'created_at'=>now(), 'updated_at'=>now()]);
        DB::table('order_details')->insert([['id'=>1,'qty'=>3,'vendor_unit_price_snapshot'=>10], ['id'=>2,'qty'=>1,'vendor_unit_price_snapshot'=>10]]);
        DB::table('order_work_items')->insert([['order_detail_id'=>1,'vendor_net_amount'=>36,'shipping_vendor_cost'=>6],
            ['order_detail_id'=>2,'vendor_net_amount'=>14,'shipping_vendor_cost'=>4]]);
        DB::table('vendor_fulfillment_request_items')->insert([['request_id'=>1,'order_detail_id'=>1], ['request_id'=>1,'order_detail_id'=>2]]);
        $ledger = Mockery::mock(SellerLedgerService::class); $ledger->shouldReceive('lockSellerFinancialState')->andReturnNull();
        $visibility = Mockery::mock(SellerOrderVisibilityService::class); $visibility->shouldReceive('canSellerView')->andReturnTrue();
        $this->app->instance(SellerLedgerService::class, $ledger); $this->app->instance(SellerOrderVisibilityService::class, $visibility);
        $seller = new Seller(); $seller->id = 2;
        $method = new ReflectionMethod(VendorFulfillmentDecisionService::class, 'individualRequest');
        try {
            DB::transaction(fn () => $method->invoke(app(VendorFulfillmentDecisionService::class), $seller, 1, null));
            $this->fail('A grouped request must require a product selection.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('اختر المنتج', $exception->getMessage());
        }
        $newId = DB::transaction(fn () => $method->invoke(app(VendorFulfillmentDecisionService::class), $seller, 1, 1));
        $old = DB::table('vendor_fulfillment_requests')->where('id', 1)->first();
        $new = DB::table('vendor_fulfillment_requests')->where('id', $newId)->first();
        $this->assertEquals(10, $old->vendor_amount); $this->assertEquals(4, $old->shipping_amount);
        $this->assertEquals(30, $new->vendor_amount); $this->assertEquals(6, $new->shipping_amount);
        $audit = json_decode(DB::table('vendor_fulfillment_events')->where('request_id', $newId)->value('metadata'), true);
        $this->assertEquals(40, $audit['financial_before']['vendor_amount']);
        $this->assertEquals(10, $audit['financial_after']['remaining']['vendor_amount']);
        $this->assertSame(1, DB::table('vendor_fulfillment_request_items')->where('request_id', 1)->count());
        $this->assertSame($newId, DB::transaction(fn () => $method->invoke(app(VendorFulfillmentDecisionService::class), $seller, 1, 1)));
        DB::table('order_details')->insert(['id'=>3,'qty'=>1,'vendor_unit_price_snapshot'=>10]);
        DB::table('order_work_items')->insert(['order_detail_id'=>3,'vendor_net_amount'=>14,'shipping_vendor_cost'=>4]);
        DB::table('vendor_fulfillment_request_items')->insert(['request_id'=>1,'order_detail_id'=>3]);
        DB::table('vendor_fulfillment_requests')->where('id',1)->update(['status'=>'accepted']);
        $separated = app(VendorFulfillmentDecisionService::class)->separate($seller, 1, 2);
        $this->assertSame('accepted', $separated->status);
        $this->assertEquals(5, $separated->vendor_amount);
    }
}
