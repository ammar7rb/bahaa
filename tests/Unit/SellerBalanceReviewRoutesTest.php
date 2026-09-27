<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SellerBalanceReviewRoutesTest extends TestCase
{
    public function test_admin_can_reach_seller_deposit_review_and_funding_pages(): void
    {
        foreach ([
            'admin.vendors.balance-deposits.index',
            'admin.vendors.balance-deposits.approve',
            'admin.vendors.balance-deposits.reject',
            'admin.vendors.balance-funding-settings.index',
            'admin.vendors.balance-funding-settings.update',
            'admin.vendors.financial-center.index',
        ] as $name) {
            $this->assertTrue(Route::has($name), $name);
        }
    }
}
