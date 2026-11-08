<?php

namespace Tests\Unit;

use App\Services\OrderWorkItemService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderWorkItemDeadlineTest extends TestCase
{
    public function test_only_unanswered_product_crosses_its_deadline(): void
    {
        Schema::create('order_work_items', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('order_detail_id'); $t->string('status');
            $t->timestamp('vendor_response_due_at')->nullable();
            foreach (['vendor_unit_price','shipping_customer_cost','shipping_vendor_cost','customer_total','vendor_net_amount'] as $f) $t->decimal($f, 24, 8)->nullable();
            $t->timestamps();
        });
        Schema::create('order_work_item_events', function (Blueprint $t) {
            $t->id(); $t->integer('work_item_id'); $t->integer('order_id'); $t->integer('order_detail_id');
            $t->integer('actor_id')->nullable(); $t->string('actor_type'); $t->string('event_key');
            $t->string('status_before')->nullable(); $t->string('status_after')->nullable(); $t->json('snapshot'); $t->timestamp('created_at');
        });
        DB::table('order_work_items')->insert([
            ['id'=>1,'order_id'=>10,'order_detail_id'=>1,'status'=>'sent_to_vendor','vendor_response_due_at'=>now()->subHour(),'created_at'=>now(),'updated_at'=>now()],
            ['id'=>2,'order_id'=>10,'order_detail_id'=>2,'status'=>'sent_to_vendor','vendor_response_due_at'=>now()->subHour(),'created_at'=>now(),'updated_at'=>now()],
            ['id'=>3,'order_id'=>10,'order_detail_id'=>3,'status'=>'sent_to_vendor','vendor_response_due_at'=>now()->addHour(),'created_at'=>now(),'updated_at'=>now()],
        ]);
        $service = app(OrderWorkItemService::class);
        $service->transition(\App\Models\OrderWorkItem::findOrFail(1), 'accepted', 7, 'seller', 'vendor_accepted');
        $service->transition(\App\Models\OrderWorkItem::findOrFail(3), 'rejected', 7, 'seller', 'vendor_rejected');
        $this->assertSame(1, $service->markOverdue());
        $this->assertSame(['accepted','overdue','rejected'], DB::table('order_work_items')->orderBy('id')->pluck('status')->all());
        $this->assertSame(2, DB::table('order_work_item_events')->where('event_key', 'vendor_response_timeout')->value('work_item_id'));
    }
}
