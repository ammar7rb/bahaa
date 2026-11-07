<?php
namespace App\Http\Controllers\RestAPI\v3\seller;
use App\Http\Controllers\Controller;
use App\Models\{SellerBalanceDeposit, SellerPackage, SellerPackageSubscription, VendorFundingIntent};
use App\Services\{VendorExtraAdService, VendorFundingIntentService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DomainException;

class VendorFundingIntentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(config('vendor_advertising.enabled'),404);
        return response()->json([
            'packages'=>SellerPackage::active()->orderBy('sort_order')->get(['id','name','package_price','product_limit']),
            'subscription'=>SellerPackageSubscription::where('seller_id',$request->seller->id)->where('payment_status','paid')->whereIn('status',['active','scheduled'])->where('starts_at','<=',now())->where('expires_at','>',now())->latest('starts_at')->first(),
            'extra_settings'=>app(VendorExtraAdService::class)->settings(),
            'intents'=>VendorFundingIntent::where('seller_id',$request->seller->id)->latest('id')->paginate(20),
        ]);
    }
    public function retry(Request $request, int $intent)
    {
        try {
            $result = DB::transaction(function () use ($request,$intent) {
                app(\App\Services\SellerLedgerService::class)->lockSellerFinancialState($request->seller->id);
                $record = VendorFundingIntent::where('seller_id',$request->seller->id)->lockForUpdate()->findOrFail($intent);
                return app(VendorFundingIntentService::class)->execute(SellerBalanceDeposit::where('seller_id',$request->seller->id)->findOrFail($record->deposit_id));
            });
            return response()->json(['intent'=>$result]);
        } catch (DomainException $e) { return response()->json(['message'=>$e->getMessage()],422); }
    }
}
