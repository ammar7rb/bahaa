<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\SellerRegistrationVerificationService;
use Illuminate\Http\Request;

class SellerMiddleware
{
    public function __construct(
        private readonly SellerRegistrationVerificationService $verificationService,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (auth('seller')->check()
            && in_array(auth('seller')->user()->status, ['approved', 'pending'], true)
            && !$this->verificationService->requiresPhoneVerification(auth('seller')->user())) {
            return app(\App\Http\Middleware\VendorOrderBoundary::class)->handle($request,
                    fn ($request) => app(\App\Http\Middleware\VendorProductWorkspaceBoundary::class)->handle($request, $next, (int) auth('seller')->id()), (int) auth('seller')->id());
        }
        auth()->guard('seller')->logout();

        return redirect()->route('vendor.auth.login');
    }
}
