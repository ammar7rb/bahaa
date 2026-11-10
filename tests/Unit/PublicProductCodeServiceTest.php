<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\PublicProductCodeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicProductCodeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('added_by');
            $table->unsignedBigInteger('user_id');
            $table->string('public_code')->nullable()->unique();
            $table->integer('status')->default(1);
            $table->integer('request_status')->default(1);
            $table->string('name')->nullable();
            $table->decimal('unit_price', 20, 2)->nullable();
            $table->decimal('original_vendor_price', 20, 2)->nullable();
            $table->boolean('original_vendor_price_verified')->default(false);
            $table->json('seller_original_submission')->nullable();
            $table->timestamps();
        });
        Schema::create('public_product_code_sequences', function (Blueprint $table) {
            $table->string('scope')->primary();
            $table->unsignedBigInteger('next_sequence');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('public_product_code_sequences');
        Schema::dropIfExists('products');
        parent::tearDown();
    }

    public function test_codes_have_independent_vendor_sequences_and_stay_stable(): void
    {
        foreach ([['seller', 1], ['seller', 1], ['seller', 2], ['admin', 7]] as [$owner, $id]) {
            DB::table('products')->insert(['added_by' => $owner, 'user_id' => $id]);
        }
        $service = app(PublicProductCodeService::class);
        $products = Product::query()->withoutGlobalScopes()->orderBy('id')->get();

        $this->assertSame('P1-1', $service->assign($products[0]));
        $this->assertSame('P1-2', $service->assign($products[1]));
        $this->assertSame('P2-1', $service->assign($products[2]));
        $this->assertSame('A-1', $service->assign($products[3]));
        $this->assertSame('P1-1', $service->assign($products[0]));
        $this->assertSame(3, DB::table('public_product_code_sequences')->where('scope', 'seller:1')->value('next_sequence'));
        $this->assertCount(4, DB::table('products')->whereNotNull('public_code')->get());
    }

    public function test_first_code_requires_publication_and_issued_codes_survive_hiding_or_owner_transfer(): void
    {
        $id=DB::table('products')->insertGetId(['added_by'=>'seller','user_id'=>1,'status'=>0,'request_status'=>1]);
        $service=app(PublicProductCodeService::class);$product=Product::withoutGlobalScopes()->findOrFail($id);
        try { $service->assign($product);$this->fail('Hidden first publication must not allocate a code.'); }
        catch(\Illuminate\Validation\ValidationException $exception){ $this->assertNull(DB::table('products')->where('id',$id)->value('public_code')); }
        $this->assertSame(0,DB::table('public_product_code_sequences')->count());
        DB::table('products')->where('id',$id)->update(['status'=>1]);
        $this->assertSame('P1-1',$service->assign($product));
        DB::table('products')->where('id',$id)->update(['status'=>0,'user_id'=>2]);
        $this->assertSame('P1-1',$service->assign($product));
        DB::table('products')->where('id',$id)->delete();
        $next=DB::table('products')->insertGetId(['added_by'=>'seller','user_id'=>1]);
        $this->assertSame('P1-2',$service->assign(Product::withoutGlobalScopes()->findOrFail($next)));
    }
}
