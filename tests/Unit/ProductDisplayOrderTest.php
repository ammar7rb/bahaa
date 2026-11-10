<?php
namespace Tests\Unit;
use App\Models\Product;
use App\Services\ProductDisplayOrderService;
use App\Services\HomepageSectionBuilderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductDisplayOrderTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        (require base_path('database/migrations/2026_11_10_000003_create_product_display_orders.php'))->up();
        Schema::create('products',function(Blueprint $t){$t->id();$t->integer('category_id');$t->integer('status')->default(1);$t->decimal('unit_price');});
        DB::table('products')->insert([
            ['id'=>1,'category_id'=>7,'unit_price'=>20,'status'=>1],
            ['id'=>2,'category_id'=>7,'unit_price'=>10,'status'=>1],
            ['id'=>3,'category_id'=>8,'unit_price'=>30,'status'=>1],
            ['id'=>4,'category_id'=>8,'unit_price'=>40,'status'=>0],
        ]);
    }

    public function test_web_and_api_share_order_before_pagination_and_do_not_restore_hidden_products(): void {
        $service=app(ProductDisplayOrderService::class);$service->save('catalog',[4,3,1,2],9);
        foreach(['/products','/api/v1/products/latest'] as $path){
            $this->app->instance('request',Request::create($path));
            $page=Product::withoutGlobalScope('translate')->where('status',1)->orderBy('id')->paginate(1);
            $this->assertSame(3,$page->first()->id);$this->assertSame(3,$page->total());
        }
    }
    public function test_category_and_search_have_independent_orders_and_explicit_price_sort_wins(): void {
        $service=app(ProductDisplayOrderService::class);$service->save('category:7',[2,1],9);$service->save('search',[3,1,2],9);
        $this->app->instance('request',Request::create('/api/v1/categories/products/7'));
        $this->assertSame([2,1],Product::withoutGlobalScope('translate')->where('category_id',7)->get()->pluck('id')->all());
        $this->app->instance('request',Request::create('/category/gloves','GET',['category_id'=>7]));
        $this->assertSame([2,1],Product::withoutGlobalScope('translate')->where('category_id',7)->get()->pluck('id')->all());
        $this->app->instance('request',Request::create('/api/v1/products/search','GET',['name'=>'device']));
        $this->assertSame(3,Product::withoutGlobalScope('translate')->where('status',1)->paginate(1)->first()->id);
        $this->app->instance('request',Request::create('/products','GET',['sort_by'=>'low-high']));
        $this->assertSame(2,Product::withoutGlobalScope('translate')->orderBy('unit_price')->first()->id);
    }
    public function test_automatic_homepage_section_reads_its_saved_order_before_limit(): void {
        $products=collect([1,2,3])->map(fn($id)=>(new Product())->forceFill(['id'=>$id,'is_homepage_visible'=>1]));
        $layout=['sections'=>['latest_products'=>['is_visible'=>true,'source_mode'=>'automatic','automatic_source'=>'existing','product_ids'=>[],'settings'=>['limit'=>2,'product_order'=>[3,1,2]]]]];
        $result=app(HomepageSectionBuilderService::class)->resolveProducts('default','latest_products',$products,$layout,false);
        $this->assertSame([3,1],$result->pluck('id')->all());
    }
    public function test_editorial_priority_preserves_paid_search_placements(): void {
        $service=app(ProductDisplayOrderService::class);$service->save('search',[2,1,3],9);
        $products=collect([3,1,2])->map(fn($id)=>(new Product())->forceFill(['id'=>$id,'is_search_promoted'=>$id!==2]));
        $this->assertSame([3,1,2],$service->sort($products,'search')->pluck('id')->all());
    }
    public function test_original_price_comes_from_earliest_preserved_vendor_revision(): void {
        Schema::create('vendor_product_submission_revisions',function(Blueprint $t){$t->id();$t->integer('product_id');$t->text('seller_content');});
        DB::table('vendor_product_submission_revisions')->insert([
            ['product_id'=>1,'seller_content'=>'{"unit_price":80}'],
            ['product_id'=>1,'seller_content'=>'{"unit_price":90}'],
        ]);
        $product=Product::withoutGlobalScopes()->with('originalSubmission')->findOrFail(1);
        $this->assertEquals(80,$product->originalSubmission->seller_content['unit_price']);
        $this->assertEquals(20,$product->unit_price);
        $this->assertSame('M000015',\App\Services\AdministrativeReferenceService::format('seller',15));
    }
    public function test_related_products_use_category_priority_before_taking_ten_and_fall_back_to_catalog(): void {
        foreach(range(5,25) as $id) DB::table('products')->insert(['id'=>$id,'category_id'=>7,'unit_price'=>10]);
        $service=app(ProductDisplayOrderService::class);$service->save('category:7',[25,14],9);
        $query=Product::withoutGlobalScopes()->where('category_id',7)->where('id','!=',1);
        $ids=$service->relatedQuery($query,7)->limit(10)->pluck('id')->all();
        $this->assertCount(10,$ids);$this->assertSame([25,14],array_slice($ids,0,2));$this->assertNotContains(1,$ids);
        $service->save('catalog',[24,23],9);
        $query=Product::withoutGlobalScopes()->where('id','!=',1);
        $this->assertSame([24,23],$service->relatedQuery($query,99)->limit(2)->pluck('id')->all());
    }
}
