<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\CustomerActivationInvoiceService;
use App\Services\CustomerPurchaseLimitService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CriticalBatchConfigurationTest extends TestCase
{
    public function test_customer_purchase_packages_are_legacy_and_cannot_gate_or_debit_checkout(): void
    {
        $service = app(CustomerPurchaseLimitService::class);

        $this->assertFalse($service->isEnabled());
        $this->assertSame(1, $service->validateCheckoutAccess(null, [])['status']);
        $this->assertSame('customer_purchase_packages_disabled', $service->debitOrderLimit(new Order())['message']);
        $this->assertFalse(app(CustomerActivationInvoiceService::class)->shouldHoldPaidOrderForActivation(null));
        $this->assertNull(app(CustomerActivationInvoiceService::class)->createForPaidOrderGroup([1]));
    }

    public function test_customer_purchase_package_routes_are_not_registered(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());

        $this->assertFalse($routes->contains(fn ($route) => str_contains($route->uri(), 'purchase-package')));
        $this->assertFalse($routes->contains(fn ($route) => str_contains($route->uri(), 'purchase-packages')));
        $this->assertFalse($routes->contains(fn ($route) => str_contains($route->uri(), 'activation-invoice')));
    }

    public function test_seller_order_insurance_release_is_registered_in_the_real_scheduler(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString(
            'seller-order-insurances:release-reusable',
            Artisan::output(),
        );
    }
}
