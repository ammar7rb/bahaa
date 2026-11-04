<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Library\Payer;
use App\Library\Payment as PaymentInfo;
use App\Library\Receiver;
use App\Models\Currency;
use App\Models\OfflinePaymentMethod;
use App\Services\SellerBalanceDepositService;
use App\Services\SellerBalanceFundingSettingsService;
use App\Services\SellerFinancePresentationService;
use App\Services\SellerLedgerService;
use App\Traits\FileManagerTrait;
use App\Traits\Payment;
use App\Traits\PaymentGatewayTrait;
use App\Utils\Convert;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use function App\Utils\payment_gateways;

/** Web entry point for the same seller-balance services used by the API. */
class SellerBalanceController extends Controller
{
    use FileManagerTrait, Payment, PaymentGatewayTrait;

    public function __construct(
        private readonly SellerBalanceDepositService $deposits,
        private readonly SellerLedgerService $ledger,
        private readonly SellerBalanceFundingSettingsService $fundingSettings,
        private readonly SellerFinancePresentationService $financePresentation,
    ) {
    }

    public function index(Request $request)
    {
        $section = $request->attributes->get('wallet_section');
        $bucket = $section === 'operating' ? SellerLedgerService::OPERATING : SellerLedgerService::AVAILABLE;
        $seller = auth('seller')->user();
        $funding = $this->fundingSettings->get();
        $gateways = collect(payment_gateways())
            ->filter(fn ($gateway) => $funding['digital_gateways'] === [] || in_array($gateway->key_name, $funding['digital_gateways'], true))
            ->values();
        $summary = $this->ledger->summary((int) $seller->id);

        $data = [
            'walletSection' => $section,
            'transactions' => app(\App\Services\VendorFinancialWorkspaceService::class)->transactions((int) $seller->id, $request->integer('transactions_page', 1), $bucket),
            'withdrawalMethods' => \App\Models\VendorWithdrawMethodInfo::where('user_id', $seller->id)->where('is_active', true)->get(),
            'withdrawals' => \App\Models\WithdrawRequest::where('seller_id', $seller->id)->when($section === 'operating', fn ($query) => $query->where('source_bucket', 'operating'))->latest('id')->paginate(10, ['*'], 'withdrawals_page'),
            'summary' => [
                'sales_total' => (float) ($summary[SellerLedgerService::SALES_TOTAL] ?? 0),
                'pending' => (float) ($summary[SellerLedgerService::PENDING] ?? 0),
                'available' => (float) ($summary[SellerLedgerService::AVAILABLE] ?? 0),
                'operating' => (float) ($summary[SellerLedgerService::OPERATING] ?? 0),
                'order_insurance_credit' => (float) ($summary[SellerLedgerService::ORDER_INSURANCE_CREDIT] ?? 0),
                'pending_withdraw' => (float) ($summary[SellerLedgerService::PENDING_WITHDRAW] ?? 0),
                'withdrawn' => (float) ($summary[SellerLedgerService::WITHDRAWN] ?? 0),
            ],
            'financeOverview' => $this->financePresentation->overview((int) $seller->id, 50),
            'deposits' => \App\Models\SellerBalanceDeposit::where('seller_id', $seller->id)->when($section, fn ($q) => $q->where(fn ($target) => $target->where('metadata->wallet_target', $section)->when($section === 'operating', fn ($legacy) => $legacy->orWhereNull('metadata->wallet_target'))))->latest('id')->paginate(10, ['*'], 'deposits_page'),
            'fundingSettings' => $funding,
            'fundingChoices'=>config('vendor_advertising.enabled') ? app(\App\Http\Controllers\RestAPI\v3\seller\VendorFundingIntentController::class)->index((new Request())->merge(['seller'=>$seller]))->getData(true) : [],
            'paymentGatewayList' => $gateways,
            'digitalPaymentAvailable' => $funding['digital_enabled'] && $gateways->isNotEmpty() && (bool) (getWebConfig(name: 'digital_payment')['status'] ?? 0),
            'offlinePaymentAvailable' => $funding['offline_enabled'] && (bool) (getWebConfig(name: 'offline_payment')['status'] ?? 0),
            'offlinePaymentMethods' => OfflinePaymentMethod::query()->where('status', 1)->whereIn('payment_channel', ['wallet', 'instapay'])->get(),
        ];
        return view($section ? 'vendor-views.balance.details' : 'vendor-views.balance.index', $data);
    }

    public function insurance(Request $request) {
        return redirect()->route('vendor.balance.index');
    }
    public function payments(Request $request) { $request->attributes->set('wallet_section', 'operating'); return $this->index($request); }

    public function pay(Request $request): RedirectResponse
    {
        if ($request->get('wallet_target') === 'insurance') return back()->withErrors(['wallet_target' => translate('seller_insurance_retired')]);
        $validator = Validator::make($request->all(), ['wallet_target' => 'required|in:operating,insurance', 'amount' => 'required|numeric|min:0.01', 'payment_method' => 'required|string|max:100']);
        if ($validator->fails()) return back()->withErrors($validator)->withInput();

        $funding = $this->fundingSettings->get();
        $gateways = collect(payment_gateways());
        if (!$funding['digital_enabled'] || !(getWebConfig(name: 'digital_payment')['status'] ?? 0)
            || !$this->fundingSettings->isGatewayAllowed((string) $request->payment_method)
            || !$gateways->firstWhere('key_name', $request->payment_method)) {
            return back()->withErrors(['payment_method' => translate('payment_method_is_not_available')]);
        }
        try {
            $seller = auth('seller')->user();
            $amount = (float) Convert::usd($request->amount);
            $this->fundingSettings->assertAmountAllowed($amount);
            $currency = Currency::find(getWebConfig(name: 'system_default_currency'))?->code ?: getCurrencyCode(type: 'default');
            $deposit = $this->deposits->createDigital($seller, $amount, $currency, (string) $request->payment_method, (string) $request->wallet_target);
            $payment = new PaymentInfo(success_hook: 'seller_balance_deposit_payment_success', failure_hook: 'seller_balance_deposit_payment_fail', currency_code: $currency, payment_method: (string) $request->payment_method, payment_platform: 'web', payer_id: $seller->id, receiver_id: '100', additional_data: ['payment_mode' => 'web', 'payment_request_from' => 'vendor_web', 'seller_id' => $seller->id, 'seller_balance_deposit_id' => $deposit->id, 'deposit_amount' => $amount, 'wallet_target' => $request->wallet_target], payment_amount: $amount, external_redirect_link: null, attribute: 'seller_balance_deposit', attribute_id: $deposit->id);
            $link = $this->generate_link(new Payer(trim($seller->f_name . ' ' . $seller->l_name), $seller->email, $seller->phone, ''), $payment, new Receiver('receiver_name', 'example.png'));
            if (!$link) return back()->withErrors(['payment_method' => translate('payment_method_is_not_available')]);
            if ($requestId = $this->extractPaymentRequestId((string) $link)) $this->deposits->attachPaymentRequest($deposit, $requestId);
            return redirect()->away($link);
        } catch (DomainException $exception) {
            return back()->withErrors(['amount' => translate($exception->getMessage())])->withInput();
        }
    }

    public function submitOfflinePayment(Request $request): RedirectResponse
    {
        if ($request->get('wallet_target') === 'insurance') return back()->withErrors(['wallet_target' => translate('seller_insurance_retired')]);
        try {
            app(\App\Services\VendorFundingSubmissionService::class)->submit(auth('seller')->user(),$request);
            return back()->with('success',translate('seller_balance_deposit_submitted'));
        } catch (DomainException $e) { return back()->withErrors(['amount'=>translate($e->getMessage())])->withInput(); }
    }

    public function retryFundingIntent(Request $request, int $intent): RedirectResponse
    {
        $request->merge(['seller'=>auth('seller')->user()]);
        $response = app(\App\Http\Controllers\RestAPI\v3\seller\VendorFundingIntentController::class)->retry($request,$intent);
        $data = $response->getData(true);
        if ($response->getStatusCode() >= 400) return back()->withErrors(['funding'=>$data['message']]);
        return back()->with('success',$data['intent']['message'] ?? 'تم تحديث طلب الشراء.');
    }

    private function extractPaymentRequestId(string $link): ?string
    {
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        return isset($query['payment_id']) && is_string($query['payment_id']) ? $query['payment_id'] : null;
    }
}
