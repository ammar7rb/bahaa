<?php

namespace Tests\Unit;

use App\Services\AdminOrderGateService;
use Tests\TestCase;

class OrderCommissionCalculationTest extends TestCase
{
    public function test_commission_deducts_from_merchandise_sale(): void
    {
        $service = app(AdminOrderGateService::class);
        $this->assertEquals(85, $service->vendorAmountFromCommission(100, 15));
        $this->assertEquals(0, $service->vendorAmountFromCommission(100, 100));
        $this->assertEquals(100, $service->vendorAmountFromCommission(100, 0));
    }

    public function test_invalid_percentage_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        app(AdminOrderGateService::class)->vendorAmountFromCommission(100, 101);
    }
}
