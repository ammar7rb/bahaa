<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderTimelineService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderTimelineServiceTest extends TestCase
{
    public function test_original_order_retains_moved_item_history_and_financial_receipt_in_reverse_order(): void
    {
        Schema::create('order_work_item_events', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('work_item_id'); $t->string('event_key');
            $t->string('actor_type')->nullable(); $t->integer('actor_id')->nullable();
            $t->string('status_before')->nullable(); $t->string('status_after')->nullable();
            $t->json('snapshot')->nullable(); $t->timestamp('created_at');
        });
        Schema::create('order_reassignment_transfers', function (Blueprint $t) {
            $t->id(); $t->integer('source_order_id'); $t->integer('target_order_id'); $t->integer('work_item_id');
            $t->integer('admin_id'); $t->decimal('customer_amount_transferred'); $t->json('before_snapshot');
            $t->json('after_snapshot'); $t->string('reason'); $t->timestamp('created_at');
        });
        Schema::create('support_ticket_convs', function (Blueprint $t) {
            $t->id(); $t->integer('support_ticket_id'); $t->integer('admin_id')->nullable();
            $t->text('admin_message')->nullable(); $t->text('customer_message')->nullable(); $t->timestamp('created_at');
        });
        Schema::create('contacts', function (Blueprint $t) { $t->id(); $t->string('mobile_support_token_hash'); $t->text('message'); $t->timestamp('created_at'); });
        Schema::create('contact_conversation_messages', function (Blueprint $t) { $t->id(); $t->integer('contact_id'); $t->string('sender'); $t->text('body'); $t->timestamp('created_at'); });
        Schema::create('vendor_support_threads', function (Blueprint $t) { $t->id(); $t->integer('order_id')->nullable(); $t->integer('seller_id'); });
        Schema::create('vendor_support_messages', function (Blueprint $t) { $t->id(); $t->integer('vendor_support_thread_id'); $t->integer('admin_id')->nullable(); $t->text('body'); $t->timestamp('created_at'); });
        Schema::create('order_communication_links', function (Blueprint $t) { $t->id(); $t->integer('order_id'); $t->string('channel'); $t->integer('resource_id'); $t->integer('admin_id'); $t->timestamp('created_at'); });
        Schema::create('chattings', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('seller_id')->nullable(); $t->integer('admin_id')->nullable(); $t->boolean('sent_by_customer'); $t->boolean('sent_by_seller'); $t->text('message'); $t->timestamp('created_at'); });
        DB::table('order_reassignment_transfers')->insert(['source_order_id'=>10, 'target_order_id'=>20,
            'work_item_id'=>7, 'admin_id'=>2, 'customer_amount_transferred'=>50,
            'before_snapshot'=>'{"amount":50}', 'after_snapshot'=>'{"amount":0}',
            'reason'=>'إعادة التعيين', 'created_at'=>'2026-01-02 12:00:00']);
        DB::table('order_work_item_events')->insert(['order_id'=>20, 'work_item_id'=>7, 'event_key'=>'reassigned',
            'actor_type'=>'admin', 'actor_id'=>2, 'status_before'=>'canceled', 'status_after'=>'awaiting_admin',
            'snapshot'=>'{}', 'created_at'=>'2026-01-03 12:00:00']);
        DB::table('support_ticket_convs')->insert([
            ['support_ticket_id'=>8, 'admin_id'=>null, 'admin_message'=>null, 'customer_message'=>'رد العميل', 'created_at'=>'2026-01-04 12:00:00'],
            ['support_ticket_id'=>9, 'admin_id'=>null, 'admin_message'=>null, 'customer_message'=>'رسالة أخرى', 'created_at'=>'2026-01-05 12:00:00'],
        ]);
        DB::table('contacts')->insert(['id'=>4,'mobile_support_token_hash'=>hash('sha256','seller-shipping:7:10'),'message'=>'اعتراض شحن','created_at'=>'2026-01-05 12:00:00']);
        DB::table('contact_conversation_messages')->insert(['contact_id'=>4,'sender'=>'requester','body'=>'رسالة التاجر','created_at'=>'2026-01-06 12:00:00']);
        DB::table('vendor_support_threads')->insert([['id'=>1,'order_id'=>10,'seller_id'=>7],['id'=>2,'order_id'=>10,'seller_id'=>8]]);
        DB::table('vendor_support_messages')->insert([['vendor_support_thread_id'=>1,'admin_id'=>2,'body'=>'رسالة مرتبطة','created_at'=>'2026-01-07 12:00:00'],['vendor_support_thread_id'=>2,'admin_id'=>2,'body'=>'رسالة غير مرتبطة','created_at'=>'2026-01-08 12:00:00']]);
        DB::table('order_communication_links')->insert(['order_id'=>10,'channel'=>'chat_message','resource_id'=>5,'admin_id'=>2,'created_at'=>'2026-01-08 12:00:00']);
        DB::table('chattings')->insert(['id'=>5,'user_id'=>3,'seller_id'=>0,'admin_id'=>null,'sent_by_customer'=>1,'sent_by_seller'=>0,'message'=>'رسالة عميل مرتبطة','created_at'=>'2026-01-09 12:00:00']);
        $order = new Order(); $order->id = 10; $order->customer_id = 3; $order->seller_id = 7; $order->seller_shipping_support_ticket_id = 8;
        $events = app(OrderTimelineService::class)->forOrder($order);
        $this->assertCount(8, $events);
        $this->assertSame('رسالة عميل مرتبطة', $events[0]['note']);
        $this->assertSame('رسالة مرتبطة', $events[2]['note']);
        $this->assertSame('رسالة التاجر', $events[3]['note']);
        $this->assertSame('seller', $events[3]['actor']);
        $this->assertSame('رد العميل', $events[5]['note']);
        $this->assertSame('customer', $events[5]['actor']);
        $this->assertSame('reassigned', $events[6]['type']);
        $this->assertSame('canceled', $events[6]['before']);
        $this->assertSame(50, (int) $events[7]['snapshot']['customer_amount']);
    }
}
