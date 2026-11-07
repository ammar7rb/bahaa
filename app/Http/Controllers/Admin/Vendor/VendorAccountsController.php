<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\SellerBalanceDeposit;
use App\Models\SellerLedgerEntry;
use App\Models\SellerPackage;
use App\Models\SellerPackageSubscription;
use App\Models\VendorExtraAdPurchase;
use App\Services\AdministrativeReferenceService;
use App\Services\SellerBalanceDepositService;
use App\Services\SellerLedgerService;
use App\Services\SellerPackagePurchaseService;
use App\Services\VendorDebtService;
use App\Services\VendorExtraAdService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendorAccountsController extends Controller
{
    private function authorizeFinance(): void
    {
        abort_unless(auth('admin')->check() && \App\Utils\Helpers::module_permission_check('people')
            && ((int) auth('admin')->user()->admin_role_id === 1 || \App\Utils\Helpers::module_permission_check('vendor_financial_manage')), 403);
    }

    private function canViewFinance(): bool
    {
        return (int) auth('admin')->user()->admin_role_id === 1
            || \App\Utils\Helpers::module_permission_check('vendor_financial_view')
            || \App\Utils\Helpers::module_permission_check('vendor_financial_manage');
    }

    public function index(Request $request, string $page = 'list')
    {
        abort_unless(in_array($page, ['list', 'payments', 'debts', 'subscriptions'], true), 404);
        if ($page !== 'list') abort_unless($this->canViewFinance(), 403);
        $request->validate(['q' => 'nullable|string|max:100', 'seller_id' => 'nullable|integer|min:1',
            'status' => 'nullable|in:approved,pending,rejected,suspended', 'tab' => 'nullable|string|max:30']);
        $seller = $request->filled('seller_id') ? Seller::with(['shop', 'activePackageSubscription'])->findOrFail($request->integer('seller_id')) : null;
        $search = trim((string) $request->input('q', ''));
        $sellers = Seller::query()->with(['shop', 'activePackageSubscription'])
            ->when($search !== '', function ($query) use ($search) {
                $parsed = AdministrativeReferenceService::parse($search);
                $query->where(function ($q) use ($search, $parsed) {
                    if ($parsed && $parsed['type'] === 'sellers') $q->orWhereKey($parsed['id']);
                    if (ctype_digit($search)) $q->orWhereKey((int) $search);
                    $like = '%'.addcslashes($search, '%_\\').'%';
                    $q->orWhere('f_name', 'like', $like)->orWhere('l_name', 'like', $like)
                        ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)
                        ->orWhereHas('shop', fn ($shop) => $shop->where('name', 'like', $like));
                });
            })->when($page === 'list' && $request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('id')->paginate(20, ['*'], 'accounts_page')->withQueryString();

        $debt = $seller && $this->canViewFinance() ? app(VendorDebtService::class)->presentation((int) $seller->id) : null;
        $balance = $seller && $this->canViewFinance() ? (float) (app(SellerLedgerService::class)->summary((int) $seller->id, false)[SellerLedgerService::OPERATING] ?? 0) : null;
        $packages = $page === 'subscriptions' ? SellerPackage::active()->orderBy('sort_order')->get() : collect();
        $adsSettings = $page === 'subscriptions' && config('vendor_advertising.enabled') ? app(VendorExtraAdService::class)->settings() : [];
        $subscriptions = $page === 'subscriptions' ? SellerPackageSubscription::query()->with(['seller:id,f_name,l_name,status', 'package:id,name'])
            ->when($seller, fn ($q) => $q->where('seller_id', $seller->id))->latest('id')->paginate(20, ['*'], 'subscription_page')->withQueryString() : null;
        $ads = $page === 'subscriptions' && config('vendor_advertising.enabled') ? VendorExtraAdPurchase::query()->with('seller:id,f_name,l_name')
            ->when($seller, fn ($q) => $q->where('seller_id', $seller->id))->latest('id')->paginate(20, ['*'], 'ads_page')->withQueryString() : null;
        $deposits = $page === 'payments' ? SellerBalanceDeposit::query()->with('seller:id,f_name,l_name')
            ->where(function ($q) { $q->whereNull('metadata->wallet_target')->orWhere('metadata->wallet_target', 'operating'); })
            ->when($seller, fn ($q) => $q->where('seller_id', $seller->id))->latest('id')->paginate(20, ['*'], 'deposit_page')->withQueryString() : null;
        $debtRows = $page === 'debts' && app(VendorDebtService::class)->available() ? DB::table('vendor_debt_charges as d')
            ->join('sellers as s', 's.id', '=', 'd.seller_id')
            ->leftJoinSub(DB::table('vendor_debt_repayments')->select('charge_id')->selectRaw('MAX(created_at) as last_repaid_at')
                ->whereNull('reversed_at')->groupBy('charge_id'), 'repayments', 'repayments.charge_id', '=', 'd.id')
            ->select('d.*', 's.f_name', 's.l_name', 'repayments.last_repaid_at')
            ->when($seller, fn ($q) => $q->where('d.seller_id', $seller->id))->orderByDesc('d.id')
            ->paginate(20, ['*'], 'debt_page')->withQueryString() : null;
        $entries = $page === 'payments' ? SellerLedgerEntry::query()->where('bucket', SellerLedgerService::OPERATING)
            ->when($seller, fn ($q) => $q->where('seller_id', $seller->id))->latest('id')
            ->paginate(20, ['*'], 'ledger_page')->withQueryString() : null;
        return view('admin-views.vendor.accounts.index', compact('page', 'seller', 'sellers', 'debt', 'balance', 'packages',
            'adsSettings', 'subscriptions', 'ads', 'deposits', 'debtRows', 'entries'));
    }

    public function lookup(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);
        $parsed = AdministrativeReferenceService::parse($request->input('q'));
        $term = trim((string) $request->input('q'));
        $rows = Seller::query()->when($parsed && $parsed['type'] === 'sellers', fn ($q) => $q->whereKey($parsed['id']),
            function ($q) use ($term) { $q->where(fn ($inner) => $inner->where('f_name', 'like', '%'.$term.'%')
                ->orWhere('l_name', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')); })->limit(10)->get(['id', 'f_name', 'l_name', 'phone', 'status']);
        return response()->json($rows->map(fn ($s) => ['id' => $s->id, 'reference' => AdministrativeReferenceService::format('vendor', $s->id),
            'name' => trim($s->f_name.' '.$s->l_name), 'phone' => $s->phone, 'status' => $s->status]));
    }

    public function debt(Request $request): RedirectResponse
    {
        $this->authorizeFinance();
        $data = $request->validate(['seller_id' => 'required|integer|exists:sellers,id', 'amount' => 'required|numeric|gt:0|max:999999999|decimal:0,2',
            'reason' => 'required|string|max:2000', 'request_key' => 'required|uuid', 'confirmation' => 'required|string']);
        $this->confirmSeller($data);
        try {
            app(VendorDebtService::class)->createAdministrativeDebt((int) $data['seller_id'], $data['amount'], $data['reason'], $data['request_key'], (int) auth('admin')->id());
            return back()->with('vendor_account_message', 'سُجلت المديونية في حساب التاجر.');
        } catch (DomainException $e) { return back()->withErrors(['debt' => $e->getMessage()]); }
    }

    public function fund(Request $request): RedirectResponse
    {
        $this->authorizeFinance();
        $data = $request->validate(['seller_id' => 'required|integer|exists:sellers,id', 'amount' => 'required|numeric|gt:0|max:999999999|decimal:0,2',
            'reason' => 'required|string|max:1000', 'payment_reference' => 'required|string|max:191',
            'request_key' => 'required|uuid', 'confirmation' => 'required|string', 'verified' => 'accepted']);
        $seller = $this->confirmSeller($data);
        if ($seller->status !== 'approved') return back()->withErrors(['extra_ad' => 'حساب التاجر غير نشط.']);
        try {
            $deposit = app(SellerBalanceDepositService::class)->confirmAdministrativePayment($seller, (string) $data['amount'],
                $data['payment_reference'], $data['request_key'], (int) auth('admin')->id(), $data['reason']);
            $allocation = $deposit->debt_allocation;
            return back()->with('vendor_account_message', 'تم تأكيد الشحن '.AdministrativeReferenceService::format('seller_balance_deposit', $deposit->id)
                .'. استيفاء الدين: '.number_format((float) ($allocation['debt_recovered'] ?? 0), 2).' ج.م؛ صافي المحفظة: '
                .number_format((float) ($allocation['net_wallet_credit'] ?? $deposit->amount), 2).' ج.م.');
        } catch (DomainException $e) { return back()->withErrors(['funding' => $e->getMessage().' راجع سجل الإيداعات والمرجع قبل إعادة المحاولة.']); }
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $this->authorizeFinance();
        $data = $request->validate(['seller_id' => 'required|integer|exists:sellers,id', 'package_id' => 'required|integer|exists:seller_packages,id',
            'expected_price' => 'required|numeric|min:0', 'request_key' => 'required|uuid', 'confirmation' => 'required|string']);
        $seller = $this->confirmSeller($data);
        if ($seller->status !== 'approved') return back()->withErrors(['subscription' => 'حساب التاجر غير نشط.']);
        $package = SellerPackage::active()->findOrFail($data['package_id']);
        try {
            app(SellerPackagePurchaseService::class)->purchaseWithPaymentsBalance($seller, $package, $data['request_key'],
                (float) $data['expected_price'], (int) auth('admin')->id());
            return back()->with('vendor_account_message', 'اكتملت معاملة الباقة من محفظة التاجر وسُجلت في تاريخ الاشتراكات.');
        } catch (DomainException $e) {
            if ($e->getMessage() === 'seller_operating_balance_insufficient') {
                return back()->withErrors(['subscription' => 'رصيدكم الحالي لا يسمح بشراء الباقة. اشحنوا محفظة المدفوعات أولًا.']);
            }
            return back()->withErrors(['subscription' => $e->getMessage().' راجع الرصيد والسجل قبل إعادة المحاولة.']);
        }
    }

    public function extraAd(Request $request): RedirectResponse
    {
        $this->authorizeFinance();
        abort_unless(config('vendor_advertising.enabled'), 404);
        $data = $request->validate(['seller_id' => 'required|integer|exists:sellers,id', 'subscription_id' => 'required|integer',
            'quantity' => 'required|integer|min:1|max:10000', 'expected_unit_price' => 'required|numeric|gt:0',
            'request_key' => 'required|uuid', 'confirmation' => 'required|string']);
        $seller = $this->confirmSeller($data);
        try {
            app(VendorExtraAdService::class)->purchase($seller, ['subscription_id' => (int) $data['subscription_id'],
                'quantity' => (int) $data['quantity'], 'expected_unit_price' => number_format((float) $data['expected_unit_price'], 2, '.', ''),
                'request_key' => $data['request_key'], 'payment_method' => 'operating_balance'], null, (int) auth('admin')->id());
            return back()->with('vendor_account_message', 'تمت إضافة الإعلان المنفرد وسُجل خصمه من محفظة التاجر.');
        } catch (DomainException $e) {
            if (str_contains($e->getMessage(), 'الرصيد غير كاف') || str_contains($e->getMessage(), 'رصيد المدفوعات غير كاف')) {
                $this->notifyInsufficientBalance($seller->id, $data['request_key']);
                return back()->withErrors(['extra_ad' => 'رصيدكم الحالي لا يسمح بإضافة الإعلان المنفرد. اشحنوا المحفظة أولًا.']);
            }
            return back()->withErrors(['extra_ad' => $e->getMessage().' راجع العملية والسجل قبل إعادة المحاولة.']);
        }
    }

    private function confirmSeller(array $data): Seller
    {
        $seller = Seller::findOrFail($data['seller_id']);
        abort_unless(hash_equals(AdministrativeReferenceService::format('vendor', $seller->id), strtoupper(trim($data['confirmation']))), 422);
        return $seller;
    }

    private function notifyInsufficientBalance(int $sellerId, string $requestKey): void
    {
        if (!Schema::hasTable('notifications') || !Schema::hasColumn('notifications', 'customer_event_key')) return;
        DB::table('notifications')->insertOrIgnore([
            'seller_id' => $sellerId, 'customer_event_key' => 'vendor-extra-ad-insufficient-'.$requestKey,
            'sent_by' => 'admin', 'sent_to' => 'seller', 'title' => 'رصيد الإعلان المنفرد',
            'description' => 'رصيدكم الحالي لا يسمح بإضافة الإعلان المنفرد. يرجى شحن محفظة المدفوعات أولًا.',
            'status' => 1, 'notification_count' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
