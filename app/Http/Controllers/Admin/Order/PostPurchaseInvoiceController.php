<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\PostPurchaseInvoice;
use App\Services\PostPurchaseInvoiceService;
use Brian2694\Toastr\Facades\Toastr;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Administrative gate between second payment and seller visibility. */
class PostPurchaseInvoiceController extends Controller
{
    public function __construct(private readonly PostPurchaseInvoiceService $invoices)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $invoices = PostPurchaseInvoice::query()
            ->with(['order.seller.shop', 'customer'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);
                $query->where(function ($nested) use ($search) {
                    $nested->where('id', $search)
                        ->orWhere('order_id', $search)
                        ->orWhereHas('customer', fn ($customers) => $customers
                            ->where('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->appends($request->query());

        $settings = BusinessSetting::query()->whereIn('type', [
            'feature_post_purchase_tax_invoice_v3', 'commerce_contract_version',
            'customer_order_insurance_status', 'customer_second_invoice_tax_enabled',
            'post_purchase_invoice_due_days',
        ])->pluck('value', 'type')->all();

        return view('admin-views.order.post-purchase-invoices.index', compact('invoices', 'status', 'settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_due_days' => 'required|integer|min:1|max:30',
        ]);
        if ($validator->fails()) {
            Toastr::error($validator->errors()->first());
            return back();
        }
        $values = [
            'post_purchase_invoice_due_days' => (string) $request->payment_due_days,
        ];
        foreach ($values as $type => $value) {
            BusinessSetting::query()->updateOrCreate(['type' => $type], ['value' => $value]);
        }

        Toastr::success(translate('configuration_updated_successfully'));
        return back();
    }

    public function approveOffline(Request $request, int $invoiceId): RedirectResponse
    {
        $invoice = PostPurchaseInvoice::query()->findOrFail($invoiceId);
        $validator = Validator::make($request->all(), ['note' => 'nullable|string|max:1000']);
        if ($validator->fails()) {
            Toastr::error($validator->errors()->first());
            return back();
        }

        try {
            if ($invoice->status !== PostPurchaseInvoice::STATUS_AWAITING_REVIEW) {
                throw new DomainException('post_purchase_invoice_is_not_waiting_for_offline_review');
            }
            $summary = $this->invoices->paymentSummary($invoice);
            $invoice = $this->invoices->markExternalPaymentPaid(
                $invoice,
                (float) $summary['external_amount_due'],
                'offline_payment',
                'offline-post-purchase-'.$invoice->id.'-'.now()->timestamp,
            );
            $metadata = $invoice->metadata ?: [];
            $metadata['offline_reviewed_by'] = auth('admin')->id();
            $metadata['offline_review_note'] = $request->note;
            $metadata['offline_reviewed_at'] = now()->toIso8601String();
            $invoice->update(['metadata' => $metadata]);
            Toastr::success(translate('payment_status_updated_successfully'));
        } catch (DomainException $exception) {
            Toastr::warning(translate($exception->getMessage()));
        }

        return back();
    }

    public function releaseToSeller(Request $request, int $invoiceId): RedirectResponse
    {
        $invoice = PostPurchaseInvoice::query()->findOrFail($invoiceId);
        $validator = Validator::make($request->all(), ['note' => 'nullable|string|max:1000']);
        if ($validator->fails()) {
            Toastr::error($validator->errors()->first());
            return back();
        }

        try {
            $this->invoices->releaseToSeller($invoice, (int) auth('admin')->id(), $request->note);
            Toastr::success(translate('order_is_ready_for_admin_shipping_and_insurance_assignment'));
            return redirect()->route('admin.orders.admin-gate.show', $invoice->order_id);
        } catch (DomainException $exception) {
            Toastr::warning(translate($exception->getMessage()));
        }

        return back();
    }
}
