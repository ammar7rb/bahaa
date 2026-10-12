<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\VendorWithdrawMethodInfo;
use App\Models\WithdrawalMethod;
use App\Models\WithdrawRequest;
use App\Services\SellerLedgerService;
use App\Services\SellerWithdrawalMethodService;
use App\Services\SellerWithdrawalService;
use App\Services\VendorFinancialWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorFinanceWorkspaceController extends Controller
{
    private function seller(Request $request): Seller
    {
        $seller = $request->seller ?? auth('seller')->user();
        abort_unless($seller instanceof Seller, 401);
        return $seller;
    }

    public function methods(Request $request)
    {
        $seller = $this->seller($request);
        $data = ['methods' => VendorWithdrawMethodInfo::where('user_id', $seller->id)->latest('id')->get(),
            'definitions' => WithdrawalMethod::where('is_active', true)->get()];
        return $request->is('api/*') ? response()->json($data)->header('Cache-Control', 'private, no-store') : view('vendor-views.account.withdrawal-methods', compact('data'));
    }

    public function saveMethod(Request $request)
    {
        $seller = $this->seller($request);
        $input = $request->validate(['id' => 'nullable|integer', 'withdraw_method_id' => 'required|integer', 'method_name' => 'required|string|max:100', 'method_info' => 'required|array|max:20', 'method_info.*' => 'nullable|string|max:191']);
        $definition = WithdrawalMethod::whereKey($input['withdraw_method_id'])->where('is_active', true)->firstOrFail();
        $service = app(SellerWithdrawalMethodService::class);
        $type = $service->resolveMethodType(null, $definition->method_name);
        $fields = [];
        foreach ($definition->method_fields ?: [] as $field) {
            $key = $field['input_name'];
            $value = trim($input['method_info'][$key] ?? '');
            if (($field['is_required'] ?? false) && $value === '') throw ValidationException::withMessages(['method_info.'.$key => translate('required_field')]);
            $fields[$key] = $value;
        }
        if (!$fields || !collect($fields)->contains(fn ($value) => $value !== '')) throw ValidationException::withMessages(['method_info' => translate('recipient_details_required')]);
        DB::transaction(function () use ($input, $seller, $definition, $fields, $service, $type) {
            app(SellerLedgerService::class)->lockSellerFinancialState($seller->id);
            $method = isset($input['id']) ? VendorWithdrawMethodInfo::where('user_id', $seller->id)->lockForUpdate()->findOrFail($input['id']) : new VendorWithdrawMethodInfo();
            $method->fill($service->prepareSubmission(['user_id' => $seller->id, 'withdraw_method_id' => $definition->id, 'method_name' => $input['method_name'], 'method_info' => $fields], $type))->save();
        });
        return $request->is('api/*') ? response()->json(['message' => translate('withdrawal_method_pending_review')]) : back()->with('success', translate('withdrawal_method_pending_review'));
    }

    public function disableMethod(Request $request, int $method)
    {
        $seller = $this->seller($request);
        app(SellerWithdrawalMethodService::class)->setActive($method, $seller->id, false);
        return $request->is('api/*') ? response()->json(['status' => 'disabled']) : back();
    }

    public function invoices(Request $request)
    {
        $request->validate(['page' => 'nullable|integer|min:1']);
        $data = app(VendorFinancialWorkspaceService::class)->invoices($this->seller($request)->id, $request->integer('page', 1));
        return $request->is('api/*') ? response()->json($data)->header('Cache-Control', 'private, no-store') : view('vendor-views.finance.invoices', compact('data'));
    }

    public function invoiceDocument(Request $request, int $order)
    {
        $seller = $this->seller($request);
        $order = \App\Models\Order::sellerAccessible()->where(['seller_id' => $seller->id, 'seller_is' => 'seller'])->findOrFail($order);
        abort_unless(app(\App\Services\SellerOrderVisibilityService::class)->canSellerView($order), 403);
        $order->load(['details', 'customer', 'shipping', 'seller']);
        $data = ['order' => $order, 'vendor' => $seller->gst, 'invoiceSettings' => getWebConfig('invoice_settings'),
            'companyPhone' => getWebConfig('company_phone'), 'companyEmail' => getWebConfig('company_email'),
            'companyName' => getWebConfig('company_name'), 'companyWebLogo' => getWebConfig('company_web_logo')];
        $pdf = new \Mpdf\Mpdf(['default_font' => 'FreeSerif', 'mode' => 'utf-8', 'format' => [190, 250], 'autoLangToFont' => true]);
        $pdf->autoScriptToLang = true;
        $pdf->WriteHTML(view('vendor-views.order.invoice', $data)->render());
        return response($pdf->Output('', \Mpdf\Output\Destination::STRING_RETURN), 200,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="invoice-'.$order->id.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function withdraw(Request $request)
    {
        $input = $request->validate(['saved_method_id' => 'required|integer', 'amount' => 'required|numeric|min:0.01', 'request_key' => 'required|uuid']);
        try {
            $result = app(SellerWithdrawalService::class)->submit($this->seller($request)->id, $input['saved_method_id'], (float) \App\Utils\Convert::usd($input['amount']), $input['request_key']);
        } catch (\DomainException | \InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => translate($exception->getMessage())]);
        }
        return $request->is('api/*') ? response()->json(['request' => $result]) : back()->with('success', translate('withdraw_request_has_been_sent'));
    }
}
