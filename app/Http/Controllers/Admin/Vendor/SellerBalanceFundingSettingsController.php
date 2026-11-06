<?php

namespace App\Http\Controllers\Admin\Vendor;

use App\Http\Controllers\Controller;
use App\Models\OfflinePaymentMethod;
use App\Services\SellerBalanceFundingSettingsService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

use function App\Utils\payment_gateways;

class SellerBalanceFundingSettingsController extends Controller
{
    public function __construct(private readonly SellerBalanceFundingSettingsService $settingsService)
    {
    }

    public function index(): View
    {
        return view('admin-views.vendor.balance-funding-settings.index', [
            'salesTransferSettings' => app(\App\Services\SellerCashTransferService::class)->settings(),
            'settings' => $this->settingsService->get(),
            'paymentGateways' => payment_gateways(),
            'offlinePaymentMethods' => OfflinePaymentMethod::query()->where('status', 1)->get(),
        ]);
    }

    public function updateSalesTransfer(Request $request): RedirectResponse
    {
        abort_unless(\App\Utils\Helpers::module_permission_check('people'), 403);
        $input = $request->validate(['type' => 'required|in:percent,fixed', 'value' => 'required|numeric|min:0|max:1000000']);
        if ($input['type'] === 'percent' && (float) $input['value'] > 100) {
            return back()->withErrors(['value' => translate('sales_cash_transfer_percent_invalid')]);
        }
        $value = $input['type'] === 'fixed' ? (float) \App\Utils\Convert::usd($input['value']) : (float) $input['value'];
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $input, $value) {
            $setting = \App\Models\BusinessSetting::query()->where('type', \App\Services\SellerCashTransferService::SETTING)->lockForUpdate()->first();
            if (!$setting) $setting = new \App\Models\BusinessSetting(['type' => \App\Services\SellerCashTransferService::SETTING]);
            $setting->value = json_encode(['enabled' => $request->boolean('enabled'), 'type' => $input['type'], 'value' => $value]);
            $setting->save();
        });
        ToastMagic::success(translate('sales_cash_transfer_settings_saved'));
        return back();
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'min_amount' => 'required|numeric|min:0.01',
            'max_amount' => 'nullable|numeric|min:0',
            'digital_gateways' => 'nullable|array',
            'digital_gateways.*' => 'string|max:100',
            'company_accounts' => 'nullable|array',
            'company_accounts.*.bank_name' => 'nullable|string|max:191',
            'company_accounts.*.account_name' => 'nullable|string|max:191',
            'company_accounts.*.account_number' => 'nullable|string|max:191',
            'company_accounts.*.iban' => 'nullable|string|max:191',
            'company_accounts.*.notes' => 'nullable|string|max:1000',
        ]);

        $gatewayKeys = collect(payment_gateways())->pluck('key_name')->map(fn ($key) => (string) $key)->all();
        $selectedGateways = array_values(array_intersect($validated['digital_gateways'] ?? [], $gatewayKeys));
        $accounts = collect($validated['company_accounts'] ?? [])->map(function (array $account) {
            return collect($account)->map(fn ($value) => is_string($value) ? trim($value) : $value)->all();
        })->filter(fn (array $account) => collect($account)->contains(fn ($value) => $value !== null && $value !== ''))->values()->all();

        try {
            $this->settingsService->update([
                'offline_enabled' => $request->boolean('offline_enabled'),
                'digital_enabled' => $request->boolean('digital_enabled'),
                'min_amount' => $validated['min_amount'],
                'max_amount' => $validated['max_amount'] ?? 0,
                'digital_gateways' => $selectedGateways,
                'company_accounts' => $accounts,
            ]);
            ToastMagic::success(translate('seller_balance_funding_settings_updated'));
        } catch (DomainException $exception) {
            ToastMagic::error(translate($exception->getMessage()));
        }

        return back();
    }
}
