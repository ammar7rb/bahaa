<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderFinancialAuditService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderFinancialAuditServiceTest extends TestCase
{
    public function test_financial_receipt_preserves_before_and_after_without_a_pricing_approval(): void
    {
        Schema::create('order_financial_events', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('seller_id'); $t->string('event_key')->unique();
            $t->string('event_type'); $t->string('source_type'); $t->integer('source_id'); $t->integer('admin_id')->nullable();
            $t->json('snapshot'); $t->timestamp('created_at');
        });
        $order = new Order(); $order->id=10; $order->seller_id=7; $order->order_amount=100;
        $order->shipping_cost=10; $order->order_status='delivered';
        $data=['financial_before'=>['status'=>'held','amount'=>30], 'financial_after'=>['status'=>'released','amount'=>30]];
        $service=app(OrderFinancialAuditService::class);
        $service->record($order,'settlement_released','seller_settlements',3,$data,9);
        $service->record($order,'settlement_released','seller_settlements',3,$data,9);
        $this->assertSame(1,DB::table('order_financial_events')->count());
        $snapshot=json_decode(DB::table('order_financial_events')->value('snapshot'),true);
        $this->assertSame('held',$snapshot['financial_before']['status']);
        $this->assertSame('released',$snapshot['financial_after']['status']);
        $this->assertEquals(100,$snapshot['customer_amount']);
    }
}
