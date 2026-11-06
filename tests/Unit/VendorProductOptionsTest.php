<?php

namespace Tests\Unit;

use App\Services\ProductDateComplianceService;
use App\Services\SellerProductCategoryService;

class VendorProductOptionsTest extends SellerProductCategoryServiceTest
{
    public function test_other_is_an_explicit_choice_and_blank_category_is_rejected(): void
    {
        $service = app(SellerProductCategoryService::class);
        $this->assertSame([], $service->validate(['category_id' => 'other']));
        $this->assertArrayHasKey('category_id', $service->validate(['category_id' => '']));
    }

    public function test_no_dates_clears_stale_values_and_normal_products_keep_date_validation(): void
    {
        $service = app(ProductDateComplianceService::class);
        $this->assertSame(['no_production_expiry_dates' => true, 'production_date' => null, 'expiry_date' => null], $service->normalized([
            'no_production_expiry_dates' => true, 'production_date' => '2020-01-01', 'expiry_date' => '2020-02-01',
        ]));
        $this->assertArrayHasKey('production_date', $service->validate(['no_production_expiry_dates' => false]));
        $this->assertArrayHasKey('expiry_date', $service->validate(['production_date' => '2020-01-01', 'expiry_date' => '2020-02-01']));
    }

    public function test_seller_can_omit_category_and_production_date_without_relaxing_admin_rules(): void
    {
        $categories = app(SellerProductCategoryService::class);
        $this->assertSame([], $categories->validate(['category_id' => null], true));
        $this->assertArrayHasKey('category_id', $categories->validate(['category_id' => null]));
        $dates = app(ProductDateComplianceService::class);
        $expiry = now()->addDays(20)->toDateString();
        $this->assertSame(['no_production_expiry_dates' => false, 'production_date' => null, 'expiry_date' => $expiry], $dates->normalized(['expiry_date' => $expiry], true));
        $this->assertSame(['no_production_expiry_dates' => true, 'production_date' => null, 'expiry_date' => null], $dates->normalized([], true));
        $this->assertArrayHasKey('expiry_date', $dates->validate(['expiry_date' => '2020-01-01'], true));
    }

    public function test_product_entry_templates_compile(): void
    {
        foreach (['vendor-views/product/add-new', 'vendor-views/product/add/_basic-setup', 'vendor-views/product/add/_additional-images', 'vendor-views/product/add/_title-description', 'vendor-views/product/partials/_seller-product-specification'] as $view) {
            $compiled = \Illuminate\Support\Facades\Blade::compileString(file_get_contents(resource_path('views/'.$view.'.blade.php')));
            $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE), $view);
        }
    }
}
