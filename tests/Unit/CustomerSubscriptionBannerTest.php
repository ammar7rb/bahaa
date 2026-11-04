<?php

namespace Tests\Unit;

use App\Services\CustomerPurchaseEligibilityService;
use App\Services\CustomerMonthlySubscriptionService;
use Mockery;
use Tests\TestCase;

class CustomerSubscriptionBannerTest extends TestCase
{
    private function banner(string $status): string
    {
        $customer = new \App\User;
        $customer->id = 17;
        $this->actingAs($customer, 'customer');
        $eligibility = Mockery::mock(CustomerPurchaseEligibilityService::class);
        $eligibility->shouldReceive('initialCheckoutGate')->andReturn(['payment_approved' => true]);
        $this->app->instance(CustomerPurchaseEligibilityService::class, $eligibility);
        $subscription = Mockery::mock(CustomerMonthlySubscriptionService::class);
        $subscription->shouldReceive('status')->with(17)->once()->andReturn([
            'status' => $status, 'active' => $status === 'active',
        ]);
        $this->app->instance(CustomerMonthlySubscriptionService::class, $subscription);
        return view('customer-subscription.expiry-banner')->render();
    }

    public function test_customer_without_subscription_does_not_see_banner(): void
    {
        $this->assertStringNotContainsString('customer-subscription-notice', $this->banner('inactive'));
    }

    public function test_active_subscription_hides_banner(): void
    {
        $this->assertStringNotContainsString('customer-subscription-notice', $this->banner('active'));
    }

    public function test_expired_subscription_shows_new_package_selection(): void
    {
        $html = $this->banner('expired');
        $this->assertStringContainsString('اختيار باقة شهرية جديدة', $html);
        $this->assertStringContainsString(route('customer.subscription.index'), $html);
    }

    public function test_guest_does_not_see_banner(): void
    {
        $this->assertStringNotContainsString('customer-subscription-notice', view('customer-subscription.expiry-banner')->render());
    }
}
