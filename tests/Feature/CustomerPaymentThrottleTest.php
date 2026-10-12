<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomerPaymentThrottleTest extends TestCase
{
    public function test_browsing_cannot_exhaust_payment_quota_and_payment_remains_limited(): void
    {
        $request = \Illuminate\Http\Request::create('http://localhost/_test');
        $request->setUserResolver(fn () => null);
        $request->setRouteResolver(fn () => new \Illuminate\Routing\Route('GET', '/_test', fn () => null));
        $middleware = app(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $catalog = fn () => $middleware->handle($request, fn () => response('ok'), 3000, 1, 'api-global:');
        $payment = fn () => $middleware->handle($request, fn () =>
            $middleware->handle($request, fn () => response('ok'), 10, 1, 'customer-package-wallet:'), 3000, 1, 'api-global:');
        for ($i = 0; $i < 12; $i++) $this->assertSame(200, $catalog()->getStatusCode());
        for ($i = 0; $i < 10; $i++) $this->assertSame(200, $payment()->getStatusCode());
        try { $payment(); $this->fail('Payment quota must still apply'); }
        catch (\Illuminate\Http\Exceptions\ThrottleRequestsException $e) { $this->assertSame(429, $e->getStatusCode()); }
        $this->assertSame(200, $catalog()->getStatusCode());
    }
}
