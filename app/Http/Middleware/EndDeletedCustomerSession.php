<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EndDeletedCustomerSession
{
    public function handle(Request $request, Closure $next): mixed {
        if (auth('customer')->user()?->account_deleted_at) {
            auth('customer')->logout();
            $request->session()->forget(['customer_id','is_guest']);
        }
        return $next($request);
    }
}
