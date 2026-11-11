<?php

namespace Tests\Unit;

use App\Support\OrderCenterFilters;
use Tests\TestCase;

class OrderCenterFiltersTest extends TestCase
{
    public function test_grouped_queues_include_the_underlying_states(): void
    {
        \Illuminate\Support\Facades\Schema::create('orders', function ($table) { $table->id(); $table->string('order_status'); });
        \Illuminate\Support\Facades\Schema::create('order_work_items', function ($table) { $table->id(); $table->integer('order_id'); $table->string('status'); });
        \Illuminate\Support\Facades\DB::table('orders')->insert([['id'=>1,'order_status'=>'processing'],['id'=>2,'order_status'=>'failed']]);
        foreach (['sent_to_vendor','overdue','accepted','rejected','canceled','completed'] as $i=>$status) {
            \Illuminate\Support\Facades\DB::table('order_work_items')->insert(['id'=>$i+1,'order_id'=>1,'status'=>$status]);
        }
        \Illuminate\Support\Facades\DB::table('order_work_items')->insert(['id'=>7,'order_id'=>2,'status'=>'accepted']);
        $method = new \ReflectionMethod(\App\Http\Controllers\Admin\Order\OrderCenterController::class, 'applyQueue');
        foreach (['waiting_vendor'=>[1,2],'active'=>[3],'stopped'=>[4,5,7],'completed'=>[6]] as $queue=>$expected) {
            $query = \App\Models\OrderWorkItem::query();
            $method->invoke(app(\App\Http\Controllers\Admin\Order\OrderCenterController::class), $query, $queue);
            $this->assertSame($expected, $query->orderBy('id')->pluck('id')->all());
        }
    }

    public function test_old_filters_resolve_to_the_seven_visible_groups(): void
    {
        $this->assertCount(7, OrderCenterFilters::OPTIONS);
        $this->assertSame('guests', OrderCenterFilters::normalize('guests'));
        foreach (['sent_to_vendor'=>'waiting_vendor','overdue'=>'waiting_vendor','accepted'=>'active','processing'=>'active','out_for_delivery'=>'active','canceled'=>'stopped','failed'=>'stopped','returned'=>'stopped','rejected'=>'stopped','history'=>'completed'] as $old=>$group) {
            $this->assertSame($group, OrderCenterFilters::normalize($old));
        }
        $this->assertSame('all', OrderCenterFilters::normalize('invalid'));
    }
}
