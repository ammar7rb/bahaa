<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderItemReassignmentService;
use App\Services\SellerOrderVisibilityService;
use App\Services\VendorProductWorkspaceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class OrderItemReassignmentFinancialTest extends TestCase
{
    private function table(string $name, array $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns) {
            $table->id();
            foreach ($columns as $column) $table->text($column)->nullable();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        session()->put('local', 'en');
        $this->table('business_settings', ['type', 'value']);
        $this->table('translations', ['translationable_id', 'translationable_type', 'locale', 'key', 'value']);
        $this->table('reviews', ['product_id', 'delivery_man_id', 'status']);
        $this->table('orders', explode(' ', 'seller_id seller_is customer_id order_group_id order_status payment_status order_amount init_order_amount paid_amount shipping_cost shipping_customer_cost shipping_seller_cost shipping_seller_entitlement seller_shipping_allocation platform_shipping_margin admin_commission discount_amount refer_and_earn_discount extra_discount total_tax_amount commerce_flow_version commerce_flow_status commerce_flow_status_updated_at admin_order_review_status admin_order_reviewed_by admin_order_reviewed_at admin_order_review_note shipping_assignment_status shipping_decided_at shipping_decided_by sales_settlement_due_at shipping_settlement_due_at seller_shipping_response_status seller_shipping_response_due_at seller_shipping_response_at seller_shipping_rejection_reason seller_shipping_support_ticket_id shipment_reference third_party_delivery_tracking_id operational_blocked_by operational_block_reason transaction_ref verification_code checked edited_status customer_initial_payment_review_status'));
        $this->table('order_details', explode(' ', 'order_id seller_id product_id qty price tax tax_model discount product_details vendor_unit_price_snapshot vendor_content_revision delivery_status payment_status refund_request refund_started_at is_stock_decreased variant'));
        $this->table('order_work_items', explode(' ', 'order_id order_detail_id seller_id product_id status vendor_unit_price customer_total shipping_customer_cost shipping_vendor_cost vendor_net_amount expected_delivery_at vendor_payment_due_at sent_to_vendor_at vendor_response_due_at vendor_responded_at vendor_response assigned_by_admin_id'));
        $this->table('products', explode(' ', 'user_id name added_by product_type category_id brand_id current_stock status unit_price variation'));
        $this->table('shops', ['seller_id']);
        $this->table('refund_requests', ['order_id', 'order_details_id']);
        $this->table('seller_settlements', ['order_id']);
        $this->table('order_cancellation_settlements', ['order_id', 'operation_key', 'seller_id', 'canceled_by']);
        $this->table('order_vendor_pricings', explode(' ', 'order_id seller_id admin_id procurement_amount merchandise_sale snapshot approved_at'));
        $this->table('order_transactions', explode(' ', 'seller_id shop_id order_id order_amount seller_amount admin_commission received_by status delivery_charge tax customer_id seller_is delivered_by payment_method transaction_id'));
        $this->table('vendor_fulfillment_requests', explode(' ', 'order_id seller_id status vendor_amount shipping_amount decision_reason'));
        $this->table('vendor_fulfillment_request_items', explode(' ', 'request_id order_detail_id'));
        $this->table('vendor_fulfillment_events', explode(' ', 'request_id event_type metadata'));
        $this->table('order_work_item_events', explode(' ', 'work_item_id order_id order_detail_id actor_id actor_type event_key status_before status_after snapshot'));
        $this->table('notifications', explode(' ', 'sent_by sent_to seller_id title description notification_count status'));
        $this->table('storages', ['data_id', 'data_type']);
        (require base_path('database/migrations/2026_11_08_100000_create_order_reassignment_transfers.php'))->up();
        $workspace = Mockery::mock(VendorProductWorkspaceService::class);
        $workspace->shouldReceive('approvedPrice')->andReturn(['unit_price'=>35]);
        $this->app->instance(VendorProductWorkspaceService::class, $workspace);
        DB::table('orders')->insert(['id'=>1,'seller_id'=>10,'seller_is'=>'seller','customer_id'=>1,'order_group_id'=>55,
            'order_status'=>'confirmed','payment_status'=>'paid','order_amount'=>160,'init_order_amount'=>160,'paid_amount'=>160,
            'shipping_cost'=>20,'shipping_customer_cost'=>20,'shipping_seller_cost'=>10,'shipping_seller_entitlement'=>10,
            'seller_shipping_allocation'=>10,'platform_shipping_margin'=>10,'admin_commission'=>40,'discount_amount'=>0,
            'refer_and_earn_discount'=>0,'extra_discount'=>0,'total_tax_amount'=>0,'commerce_flow_status'=>'released_to_seller',
            'shipment_reference'=>'SHP-QA-ASSIGNED-NOT-SHIPPED']);
        DB::table('products')->insert([
            ['id'=>1,'user_id'=>10,'name'=>'Test product','added_by'=>'seller','product_type'=>'physical','category_id'=>1,'current_stock'=>9,'status'=>1,'unit_price'=>30],
            ['id'=>2,'user_id'=>10,'name'=>'Other product','added_by'=>'seller','product_type'=>'physical','category_id'=>1,'current_stock'=>9,'status'=>1,'unit_price'=>30],
            ['id'=>3,'user_id'=>20,'name'=>'Test product','added_by'=>'seller','product_type'=>'physical','category_id'=>1,'current_stock'=>9,'status'=>1,'unit_price'=>35],
        ]);
        DB::table('shops')->insert(['seller_id'=>20]);
        foreach ([1,2] as $i) {
            DB::table('order_details')->insert(['id'=>$i,'order_id'=>1,'seller_id'=>10,'product_id'=>$i,'qty'=>1,'price'=>70,
                'tax'=>0,'discount'=>0,'vendor_unit_price_snapshot'=>30,'product_details'=>'{}','delivery_status'=>'pending','is_stock_decreased'=>1]);
            DB::table('order_work_items')->insert(['id'=>$i,'order_id'=>1,'order_detail_id'=>$i,'seller_id'=>10,'product_id'=>$i,
                'status'=>'sent_to_vendor','vendor_unit_price'=>30,'customer_total'=>80,'shipping_customer_cost'=>10,
                'shipping_vendor_cost'=>5,'vendor_net_amount'=>35]);
        }
        DB::table('order_vendor_pricings')->insert(['order_id'=>1,'seller_id'=>10,'procurement_amount'=>60,'merchandise_sale'=>140,'snapshot'=>'{}']);
        DB::table('order_transactions')->insert(['order_id'=>1,'seller_id'=>10,'order_amount'=>160,'seller_amount'=>60,
            'admin_commission'=>80,'delivery_charge'=>20,'tax'=>0,'status'=>'hold']);
    }

    public function test_late_registered_line_keeps_original_response_deadline(): void
    {
        $sent = now()->subHours(30)->startOfSecond();
        DB::table('orders')->where('id', 1)->update(['admin_order_reviewed_at' => $sent]);
        DB::table('order_work_items')->where('id', 1)->delete();
        $item = app(\App\Services\OrderWorkItemService::class)->ensureForLine(\App\Models\OrderDetail::findOrFail(1));
        $this->assertSame($sent->format('Y-m-d H:i:s'), $item->sent_to_vendor_at->format('Y-m-d H:i:s'));
        $this->assertSame($sent->copy()->addHours(24)->format('Y-m-d H:i:s'), $item->vendor_response_due_at->format('Y-m-d H:i:s'));
        $this->assertTrue($item->vendor_response_due_at->isPast());
    }

    public function test_reassignment_requires_explicit_admin_identity_confirmation(): void
    {
        try {
            app(OrderItemReassignmentService::class)->transfer(1, 3, 7, 'Alternative vendor');
            $this->fail('Identity confirmation was not required');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('تأكيد الإدارة', $e->getMessage());
        }
        $this->assertSame(0, DB::table('order_reassignment_transfers')->count());
    }

    public function test_full_transfer_preserves_customer_payment_and_moves_only_one_seller_entitlement(): void
    {
        $replacement = app(OrderItemReassignmentService::class)->transfer(1, 3, 7, 'Alternative vendor', true);
        $source = Order::findOrFail(1);
        $this->assertEquals(80, $source->order_amount);
        $this->assertEquals(80, $replacement->order_amount);
        $this->assertEquals(160, $source->paid_amount + $replacement->paid_amount);
        $this->assertEquals(10, $source->shipping_cost);
        $this->assertEquals(10, $replacement->shipping_cost);
        $this->assertEquals(30, DB::table('order_vendor_pricings')->where('order_id', 1)->value('procurement_amount'));
        $this->assertEquals(30, DB::table('order_transactions')->where('order_id', 1)->value('seller_amount'));
        $this->assertEquals(0, DB::table('order_transactions')->where('order_id', $replacement->id)->value('seller_amount'));
        $this->assertEquals(160, DB::table('order_transactions')->sum('order_amount'));
        $this->assertEquals(20, DB::table('order_details')->where('id', 1)->value('seller_id'));
        $this->assertEquals(10, DB::table('order_details')->where('id', 2)->value('seller_id'));
        $this->assertSame('awaiting_admin', DB::table('order_work_items')->where('id', 1)->value('status'));
        $this->assertTrue(app(SellerOrderVisibilityService::class)->canSellerView($source));
        $this->assertFalse(app(SellerOrderVisibilityService::class)->canSellerView($replacement));
        $receipt = DB::table('order_reassignment_transfers')->first();
        $this->assertEquals(80, $receipt->customer_amount_transferred);
        $this->assertEquals(60, data_get(json_decode($receipt->before_snapshot, true), 'pricing.procurement_amount'));
        $this->assertEquals(30, data_get(json_decode($receipt->after_snapshot, true), 'source_pricing.procurement_amount'));
    }

    public function test_accepted_product_can_be_moved_without_canceling_another_product(): void
    {
        DB::table('order_work_items')->where('id', 1)->update(['status'=>'accepted']);
        DB::table('vendor_fulfillment_requests')->insert(['id'=>1,'order_id'=>1,'seller_id'=>10,
            'status'=>'accepted','vendor_amount'=>60,'shipping_amount'=>10]);
        DB::table('vendor_fulfillment_request_items')->insert([
            ['request_id'=>1,'order_detail_id'=>1], ['request_id'=>1,'order_detail_id'=>2],
        ]);
        $replacement = app(OrderItemReassignmentService::class)->transfer(1, 3, 7, 'Alternative vendor', true);
        $request = DB::table('vendor_fulfillment_requests')->where('id', 1)->first();
        $this->assertSame('accepted', $request->status);
        $this->assertEquals(30, $request->vendor_amount);
        $this->assertEquals(5, $request->shipping_amount);
        $this->assertSame([2], DB::table('vendor_fulfillment_request_items')->pluck('order_detail_id')->map(fn ($id)=>(int)$id)->all());
        $this->assertEquals(20, $replacement->seller_id);
        $this->assertSame('awaiting_admin', DB::table('order_work_items')->where('id', 1)->value('status'));
        $this->assertEquals(160, DB::table('order_transactions')->sum('order_amount'));
    }

    public function test_disbursed_transaction_rolls_back_the_entire_transfer(): void
    {
        DB::table('order_transactions')->update(['status'=>'disburse']);
        try {
            app(OrderItemReassignmentService::class)->transfer(1, 3, 7, 'Alternative vendor', true);
            $this->fail('A disbursed transaction must not be split.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('مرحلة الحجز', $e->getMessage());
        }
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertEquals(10, DB::table('order_details')->where('id', 1)->value('seller_id'));
        $this->assertEquals(160, DB::table('orders')->where('id', 1)->value('order_amount'));
        $this->assertEquals(9, DB::table('products')->where('id', 3)->value('current_stock'));
        $this->assertSame(0, DB::table('order_reassignment_transfers')->count());
    }

    public function test_incomplete_historical_allocation_blocks_transfer_without_changing_any_values(): void
    {
        DB::table('order_work_items')->where('id', 1)->update(['vendor_net_amount'=>null]);
        try {
            app(OrderItemReassignmentService::class)->transfer(1, 3, 7, 'Alternative vendor', true);
            $this->fail('A missing approved allocation must block reassignment.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('راجع جميع حصص الطلب', $e->getMessage());
        }
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertEquals(160, DB::table('order_transactions')->sum('order_amount'));
        $this->assertEquals(60, DB::table('order_vendor_pricings')->where('order_id', 1)->value('procurement_amount'));
        $this->assertEquals(10, DB::table('order_details')->where('id', 1)->value('seller_id'));
    }

    public function test_actual_tracking_still_blocks_reassignment(): void
    {
        DB::table('orders')->where('id',1)->update(['third_party_delivery_tracking_id'=>'carrier-tracking']);
        try {
            app(OrderItemReassignmentService::class)->transfer(1, 3, 7, 'Alternative vendor', true);
            $this->fail('A tracked shipment must not be moved.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('بدأت تسويتها أو تسليمها', $e->getMessage());
        }
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertEquals(10, DB::table('order_details')->where('id',1)->value('seller_id'));
    }
}
