<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EgpOnlyAndTicketCloseTest extends TestCase
{
    use \Tests\Support\CreatesBaselineTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureBusinessSettings();
    }

    public function test_currency_model_is_always_single_so_nothing_is_converted(): void
    {
        $this->assertSame('single_currency', getWebConfig(name: 'currency_model'));
        $this->assertSame(149.99, \App\Utils\Convert::usd(149.99));
        $this->assertSame(149.99, \App\Utils\Convert::usdPaymentModule(149.99, 'EGP'));
        $this->assertSame(149.99, \App\Utils\Convert::default(149.99));
        $this->assertSame(149.99, (float) usdToDefaultCurrency(149.99));
        $this->assertSame(250.0, (float) currencyConverter(250.0));
        $this->assertSame(75.5, usdToAnotherCurrencyConverter('EGP', 75.5));
    }

    public function test_ticket_close_routes_reject_get_requests(): void
    {
        foreach (['api/v1/customer/support-ticket/close/1', 'api/v1/customer/support-ticket/close-ticket/1', 'support-ticket/1/close'] as $uri) {
            $methods = collect(Route::getRoutes()->getRoutes())
                ->filter(fn ($route) => $route->uri() === $uri || str_ends_with($route->uri(), ltrim(preg_replace('#^.*?support-ticket#', 'support-ticket', $uri), '/')))
                ->flatMap(fn ($route) => $route->methods())->unique()->values()->all();
            $this->assertNotContains('GET', $methods, $uri);
        }
    }
}
