@extends('layouts.back-end.app')

@section('title', translate('post_purchase_invoices'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div><h2 class="page-header-title mb-1">{{ translate('post_purchase_invoices') }}</h2><p class="text-muted mb-0">{{ translate('tax_and_customer_insurance_must_be_paid_before_an_order_can_be_released_to_the_seller') }}</p></div>
        </div>
        <div class="card mb-3"><div class="card-header border-0"><h5 class="mb-1">إعدادات الفاتورة الثانية للعملاء</h5><p class="text-muted fs-12 mb-0">تُفعّل تلقائيًا مع تأمين العملاء أو ضريبة العملاء. يظهر التأمين وحده أو التأمين والضريبة في فاتورة واحدة حسب الإعدادات.</p></div><div class="card-body pt-0">
            <div class="d-flex flex-wrap gap-2 mb-3"><span class="badge badge-soft-{{ ($settings['customer_order_insurance_status'] ?? '0') == '1' ? 'success' : 'danger' }}">تأمين العملاء: {{ ($settings['customer_order_insurance_status'] ?? '0') == '1' ? 'مفعل' : 'متوقف' }}</span><span class="badge badge-soft-{{ ($settings['customer_second_invoice_tax_enabled'] ?? '0') == '1' ? 'success' : 'danger' }}">ضريبة العملاء 14%: {{ ($settings['customer_second_invoice_tax_enabled'] ?? '0') == '1' ? 'مفعلة' : 'متوقفة' }}</span><a href="{{ route('admin.insurance-center.index', ['tab'=>'customers']) }}">تعديل التأمين والضريبة من مركز تأمين العملاء</a></div>
            <form method="post" action="{{ route('admin.orders.post-purchase-invoices.settings') }}" class="row g-3">@csrf
                <div class="col-md-3"><label class="form-label">{{ translate('second_payment_due_days') }}</label><input class="form-control" type="number" min="1" max="30" name="payment_due_days" value="{{ $settings['post_purchase_invoice_due_days'] ?? 2 }}"></div>
                <div class="col-md-3 d-flex align-items-end"><button class="btn btn--primary w-100">{{ translate('save_changes') }}</button></div>
            </form>
        </div></div>
        <div class="card mb-3"><div class="card-body">
            <form class="row g-2" method="get"><div class="col-md-5"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ translate('search_by_invoice_order_or_customer') }}"></div><div class="col-md-4"><select class="form-select" name="status"><option value="">{{ translate('all') }}</option>@foreach([\App\Models\PostPurchaseInvoice::STATUS_PENDING, \App\Models\PostPurchaseInvoice::STATUS_AWAITING_REVIEW, \App\Models\PostPurchaseInvoice::STATUS_PAID, \App\Models\PostPurchaseInvoice::STATUS_EXPIRED] as $item)<option value="{{ $item }}" @selected($status === $item)>{{ translate($item) }}</option>@endforeach</select></div><div class="col-md-3"><button class="btn btn--primary w-100">{{ translate('filter') }}</button></div></form>
        </div></div>
        <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>#</th><th>{{ translate('customer') }}</th><th>{{ translate('order') }}</th><th>{{ translate('tax') }}</th><th>{{ translate('insurance') }}</th><th>{{ translate('status') }}</th><th>{{ translate('action') }}</th></tr></thead><tbody>
        @forelse($invoices as $invoice)
            @php($summary = app(\App\Services\PostPurchaseInvoiceService::class)->paymentSummary($invoice))
            <tr><td>{{ $invoice->id }}</td><td><div>{{ $invoice->customer?->f_name }} {{ $invoice->customer?->l_name }}</div><small>{{ $invoice->customer?->phone }}</small></td><td>#{{ $invoice->order_id }}</td><td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->tax_amount)) }}</td><td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $invoice->insurance_amount)) }}</td><td><span class="badge badge-soft-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'expired' ? 'danger' : 'warning') }}">{{ translate($invoice->status) }}</span><small class="d-block mt-1">{{ translate('external_due') }}: {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $summary['external_amount_due'])) }}</small></td><td><div class="d-flex flex-column gap-1">
                @if($invoice->status === \App\Models\PostPurchaseInvoice::STATUS_AWAITING_REVIEW)<form method="post" action="{{ route('admin.orders.post-purchase-invoices.approve-offline', $invoice->id) }}">@csrf<input class="form-control form-control-sm mb-1" name="note" placeholder="{{ translate('review_note') }}"><button class="btn btn-sm btn-outline-primary w-100">{{ translate('approve_offline_payment') }}</button></form>@endif
                @if($invoice->status === \App\Models\PostPurchaseInvoice::STATUS_PAID && $invoice->order?->post_purchase_status !== \App\Services\PostPurchaseInvoiceService::ORDER_STATUS_RELEASED)<form method="post" action="{{ route('admin.orders.post-purchase-invoices.release-to-seller', $invoice->id) }}">@csrf<input class="form-control form-control-sm mb-1" name="note" placeholder="{{ translate('admin_note') }}"><button class="btn btn-sm btn--primary w-100">{{ translate('release_to_seller') }}</button></form>@endif
            </div></td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-5">{{ translate('no_data_found') }}</td></tr>@endforelse
        </tbody></table></div><div class="card-footer border-0">{{ $invoices->links() }}</div></div>
    </div>
@endsection
