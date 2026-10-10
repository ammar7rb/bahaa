<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class APIGuestMiddleware
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->header('Authorization') && app('auth')->guard('api')) {
            if (auth('api')->user() && !auth('api')->user()->is_active) {
                return response()->json(['errors'=>[['code'=>'account_frozen','message'=>translate('the_account_is_suspended')]]],403);
            }
            $request->merge(['user' => auth('api')->user()]);
            return $next($request);
        } elseif ($request->guest_id) {
            return $next($request);
        }

        return response()->json(['Unauthorized', 401]);
    }
}
