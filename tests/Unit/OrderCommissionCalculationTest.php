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

    public function test_explicit_vendor_amount_is_not_overwritten_by_commission_field(): void
    {
        $service = app(AdminOrderGateService::class);
        $this->assertEquals(75, $service->vendorAmountForReview(['vendor_pricing_mode'=>'amount','vendor_order_amount'=>75,'company_commission_percent'=>10], 100, 60));
        $this->assertEquals(90, $service->vendorAmountForReview(['vendor_pricing_mode'=>'percent','vendor_order_amount'=>75,'company_commission_percent'=>10], 100, 60));
        $this->assertEquals(105, $service->vendorAmountForReview(['vendor_pricing_mode'=>'amount','vendor_order_amount'=>105], 100, 60));
    }

    public function test_explicit_amount_mode_requires_an_amount(): void
    {
        $this->expectException(\DomainException::class);
        app(AdminOrderGateService::class)->vendorAmountForReview(['vendor_pricing_mode'=>'amount'], 100, 60);
    }
}
