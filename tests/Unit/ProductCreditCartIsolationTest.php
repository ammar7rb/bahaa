<?php
namespace Tests\Unit;

use App\Http\Controllers\Web\CartController;
use App\Models\Product;
use App\Utils\OrderManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCreditCartIsolationTest extends TestCase
{
    public function test_digital_and_physical_direct_paths_reject_credit_before_any_cart_write(): void
    {
        $product=new Product();$product->forceFill(['id'=>3,'is_credit_listing'=>true]);
        $controller=app(CartController::class);
        $request=Request::create('/cart/update','POST',['quantity'=>1]);
        $this->assertSame(0,$controller->addToCartDigitalProduct($request,$product)['status']);
        $this->assertSame(0,$controller->addToCartPhysicalProduct($request,$product)['status']);
        $this->assertSame(0,\App\Utils\CartManager::addToCartDigitalProduct($request,$product,'order_wise',[])['status']);
        $this->assertSame(0,\App\Utils\CartManager::addToCartPhysicalProduct($request,$product,'order_wise',[])['status']);
    }

    public function test_order_again_rejects_credit_without_creating_or_reactivating_cart_rows(): void
    {
        config(['vendor_advertising.enabled'=>false,'vendor_product_workspace.enabled'=>false]);
        Schema::create('business_settings',fn(Blueprint $t)=>[$t->id(),$t->string('type'),$t->text('value')->nullable()]);
        foreach(['product_brand'=>0,'digital_product'=>1,'business_mode'=>'multi'] as $type=>$value) DB::table('business_settings')->insert(['type'=>$type,'value'=>$value]);
        DB::table('business_settings')->insert(['type'=>'language','value'=>json_encode([['code'=>'en','default'=>true,'status'=>1,'direction'=>'ltr']])]);
        Schema::create('products',function(Blueprint $t){
            $t->id();$t->integer('user_id')->default(1);$t->string('added_by')->default('admin');
            $t->integer('status')->default(1);$t->integer('request_status')->default(1);$t->string('product_type')->default('digital');
            $t->integer('brand_id')->nullable();$t->boolean('is_credit_listing')->default(true);$t->string('name')->default('Credit device');
        });
        Schema::create('sellers',fn(Blueprint $t)=>[$t->id(),$t->string('status')]);
        Schema::create('translations',function(Blueprint $t){$t->id();$t->string('translationable_type');$t->integer('translationable_id');$t->string('locale');});
        Schema::create('reviews',function(Blueprint $t){$t->id();$t->integer('product_id');$t->integer('delivery_man_id')->nullable();$t->integer('status');});
        Schema::create('digital_product_variations',fn(Blueprint $t)=>[$t->id(),$t->integer('product_id')]);
        Schema::create('order_details',function(Blueprint $t){$t->id();$t->integer('order_id');$t->integer('product_id');$t->text('product_details');});
        Schema::create('storages',function(Blueprint $t){$t->id();$t->string('data_type');$t->integer('data_id');$t->string('key');$t->string('value');});
        DB::table('products')->insert(['id'=>1]);
        DB::table('order_details')->insert(['id'=>1,'order_id'=>20,'product_id'=>1,'product_details'=>'{"name":"Credit device"}']);
        // Intentionally no carts table: any accidental read/write fails this test.
        $response=OrderManager::generateOrderAgain(Request::create('/order-again','POST',['order_id'=>20]));
        $this->assertSame(0,$response['add_to_cart_count']);
        $this->assertSame(1,$response['failedAddToCartCount']);
        $this->assertStringContainsString('ائتمان',implode(' ',$response['errorMessages']));
        Schema::create('carts',function(Blueprint $t){$t->id();$t->integer('product_id');});
        DB::table('carts')->insert(['product_id'=>1]);
        $this->assertSame(0,\App\Models\Cart::whereHas('product')->count());
        DB::table('products')->insert(['id'=>2,'is_credit_listing'=>false]);
        DB::table('carts')->insert(['product_id'=>2]);
        $this->assertSame(1,\App\Models\Cart::whereHas('product')->count());
    }
}
