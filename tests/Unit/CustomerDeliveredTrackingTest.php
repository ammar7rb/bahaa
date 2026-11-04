<?php

namespace Tests\Unit;

use App\Utils\OrderManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerDeliveredTrackingTest extends TestCase
{
    public function test_current_delivered_order_is_visible_even_when_history_is_incomplete(): void
    {
        Schema::create('business_settings', fn (Blueprint $t) => [$t->id(), $t->string('type'), $t->text('value')->nullable()]);
        DB::table('business_settings')->insert(['type' => 'language', 'value' => '[{"code":"en","status":1,"default":true}]']);
        Schema::create('orders', function (Blueprint $t) { $t->id(); $t->string('order_status'); $t->timestamps(); });
        Schema::create('order_status_histories', function (Blueprint $t) { $t->id(); $t->integer('order_id'); $t->string('status'); $t->timestamps(); });
        DB::table('orders')->insert(['id'=>1, 'order_status'=>'delivered', 'created_at'=>now()->subDay(), 'updated_at'=>now()]);
        DB::table('order_status_histories')->insert(['order_id'=>1, 'status'=>'confirmed', 'created_at'=>now()->subHours(2), 'updated_at'=>now()]);
        $tracking = OrderManager::getTrackOrderStatusHistory(1, false);
        $this->assertTrue($tracking['history']['order_delivered']['status']);
        $this->assertNotNull($tracking['history']['order_delivered']['date_time']);
        DB::table('orders')->where('id', 1)->update(['order_status'=>'canceled']);
        DB::table('order_status_histories')->insert(['order_id'=>1, 'status'=>'canceled', 'created_at'=>now(), 'updated_at'=>now()]);
        $this->assertFalse(OrderManager::getTrackOrderStatusHistory(1, false)['history']['order_delivered']['status']);
    }
}
