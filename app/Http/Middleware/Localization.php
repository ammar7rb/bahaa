<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Localization
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // The storefront language selector stores `local`. Resolve it before
        // controllers and child Blade views read localized page content.
        $locale = session('local') ?? session('locale') ?? getDefaultLanguage();
        App::setLocale($locale);
        return $next($request);
    }
}
