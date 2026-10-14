<?php
namespace Tests\Unit;

use App\Utils\{OrderManager, CustomerManager};
use App\Services\CustomerPurchaseLimitService;

class CustomerCheckoutPricingTest extends \Tests\TestCase
{
    public function test_stale_coupon_never_reduces_checkout_and_catalogue_prices_stay_consistent(): void
    {
        session()->put(['coupon_code'=>'OLD','coupon_discount'=>50,'coupon_type'=>'free_delivery']);
        $cart=collect([(object)['price'=>40,'discount'=>10,'quantity'=>3]]);
        $summary=OrderManager::getOrderSummaryBeforePlaceOrder($cart,50);
        $this->assertEquals(90,$summary['order_total']);
        $this->assertEquals($summary['order_total'],app(CustomerPurchaseLimitService::class)->getCartProductTotal($cart));
        $this->assertFalse(session()->has('coupon_code'));
        $this->assertFalse(session()->has('coupon_type'));
        $this->assertEquals(0,OrderManager::getTotalCouponAmount([], 'OLD')['discount']);
        $this->assertEquals(0,CustomerManager::getReferralDiscountAmount(['id'=>1],50));
    }
}
