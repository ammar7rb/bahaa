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
}
