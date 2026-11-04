<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Library\Payer;
use App\Library\Payment as PaymentInfo;
use App\Library\Receiver;
use App\Models\Currency;
use App\Models\OfflinePaymentMethod;
use App\Models\SellerPackage;
use App\Models\SellerPackageSubscription;
use App\Services\SellerLedgerService;
use App\Services\SellerPackagePurchaseService;
use App\Traits\FileManagerTrait;
use App\Traits\Payment;
use App\Traits\PaymentGatewayTrait;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use function App\Utils\payment_gateways;

class SellerPackageController extends Controller
{
    use FileManagerTrait;
    use Payment;
    use PaymentGatewayTrait;

    public function __construct(
        private readonly SellerPackagePurchaseService $purchaseService,
    ) {}

    public function index(): View
    {
        $seller = auth('seller')->user();
        $paymentGatewayList = payment_gateways();
        $digitalPaymentStatus = getWebConfig(name: 'digital_payment');
        $offlinePaymentStatus = getWebConfig(name: 'offline_payment');

        $ledgerSummary = app(SellerLedgerService::class)->summary((int) $seller->id);
        $summary = $this->purchaseService->getSummary($seller);
        $performanceSubscription = request()->filled('subscription')
            ? $seller->packageSubscriptions()->findOrFail(request()->integer('subscription'))
            : $summary['active_subscription'];

        return view(config('vendor_advertising.enabled') ? 'vendor-views.package.advertising' : 'vendor-views.package.index', [
            'slots' => config('vendor_advertising.enabled') ? \App\Models\VendorAdvertisingSlot::where('seller_id', $seller->id)->where('seller_package_subscription_id', $seller->packageSubscriptions()->where('payment_status', 'paid')->where('starts_at', '<=', now())->orderByDesc('starts_at')->value('id') ?? 0)->orderBy('position')->get() : collect(),
            'transitionEndsAt' => config('vendor_advertising.enabled') ? app(\App\Services\VendorAdvertisingAccessService::class)->rollout()?->grace_ends_at : null,
            'products' => config('vendor_advertising.enabled') ? \App\Models\Product::withoutGlobalScopes()->where('added_by', 'seller')->where('user_id', $seller->id)->get(['id', 'name', 'request_status', 'status']) : collect(),
            'packages' => SellerPackage::query()->active()->orderBy('sort_order')->orderBy('id')->get(),
            'summary' => $summary,
            'extraSettings' => config('vendor_advertising.enabled') ? app(\App\Services\VendorExtraAdService::class)->settings() : [],
            'extraPurchases' => config('vendor_advertising.enabled') ? \App\Models\VendorExtraAdPurchase::where('seller_id', $seller->id)->latest('id')->paginate(20, ['*'], 'extra_page') : collect(),
            'subscriptions' => $seller->packageSubscriptions()->latest('id')->limit(10)->get(),
            'performance' => $performanceSubscription ? app(\App\Services\SellerPackagePerformanceService::class)->report($performanceSubscription) : null,
            'operatingBalance' => (float) ($ledgerSummary[SellerLedgerService::OPERATING] ?? 0),
            'paymentGatewayList' => $paymentGatewayList,
            'digitalPaymentAvailable' => count($paymentGatewayList) > 0 && ($digitalPaymentStatus['status'] ?? 0),
            'offlinePaymentAvailable' => (bool) ($offlinePaymentStatus['status'] ?? 0),
            'offlinePaymentMethods' => OfflinePaymentMethod::query()->where('status', 1)->get(),
        ]);
    }

    public function slots(): RedirectResponse
    {
        abort_unless(config('vendor_advertising.enabled'), 404);
        return redirect()->route('vendor.products.list', ['type' => 'all']);
    }

    public function slotsWorkspace(): View
    {
        $seller = auth('seller')->user();
        abort_unless(config('vendor_advertising.enabled'), 404);
        app(\App\Services\VendorAdvertisingSlotService::class)->synchronize($seller);
        $summary = $this->purchaseService->getSummary($seller);
        return view('vendor-views.package.slots', [
            'workspace' => app(\App\Services\VendorAdvertisingOverviewService::class)->build($seller),
            'summary' => $summary,
            'extraSettings' => app(\App\Services\VendorExtraAdService::class)->settings(),
            'extraPurchases' => \App\Models\VendorExtraAdPurchase::where('seller_id', $seller->id)->latest('id')->paginate(20),
            'operatingBalance' => (float) (app(SellerLedgerService::class)->summary((int) $seller->id)[SellerLedgerService::OPERATING] ?? 0),
            'offlinePaymentMethods' => OfflinePaymentMethod::where('status', 1)->get(),
        ]);
    }

    public function openSlot(Request $request, int $slot): RedirectResponse
    {
        abort_unless(config('vendor_advertising.creation_slots_enabled'), 404);
        $key = 'vendor_slot_tokens.'.auth('seller')->id().'.'.$slot;
        $token = session($key) ?: (string) \Illuminate\Support\Str::uuid();
        app(\App\Services\VendorSlotReservationService::class)->reserve(auth('seller')->user(), $slot, $token);
        session([$key => $token]);
        return redirect()->route('vendor.products.add', ['slot_id' => $slot]);
    }

    public function reserveSlot(Request $request, int $slot): \Illuminate\Http\JsonResponse
    {
        $request->merge(['seller' => auth('seller')->user()]);
        $response = app(\App\Http\Controllers\RestAPI\v3\seller\VendorAdvertisingSlotController::class)->reserve($request, $slot);
        session(['vendor_slot_tokens.'.auth('seller')->id().'.'.$slot => $request->input('reservation_token')]);
        return $response;
    }

    public function cancelSlotReservation(Request $request, int $slot): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        abort_unless(config('vendor_advertising.creation_slots_enabled'), 404);
        $request->validate(['reservation_token' => 'nullable|uuid']);
        app(\App\Services\VendorSlotReservationService::class)->cancel(auth('seller')->user(), $slot, $request->input('reservation_token'));
        $key = 'vendor_slot_tokens.'.auth('seller')->id().'.'.$slot;
        if (!$request->filled('reservation_token') || session($key) === $request->input('reservation_token')) session()->forget($key);
        if ($request->boolean('background')) return response()->json(['released' => true]);
        return redirect()->route('vendor.packages.slots');
    }

    /**
     * The package overview already includes the active subscription metrics.
     * Keep this named endpoint so historic links cannot fail while the seller
     * remains inside the same single source of truth for package data.
     */
    public function performance(int $subscriptionId): RedirectResponse
    {
        $subscription = SellerPackageSubscription::query()
            ->where('seller_id', auth('seller')->id())
            ->findOrFail($subscriptionId);

        return redirect()->route('vendor.packages.index', ['subscription' => $subscription->id]);
    }

    public function assignSlot(Request $request, int $slot): RedirectResponse
    {
        $request->merge(['seller' => auth('seller')->user()]);
        $response = app(\App\Http\Controllers\RestAPI\v3\seller\VendorAdvertisingSlotController::class)->update($request, $slot);
        $data = $response->getData(true);
        if ($response->getStatusCode() >= 400) return back()->withErrors(['slot' => $data['message']]);
        ToastMagic::success($data['message']);
        return back();
    }

    public function cancel(Request $request, int $subscriptionId): RedirectResponse
    {
        $request->validate(['reason' => 'required|string|min:3|max:1000']);
        $subscription = SellerPackageSubscription::query()
            ->where('seller_id', auth('seller')->id())
            ->findOrFail($subscriptionId);

        try {
            $this->purchaseService->cancel(
                subscription: $subscription,
                reason: (string) $request->input('reason'),
                requestedByType: 'seller',
                requestedById: (int) auth('seller')->id(),
            );
            ToastMagic::success(translate('package_cancellation_request_submitted'));
        } catch (DomainException $exception) {
            ToastMagic::error(($exception->getMessage() === 'seller_package_payment_is_pending_admin_review' ? 'المعاملة قيد المراجعة.' : translate($exception->getMessage())));
        }

        return redirect()->route('vendor.packages.index');
    }

    public function pay(Request $request): RedirectResponse
    {
        $request->validate(['expected_package_price' => (config('vendor_advertising.creation_slots_enabled') ? 'required' : 'nullable').'|numeric|min:0', 'package_id' => 'required|integer', 'payment_method' => 'required|in:operating_balance', 'request_key' => 'required|uuid']);
        try {
            $package = SellerPackage::findOrFail($request->integer('package_id'));
            $this->purchaseService->purchaseWithPaymentsBalance(auth('seller')->user(), $package, $request->string('request_key')->toString(), $request->filled('expected_package_price') ? (float) $request->input('expected_package_price') : null);
            ToastMagic::success(translate('seller_package_payment_completed_from_operating_balance'));
        } catch (DomainException $exception) { return back()->withErrors(['payment_method' => ($exception->getMessage() === 'seller_package_payment_is_pending_admin_review' ? 'المعاملة قيد المراجعة.' : translate($exception->getMessage()))]); }
        return back();
    }


    public function submitOfflinePayment(Request $request): RedirectResponse
    {
        try {
            app(\App\Services\VendorPackageOfflinePaymentService::class)->submit(auth('seller')->user(), $request);
            ToastMagic::success('المعاملة قيد المراجعة.');
        } catch (DomainException $exception) {
            return back()->withErrors(['payment_method' => ($exception->getMessage() === 'seller_package_payment_is_pending_admin_review' ? 'المعاملة قيد المراجعة.' : translate($exception->getMessage()))]);
        }
        return back();
    }


    private function paymentAdditionalData(mixed $seller, mixed $subscription, string $source): array
    {
        return [
            'business_name' => getWebConfig(name: 'company_name'),
            'business_logo' => getStorageImages(path: getWebConfig('company_web_logo'), type: 'shop'),
            'payment_mode' => $source === 'vendor_web' ? 'web' : 'app',
            'payment_request_from' => $source,
            'seller_id' => $seller->id,
            'seller_package_subscription_id' => $subscription->id,
            'seller_package_id' => $subscription->seller_package_id,
            'package_name' => $subscription->package_name,
            'package_price' => (float) $subscription->paid_package_price,
        ];
    }

    private function getCurrencyCode(Request $request): string
    {
        if (getWebConfig(name: 'currency_model') === 'multi_currency') {
            return $this->getPaymentGatewayCurrencyCode(
                key: $request->get('payment_method'),
                currentCurrency: $request->get('current_currency_code') ?: session('currency_code')
            );
        }

        return Currency::find(getWebConfig(name: 'system_default_currency'))?->code ?: 'USD';
    }

    private function extractPaymentRequestId(string $redirectLink): ?string
    {
        parse_str((string) parse_url($redirectLink, PHP_URL_QUERY), $query);

        return isset($query['payment_id']) && is_string($query['payment_id']) ? $query['payment_id'] : null;
    }
}
