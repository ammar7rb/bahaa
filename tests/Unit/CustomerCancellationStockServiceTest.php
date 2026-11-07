<?php
namespace Tests\Unit;

use App\Models\Order;
use App\Services\CustomerCancellationStockService;
use App\Services\CustomerPurchaseLimitService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CustomerCancellationStockServiceTest extends TestCase
{
    public function test_reserved_stock_is_restored_only_once(): void
    {
        config(['vendor_product_workspace.enabled' => false, 'vendor_advertising.enabled' => false]);
        session()->put('local', 'en');
        Schema::create('business_settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->text('value')->nullable(); });
        Schema::create('storages', function (Blueprint $t) { $t->id(); $t->string('data_type'); $t->integer('data_id'); $t->string('key'); $t->string('value'); $t->timestamps(); });
        Schema::create('reviews', function (Blueprint $t) { $t->id(); $t->integer('product_id'); $t->integer('delivery_man_id')->nullable(); $t->boolean('status')->default(true); });
        Schema::create('orders', function (Blueprint $t) { $t->id(); $t->integer('customer_id'); $t->boolean('is_guest')->default(false); });
        Schema::create('order_details', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('product_id'); $t->integer('seller_id');
            $t->string('variant')->nullable(); $t->integer('qty'); $t->boolean('is_stock_decreased'); $t->string('delivery_status')->nullable(); $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) { $t->id(); $t->string('added_by'); $t->integer('user_id'); $t->text('variation'); $t->integer('current_stock'); $t->timestamps(); });
        Schema::create('translations', function (Blueprint $t) { $t->id(); $t->string('translationable_type'); $t->integer('translationable_id'); $t->string('locale'); $t->string('key'); $t->text('value'); });
        DB::table('orders')->insert(['id'=>1,'customer_id'=>1]);
        DB::table('products')->insert(['id'=>1,'added_by'=>'seller','user_id'=>2,'variation'=>'[{"type":"red","qty":3}]','current_stock'=>3]);
        DB::table('order_details')->insert(['order_id'=>1,'product_id'=>1,'seller_id'=>2,'variant'=>'red','qty'=>2,'is_stock_decreased'=>1]);
        DB::table('order_details')->insert(['order_id'=>1,'product_id'=>99,'seller_id'=>2,'variant'=>null,'qty'=>1,'is_stock_decreased'=>1]);
        $limit = Mockery::mock(CustomerPurchaseLimitService::class);
        $limit->shouldReceive('returnOrderLimit')->twice()->andReturn(['status'=>1]);
        $this->app->instance(CustomerPurchaseLimitService::class, $limit);
        $service = app(CustomerCancellationStockService::class);
        $service->restore(Order::findOrFail(1));
        $service->restore(Order::findOrFail(1));
        $this->assertSame(5, DB::table('products')->value('current_stock'));
        $this->assertSame(5, json_decode(DB::table('products')->value('variation'), true)[0]['qty']);
        $this->assertSame([0, 0], DB::table('order_details')->orderBy('id')->pluck('is_stock_decreased')->all());
    }
}
