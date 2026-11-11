<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Point of sale is retired on SIGMA: every order must go through the admin
 * review flow (payment proof → vendor price → shipping → release to vendor).
 * The POS screens also listed every platform customer's name and phone to any
 * vendor. This closes the web screens and the mobile API in one place, by
 * path, so it keeps working even if menus or route files change.
 */
class BlockRetiredPointOfSale
{
    private const BLOCKED = [
        'admin/pos', 'admin/pos/*',
        'vendor/pos', 'vendor/pos/*',
        'vendor/customer/*',
        'api/v3/seller/pos', 'api/v3/seller/pos/*',
        'api/v3/seller/coupon/customers',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->is(...self::BLOCKED)) {
            return $next($request);
        }

        $message = 'نقطة البيع متوقفة. كل الطلبات تمر عبر مراجعة الإدارة.';
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => [['code' => 'pos_retired', 'message' => $message]]], 410);
        }

        $home = $request->is('admin/*') ? url('/admin/dashboard') : url('/vendor/dashboard');
        return redirect()->to($home)->withErrors(['pos' => $message]);
    }
}
