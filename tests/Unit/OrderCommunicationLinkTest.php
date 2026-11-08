<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\Order\OrderCommunicationLinkController;
use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderCommunicationLinkTest extends TestCase
{
    public function test_cannot_link_another_customers_support_ticket(): void
    {
        Schema::create('support_tickets', function (Blueprint $t) { $t->id(); $t->integer('customer_id'); });
        DB::table('support_tickets')->insert(['id'=>9,'customer_id'=>44]);
        $order = new Order(); $order->id=1; $order->customer_id=3;
        $request = Request::create('/admin/orders/1/communication-links', 'POST', ['channel'=>'support_ticket','resource_id'=>9]);
        $this->expectException(ValidationException::class);
        app(OrderCommunicationLinkController::class)->store($request, $order);
    }

    public function test_cannot_link_a_chat_from_another_vendor(): void
    {
        Schema::create('chattings', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('seller_id')->nullable(); });
        DB::table('chattings')->insert(['id'=>8,'user_id'=>3,'seller_id'=>99]);
        $order = new Order(); $order->id=1; $order->customer_id=3; $order->seller_id=7;
        $request = Request::create('/admin/orders/1/communication-links', 'POST', ['channel'=>'chat_message','resource_id'=>8]);
        $this->expectException(ValidationException::class);
        app(OrderCommunicationLinkController::class)->store($request, $order);
    }
}
