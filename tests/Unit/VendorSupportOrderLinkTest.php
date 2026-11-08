<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\Vendor\VendorSupportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendorSupportOrderLinkTest extends TestCase
{
    public function test_a_conversation_cannot_be_linked_to_another_sellers_order(): void
    {
        Schema::create('sellers', fn (Blueprint $t) => $t->id());
        Schema::create('orders', function (Blueprint $t) { $t->id(); $t->integer('seller_id'); $t->string('seller_is'); });
        DB::table('sellers')->insert(['id'=>7]);
        DB::table('orders')->insert(['id'=>10,'seller_id'=>8,'seller_is'=>'seller']);
        $request = Request::create('/admin/vendor-support', 'POST', [
            'seller_id'=>7,'order_id'=>10,'subject'=>'طلب','body'=>'رسالة',
        ]);
        $this->expectException(ValidationException::class);
        app(VendorSupportController::class)->store($request);
    }
}
