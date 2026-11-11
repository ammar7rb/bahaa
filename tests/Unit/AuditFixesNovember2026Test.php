<?php

namespace Tests\Unit;

use App\Exceptions\ExpiredSessionResponder;
use App\Http\Middleware\BlockRetiredPointOfSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuditFixesNovember2026Test extends TestCase
{
    public function test_expired_admin_login_returns_to_admin_login_with_message(): void
    {
        $request = Request::create('/login', 'POST', ['role' => 'admin', 'email' => 'a@b.c', 'password' => 'secret']);
        $request->setLaravelSession($this->app['session.store']);
        $response = (new ExpiredSessionResponder())(new HttpException(419), $request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/login/admin', $response->headers->get('Location'));
        $this->assertTrue($this->app['session.store']->get('errors')->has('session_expired'));
        $this->assertArrayNotHasKey('password', $this->app['session.store']->get('_old_input', []));
    }

    public function test_expired_vendor_and_customer_logins_return_to_their_own_pages(): void
    {
        foreach (['/vendor/auth/login' => '/vendor/auth/login', '/customer/auth/login' => '/customer/auth/login'] as $path => $target) {
            $request = Request::create($path, 'POST');
            $request->setLaravelSession($this->app['session.store']);
            $response = (new ExpiredSessionResponder())(new HttpException(419), $request);
            $this->assertStringEndsWith($target, $response->headers->get('Location'));
        }
    }

    public function test_expired_ajax_request_gets_json_with_arabic_message(): void
    {
        $request = Request::create('/customer/auth/login', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = (new ExpiredSessionResponder())(new HttpException(419), $request);

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame('session_expired', $response->getData(true)['status']);
        $this->assertNotEmpty($response->getData(true)['error']);
    }

    public function test_other_http_errors_are_left_to_the_default_handler(): void
    {
        $this->assertNull((new ExpiredSessionResponder())(new HttpException(404), Request::create('/x')));
    }

    public function test_point_of_sale_and_customer_lists_are_closed_for_web_and_api(): void
    {
        $middleware = new BlockRetiredPointOfSale();
        $next = fn () => response('reached', 200);

        foreach (['/vendor/pos', '/vendor/pos/order-place', '/vendor/customer/list', '/admin/pos'] as $path) {
            $request = Request::create($path);
            $request->setLaravelSession($this->app['session.store']);
            $this->assertSame(302, $middleware->handle($request, $next)->getStatusCode(), $path);
        }
        foreach (['/api/v3/seller/pos/customers', '/api/v3/seller/pos/place-order', '/api/v3/seller/coupon/customers'] as $path) {
            $this->assertSame(410, $middleware->handle(Request::create($path), $next)->getStatusCode(), $path);
        }
        foreach (['/vendor/dashboard', '/vendor/orders/list/all', '/admin/customer/accounts/list', '/api/v3/seller/orders/list', '/vendor/position'] as $path) {
            $this->assertSame('reached', $middleware->handle(Request::create($path), $next)->getContent(), $path);
        }
    }

    public function test_vendor_fulfillment_api_routes_resolve_their_controller(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v3/seller/fulfillment-requests/{id}'));
        $this->assertNotEmpty($routes);
        foreach ($routes as $route) {
            $this->assertTrue(class_exists($route->getControllerClass()), $route->getControllerClass());
        }
    }
}
