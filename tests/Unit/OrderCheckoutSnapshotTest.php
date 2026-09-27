<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\ShippingPromiseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderCheckoutSnapshotTest extends TestCase
{
    public function test_query_builder_can_save_delivery_details_and_model_reads_them_as_an_array(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method');
            $table->string('payment_status');
            $table->text('shipping_duration_snapshot');
        });
        try {
            $details = ['key' => 'normal', 'label' => 'شحن عادي', 'min_days' => 7, 'max_days' => 10];
            $service = \Mockery::mock(ShippingPromiseService::class)->makePartial();
            $service->shouldReceive('orderSnapshot')->once()->with(1, null)->andReturn([
                'shipping_duration_snapshot' => $details,
            ]);
            $payload = $service->orderDatabaseSnapshot(1);
            $id = DB::table('orders')->insertGetId($payload + [
                'payment_method' => 'offline_payment', 'payment_status' => 'unpaid',
            ]);
            $order = Order::findOrFail($id);
            $this->assertSame($details, $order->shipping_duration_snapshot);
            $this->assertSame('unpaid', $order->payment_status);
        } finally {
            Schema::dropIfExists('orders');
        }
    }
}
