<?php

namespace App\Http\Middleware;

use App\Utils\Helpers;
use Closure;
use Illuminate\Http\Request;

class LegacyVendorProductWorkspaceBoundary
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->is('api/v2/seller/auth/*', 'api/v2/seller/registration*')) return $next($request);
        $result = Helpers::get_seller_by_token($request);
        if (!$result['success']) return response()->json(['message' => translate('Unauthenticated')], 401);
        $request->merge(['seller' => $result['data']]);
        return app(\App\Http\Middleware\VendorOrderBoundary::class)->handle($request,
                    fn ($request) => app(\App\Http\Middleware\VendorProductWorkspaceBoundary::class)->handle($request, $next, (int) $result['data']->id), (int) $result['data']->id);
    }
}
