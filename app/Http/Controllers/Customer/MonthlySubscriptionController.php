<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerSubscriptionPayment;
use App\Services\CustomerMonthlySubscriptionService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MonthlySubscriptionController extends Controller
{
    public function __construct(private readonly CustomerMonthlySubscriptionService $subscriptions) {}

    private function customer(Request $request): int
    {
        return (int) ($request->is('api/*') ? $request->user('api')->id : auth('customer')->id());
    }

    public function index(Request $request)
    {
        $id = $this->customer($request);
        $payload = [
            'wallet_policy_version'=>2,
            'packages' => \Illuminate\Support\Facades\Schema::hasTable('customer_purchase_packages') ? app(\App\Services\CustomerPackageCatalogService::class)->available()->get()->map(function($package){$package->setAttribute('quote_token',app(\App\Services\CustomerPackageCatalogService::class)->token($package,'package'));return $package;}) : collect(),
            'increase_offers' => \Illuminate\Support\Facades\Schema::hasTable('customer_credit_offers') ? \Illuminate\Support\Facades\DB::table('customer_credit_offers')->where('active',true)->get()->map(function($offer){$offer->quote_token=app(\App\Services\CustomerPackageCatalogService::class)->token($offer,'offer');return $offer;}) : collect(),
            'subscription' => $this->subscriptions->status($id),
            'offline_methods' => collect(),
            'purchase_wallet_enabled' => getWebConfig('wallet_status') == 1,
            'purchase_balance' => (float) \App\Models\User::findOrFail($id)->wallet_balance,
            'payments' => CustomerSubscriptionPayment::where('customer_id', $id)->latest('id')->paginate(10),
        ];
        return $request->is('api/*') ? response()->json($payload) : view('customer-subscription.packages-wallet', $payload);
    }

    public function checkout(Request $request)
    {
        abort(410,'حدّث التطبيق أو الصفحة. معاملات الباقات الجديدة تُنفذ مباشرة من محفظة المنصة.');
    }

    public function wallet(Request $request, int $payment)
    {
        abort(410,'مسار دفع مسودات الباقات مغلق. اختر معاملة جديدة من صفحة الباقات.');
    }

    public function cancel(Request $request, int $payment)
    {
        try { $this->subscriptions->cancelDraft($this->customer($request), $payment); }
        catch (DomainException $e) { throw ValidationException::withMessages(['subscription' => $e->getMessage()]); }
        return $request->is('api/*') ? response()->json(['cancelled' => true]) : redirect()->route('customer.subscription.index');
    }

    public function offline(Request $request, int $payment)
    {
        abort(410,'الدفع الخارجي للباقات مغلق. اشحن محفظة المنصة ثم نفّذ العملية منها.');
    }

    public function transact(Request $request)
    {
        $data=$request->validate([
            'request_key'=>'required|uuid','operation'=>'required|in:purchase,renew_limit',
            'package_id'=>'nullable|integer|min:1','offer_id'=>'nullable|integer|min:1','quantity'=>'sometimes|integer|min:1|max:5',
            'quote_token'=>'required|string|size:64|regex:/^[a-f0-9]{64}$/',
            'expected_amount'=>'required|numeric|gt:0|max:4999999995|decimal:0,3',
            'expected_subscription_id'=>'nullable|integer|min:1','expected_revision'=>'required|integer|min:0',
        ]);
        try { return $this->result($request,app(\App\Services\CustomerPackageWalletService::class)->transact($this->customer($request),$data)); }
        catch(DomainException $e){throw ValidationException::withMessages(['subscription'=>$e->getMessage()]);}
    }

    private function result(Request $request, CustomerSubscriptionPayment $payment)
    {
        return $request->is('api/*') ? response()->json(['payment' => $payment, 'subscription' => $this->subscriptions->status($payment->customer_id)])
            : redirect()->route('customer.subscription.index')->with('subscription_message', $payment->purchase_kind === 'activation' ? 'تم الاشتراك والخصم من محفظتك لمدة 30 يومًا.' : 'تم تحديث الحد الائتماني والخصم من محفظتك؛ راجع تاريخ انتهاء الباقة الموضح.');
    }
}
