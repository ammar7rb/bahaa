<?php

namespace App\Http\Middleware;

use App\Models\DeliveryMan;
use Closure;
use Illuminate\Http\Request;

class DeliveryManAuth
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $token = explode(' ', $request->header('authorization'));
        if (count($token) > 1 && strlen($token[1]) > 30) {
            $d_man = DeliveryMan::where(['auth_token' => $token['1']])->first();
            if (isset($d_man)) {
                $request['delivery_man'] = $d_man;
                if(!$request->isMethod('GET')&&$request->filled('order_id')){
                    try{return \Illuminate\Support\Facades\DB::transaction(function()use($request,$next,$d_man){
                        $order=\App\Models\Order::where('delivery_man_id',$d_man->id)->lockForUpdate()->findOrFail($request->input('order_id'));
                        app(\App\Services\VendorFulfillmentDecisionService::class)->assertLegacyMutationAllowed($order);
                        return $next($request);
                    },3);}catch(\DomainException $error){return response()->json(['message'=>$error->getMessage()],422);}
                }
                return $next($request);
            }
        }

        return response()->json([
            'auth-001' => translate('Your existing session token does not authorize you any more')
        ], 401);
    }
}
