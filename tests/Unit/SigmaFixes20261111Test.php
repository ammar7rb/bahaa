<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Services\EgyptPhoneService;
use App\Services\GuestOrderConversionService;
use App\Services\HomepageSectionBuilderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SigmaFixes20261111Test extends TestCase
{
    public function test_category_and_brand_names_survive_missing_translations(): void
    {
        $category = new Category(['name' => 'عناية']);
        $category->setRelation('translations', collect());
        $brand = new Brand(['name' => 'سيجما']);
        $brand->setRelation('translations', collect());

        $this->assertSame('عناية', $category->defaultname);
        $this->assertSame('سيجما', $brand->defaultname);
    }

    public function test_admin_language_list_falls_back_when_the_setting_is_missing(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
        });

        $this->assertSame(['ar'], adminContentLanguages());
    }

    public function test_catalog_price_is_not_multiplied_by_an_exchange_rate(): void
    {
        $this->assertSame(1.0, catalogUnitPrice(1));
        $this->assertSame(149.99, catalogUnitPrice('149.99'));
    }

    public function test_vendor_phone_keeps_the_leading_zero(): void
    {
        $phone = app(EgyptPhoneService::class);
        $this->assertSame('01012345678', $phone->normalizeLocal('+201012345678'));
        $this->assertTrue($phone->isValid('01012345678'));
        $this->assertFalse($phone->isValid('1012345678'));
    }

    public function test_explicit_homepage_placement_is_removed_from_other_automatic_sections(): void
    {
        $products = collect([1, 2, 3])->map(fn ($id) => (new \App\Models\Product())->forceFill(['id' => $id, 'is_homepage_visible' => 1]));
        $layout = ['sections' => [
            'latest_products' => ['is_visible' => true, 'source_mode' => 'automatic', 'automatic_source' => 'existing', 'product_ids' => [], 'settings' => ['limit' => 3]],
            'featured' => ['is_visible' => true, 'source_mode' => 'manual', 'automatic_source' => 'latest', 'product_ids' => [2], 'settings' => ['limit' => 3, 'product_order' => [2]]],
        ]];
        $result = app(HomepageSectionBuilderService::class)->resolveProducts('default', 'latest_products', $products, $layout, false);

        $this->assertSame([1, 3], $result->pluck('id')->all());
    }

    public function test_guest_order_moves_to_the_customer_account_number(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('account_deleted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->boolean('is_guest')->default(1);
            $table->timestamps();
        });
        DB::table('users')->insert(['id' => 15, 'is_active' => 1]);
        DB::table('orders')->insert(['id' => 9, 'customer_id' => null, 'is_guest' => 1]);

        $order = app(GuestOrderConversionService::class)->convert(9, 'C000015');

        $this->assertSame(15, (int) $order->customer_id);
        $this->assertSame(0, (int) $order->is_guest);
        $this->expectException(ValidationException::class);
        app(GuestOrderConversionService::class)->convert(9, 'C000015');
    }
}
