<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class RequireCustomerPasswordChange {
    public function handle(Request $request,Closure $next): mixed {
        $api=$request->is('api/*');
        if($api && !$request->is('api/v1/*'))return $next($request);
        if(!$api && $request->is('admin*','vendor*','seller*'))return $next($request);
        $guard=$api?'api':'customer';$customer=auth($guard)->user();
        if(!$api && $customer && (int)$customer->password_revision>0 && (int)$request->session()->get('customer_password_revision',-1)!==(int)$customer->password_revision) {
            auth($guard)->logout();$customer=null;
        }
        $allowed=$api ? $request->is('api/v1/customer/password-recovery/*','api/v1/auth/login','api/v1/auth/logout') :
            $request->routeIs('customer.password-recovery.*','customer.auth.logout');
        if($customer?->must_change_password && !$allowed) {
            if($api || $request->expectsJson())return response()->json(['code'=>'password_change_required','must_change_password'=>true,'message'=>'غيّر كلمة المرور المؤقتة قبل متابعة استخدام الحساب.'],403);
            return redirect()->route('customer.password-recovery.change-form');
        }
        $response=$next($request);
        if(!$api && !$customer && ($signed=auth('customer')->user()))$request->session()->put('customer_password_revision',(int)$signed->password_revision);
        return $response;
    }
}
