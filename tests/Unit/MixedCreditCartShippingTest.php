<?php
namespace Tests\Unit;
use App\Models\ShippingMethod;
use App\Services\CustomerThreeStepShippingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MixedCreditCartShippingTest extends TestCase
{
    public function test_old_credit_rows_do_not_invalidate_normal_product_rates_or_quantity(): void
    {
        Schema::create('business_settings',function(Blueprint $t){$t->id();$t->string('type');$t->text('value');});
        foreach([
            'customer_three_step_shipping_method_ids'=>json_encode(['normal'=>5]),
            'customer_product_shipping_quantity_threshold'=>'5',
            'customer_product_shipping_extra_charge_type'=>'percent',
            'customer_product_shipping_extra_charge_value'=>'50',
            'language'=>json_encode([['code'=>'en','default'=>true,'status'=>1,'direction'=>'ltr']]),
        ] as $type=>$value) DB::table('business_settings')->insert(compact('type','value'));
        Schema::create('products',function(Blueprint $t){$t->id();$t->boolean('is_credit_listing')->default(false);$t->decimal('normal_shipping_cost')->nullable();});
        Schema::create('translations',function(Blueprint $t){$t->id();$t->string('translationable_type');$t->integer('translationable_id');$t->string('locale');});
        Schema::create('reviews',function(Blueprint $t){$t->id();$t->integer('product_id');$t->integer('delivery_man_id')->nullable();$t->integer('status');});
        Schema::create('carts',function(Blueprint $t){$t->id();$t->integer('product_id');$t->integer('quantity');$t->string('cart_group_id');$t->string('product_type');$t->boolean('is_checked');});
        DB::table('products')->insert([
            ['id'=>1,'is_credit_listing'=>false,'normal_shipping_cost'=>10],
            ['id'=>2,'is_credit_listing'=>false,'normal_shipping_cost'=>7],
            ['id'=>3,'is_credit_listing'=>true,'normal_shipping_cost'=>null],
        ]);
        foreach([1=>2,2=>3,3=>100] as $id=>$qty) DB::table('carts')->insert(['product_id'=>$id,'quantity'=>$qty,'cart_group_id'=>'mixed','product_type'=>'physical','is_checked'=>1]);
        $method=(new ShippingMethod())->forceFill(['id'=>5]);$service=app(CustomerThreeStepShippingService::class);
        $breakdown=$service->getCartGroupShippingBreakdown('mixed',$method);
        $this->assertSame(5,$breakdown['shipping_quantity']);
        $this->assertEquals(41,$breakdown['total_shipping_cost']);
        $this->assertEquals(0,$breakdown['shipping_quantity_surcharge']);
        DB::table('carts')->update(['is_checked'=>0]);
        $this->assertEquals(41,$service->getCartGroupShippingBreakdown('mixed',$method)['total_shipping_cost']);
        DB::table('carts')->where('product_id',3)->update(['is_checked'=>1]);
        $this->assertEquals(0,$service->getCartGroupShippingBreakdown('mixed',$method)['total_shipping_cost']);
        DB::table('carts')->update(['is_checked'=>1]);
        DB::table('products')->where('id',2)->update(['normal_shipping_cost'=>null]);
        $this->assertNull($service->getCartGroupShippingBreakdown('mixed',$method));
    }
}
