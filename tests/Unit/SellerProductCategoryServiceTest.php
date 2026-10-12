<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\SellerProductCategoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerProductCategoryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('icon_storage_type')->nullable();
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->integer('position')->default(0);
            $table->boolean('home_status')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('categories');
        parent::tearDown();
    }

    public function test_subcategories_must_belong_to_the_selected_main_category(): void
    {
        $main = Category::withoutGlobalScopes()->create(['name' => 'General', 'slug' => 'general', 'parent_id' => 0, 'position' => 0]);
        $other = Category::withoutGlobalScopes()->create(['name' => 'Other', 'slug' => 'other', 'parent_id' => 0, 'position' => 0]);
        $sub = Category::withoutGlobalScopes()->create(['name' => 'Syringes', 'slug' => 'syringes', 'parent_id' => $main->id, 'position' => 1]);
        $service = app(SellerProductCategoryService::class);

        $this->assertSame([], $service->validate(['category_id' => $main->id, 'sub_category_id' => $sub->id]));
        $this->assertSame('seller_product_sub_category_invalid', $service->validate(['category_id' => $other->id, 'sub_category_id' => $sub->id])['sub_category_id']);
    }

    public function test_inactive_categories_cannot_be_used_for_a_seller_product(): void
    {
        $main = Category::withoutGlobalScopes()->create(['name' => 'Inactive', 'slug' => 'inactive', 'parent_id' => 0, 'position' => 0, 'is_active' => false]);

        $this->assertSame('seller_product_category_invalid', app(SellerProductCategoryService::class)->validate(['category_id' => $main->id])['category_id']);
    }
}
