<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    public function handle($request, \Closure $next, ...$guards)
    {
        return parent::handle($request, function ($request) use ($next,$guards) {
            if (in_array('api',$guards,true) && auth('api')->user() && !auth('api')->user()->is_active) {
                return response()->json(['errors'=>[['code'=>'account_frozen','message'=>translate('the_account_is_suspended')]]],403);
            }
            return $next($request);
        }, ...$guards);
    }
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param Request $request
     * @return string|null
     */
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            return route('authentication-failed');
        }
        return null;
    }
}
