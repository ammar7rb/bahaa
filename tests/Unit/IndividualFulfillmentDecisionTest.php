<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Services\SellerLedgerService;
use App\Services\SellerOrderVisibilityService;
use App\Services\VendorFulfillmentDecisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class IndividualFulfillmentDecisionTest extends TestCase
{
    public function test_accepting_one_line_and_rejecting_another_preserves_separate_amounts(): void
    {
        Event::fake();
        Schema::create('orders', function (Blueprint $t) { $t->id(); $t->integer('seller_id'); $t->string('seller_is'); $t->string('order_status'); $t->string('commerce_flow_status')->nullable(); $t->timestamps(); });
        Schema::create('vendor_fulfillment_requests', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('seller_id'); $t->string('portion'); $t->string('status');
            $t->decimal('vendor_amount',24,8); $t->decimal('shipping_amount',24,8); $t->timestamp('forwarded_at')->nullable();
            $t->timestamp('accepted_at')->nullable(); $t->timestamp('rejected_at')->nullable(); $t->text('decision_reason')->nullable(); $t->timestamps();
        });
        Schema::create('vendor_fulfillment_request_items', function (Blueprint $t) { $t->id(); $t->integer('request_id'); $t->integer('order_detail_id'); });
        Schema::create('vendor_fulfillment_events', function (Blueprint $t) { $t->id(); $t->integer('request_id'); $t->string('event_type'); $t->json('metadata')->nullable(); $t->timestamp('created_at'); });
        Schema::create('order_details', function (Blueprint $t) { $t->id(); $t->integer('order_id'); $t->integer('qty'); $t->decimal('vendor_unit_price_snapshot',24,8); $t->boolean('is_stock_decreased'); $t->string('delivery_status'); $t->timestamps(); });
        Schema::create('storages', function (Blueprint $t) { $t->id(); $t->integer('data_id'); $t->string('data_type'); });
        Schema::create('order_work_items', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('order_detail_id'); $t->string('status'); $t->timestamp('vendor_response_due_at')->nullable();
            foreach (['vendor_unit_price','shipping_customer_cost','shipping_vendor_cost','customer_total','vendor_net_amount'] as $f) $t->decimal($f,24,8)->nullable(); $t->timestamps();
        });
        Schema::create('order_work_item_events', function (Blueprint $t) { $t->id(); $t->integer('work_item_id'); $t->integer('order_id'); $t->integer('order_detail_id'); $t->integer('actor_id')->nullable(); $t->string('actor_type'); $t->string('event_key'); $t->string('status_before')->nullable(); $t->string('status_after')->nullable(); $t->json('snapshot'); $t->timestamp('created_at'); });
        Schema::create('vendor_fulfillment_financial_reviews', function (Blueprint $t) { $t->id(); $t->integer('request_id')->unique(); $t->integer('order_id'); $t->string('status'); $t->json('line_ids'); $t->text('reason'); $t->timestamps(); });
        DB::table('orders')->insert(['id'=>1,'seller_id'=>2,'seller_is'=>'seller','order_status'=>'confirmed','commerce_flow_status'=>'released_to_seller']);
        DB::table('vendor_fulfillment_requests')->insert(['id'=>1,'order_id'=>1,'seller_id'=>2,'portion'=>'advertising','status'=>'available','vendor_amount'=>40,'shipping_amount'=>10,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('order_details')->insert([['id'=>1,'order_id'=>1,'qty'=>3,'vendor_unit_price_snapshot'=>10,'is_stock_decreased'=>0,'delivery_status'=>'pending'],['id'=>2,'order_id'=>1,'qty'=>1,'vendor_unit_price_snapshot'=>10,'is_stock_decreased'=>0,'delivery_status'=>'pending']]);
        DB::table('order_work_items')->insert([['id'=>1,'order_id'=>1,'order_detail_id'=>1,'status'=>'sent_to_vendor','vendor_net_amount'=>36,'shipping_vendor_cost'=>6],['id'=>2,'order_id'=>1,'order_detail_id'=>2,'status'=>'sent_to_vendor','vendor_net_amount'=>14,'shipping_vendor_cost'=>4]]);
        DB::table('vendor_fulfillment_request_items')->insert([['request_id'=>1,'order_detail_id'=>1],['request_id'=>1,'order_detail_id'=>2]]);
        $ledger=Mockery::mock(SellerLedgerService::class); $ledger->shouldReceive('lockSellerFinancialState')->andReturnNull();
        $visibility=Mockery::mock(SellerOrderVisibilityService::class); $visibility->shouldReceive('canSellerView')->andReturnTrue();
        $this->app->instance(SellerLedgerService::class,$ledger); $this->app->instance(SellerOrderVisibilityService::class,$visibility);
        $seller=new Seller(); $seller->id=2; $service=app(VendorFulfillmentDecisionService::class);
        $accepted=$service->decide($seller,1,'accept',lineId:1);
        $rejected=$service->decide($seller,1,'reject','غير متاح',lineId:2);
        $this->assertSame('accepted',$accepted->status); $this->assertSame('rejected',$rejected->status);
        $this->assertEquals(30,$accepted->vendor_amount); $this->assertEquals(10,$rejected->vendor_amount);
        $this->assertSame(['accepted','rejected'],DB::table('order_work_items')->orderBy('id')->pluck('status')->all());
        $this->assertSame(1,DB::table('vendor_fulfillment_financial_reviews')->count());
    }
}
