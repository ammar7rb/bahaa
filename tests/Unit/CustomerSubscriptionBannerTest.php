<?php

namespace Tests\Unit;

use App\Services\CustomerActivationService;
use App\Services\CustomerMonthlySubscriptionService;
use Mockery;
use Tests\TestCase;

class CustomerSubscriptionBannerTest extends TestCase
{
    private function banner(bool $activated, string $status): string
    {
        $customer = new \App\User;
        $customer->id = 17;
        $this->actingAs($customer, 'customer');
        $activation = Mockery::mock(CustomerActivationService::class);
        $activation->shouldReceive('payload')->once()->andReturn(['is_active' => $activated]);
        $this->app->instance(CustomerActivationService::class, $activation);
        $subscription = Mockery::mock(CustomerMonthlySubscriptionService::class);
        $subscription->shouldReceive('status')->with(17)->once()->andReturn([
            'status' => $status, 'active' => $status === 'active',
        ]);
        $this->app->instance(CustomerMonthlySubscriptionService::class, $subscription);
        return view('customer-subscription.expiry-banner')->render();
    }

    public function test_activated_customer_without_subscription_sees_red_banner_and_subscription_link(): void
    {
        $html = $this->banner(true, 'inactive');
        $this->assertStringContainsString('alert-danger', $html);
        $this->assertStringContainsString('تفعيل الاشتراك الشهري', $html);
        $this->assertStringContainsString(route('customer.subscription.index'), $html);
    }

    public function test_pending_activation_does_not_show_subscription_banner(): void
    {
        $this->assertStringNotContainsString('customer-subscription-notice', $this->banner(false, 'inactive'));
    }

    public function test_active_subscription_hides_banner(): void
    {
        $this->assertStringNotContainsString('customer-subscription-notice', $this->banner(true, 'active'));
    }

    public function test_expired_subscription_shows_new_package_selection(): void
    {
        $html = $this->banner(true, 'expired');
        $this->assertStringContainsString('اختيار باقة شهرية جديدة', $html);
        $this->assertStringContainsString(route('customer.subscription.index'), $html);
    }

    public function test_guest_does_not_see_banner(): void
    {
        $this->assertStringNotContainsString('customer-subscription-notice', view('customer-subscription.expiry-banner')->render());
    }
}
