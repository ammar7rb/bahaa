@extends('layouts.admin.app')

@section('title', translate('admin_order_gate').' ORD-'.$order->id)

@section('content')
@php($openInvestigation = $order->riskInvestigations->firstWhere('status','open'))
@php($postPurchaseInvoice = $order->postPurchaseInvoice)
@php($sellerResponseStatus = $order->seller_shipping_response_status)
<div class="content container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h1 class="page-header-title mb-1">{{ translate('admin_order_gate') }} — ORD-{{ $order->id }}</h1><p class="text-muted mb-0">{{ translate('complete_insurance_and_shipping_before_release') }}</p></div>
        <a class="btn btn-outline-secondary" href="{{ route('admin.orders.admin-gate.index') }}">{{ translate('back_to_queue') }}</a>
    </div>

    @if($openInvestigation)<div class="alert alert-danger"><strong>{{ translate('order_is_under_investigation') }}</strong><br>{{ $openInvestigation->reason }}</div>@endif

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><span class="text-muted">{{ translate('purchase_amount') }}</span><h4>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $order->order_amount)) }}</h4><span class="badge bg-{{ $order->payment_status==='paid'?'success':'warning' }}">{{ translate($order->payment_status) }}</span></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body">
            <span class="text-muted">{{ translate('customer_insurance') }}</span>
            <h4>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $postPurchaseInvoice?->insurance_amount ?? $order->insurance?->amount ?? 0)) }}</h4>
            @if($postPurchaseInvoice)
                <div class="small text-muted">{{ translate('tax') }}: {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $postPurchaseInvoice->tax_amount)) }}</div>
                <div class="small text-muted">{{ translate('total') }}: {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $postPurchaseInvoice->total_amount)) }}</div>
                <span class="badge bg-{{ $postPurchaseInvoice->status==='paid'?'success':($postPurchaseInvoice->status==='awaiting_review'?'warning':'secondary') }}">{{ translate($postPurchaseInvoice->status) }}</span>
                @if(data_get($postPurchaseInvoice->metadata, 'offline_payment.payment_proof.image_name'))
                    <div class="small text-success mt-1">{{ translate('offline_payment_proof_submitted') }}</div>
                @endif
            @else
                <span class="badge bg-{{ $order->insurance?->payment_status==='paid'?'success':'secondary' }}">{{ translate($order->insurance?->payment_status??'not_required') }}</span>
            @endif
        </div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><span class="text-muted">{{ translate('seller_insurance') }}</span><h4>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $order->sellerOrderInsurance?->amount??($suggestion['amount']??0))) }}</h4><span class="badge bg-info">{{ translate($order->sellerOrderInsurance?->status??'suggested') }}</span></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><span class="text-muted">{{ translate('shipping_decision') }}</span><h4>{{ translate($order->shipping_fulfillment_mode??'pending') }}</h4><span class="badge bg-{{ ($order->shipping_assignment_status??'pending')==='assigned'?'success':'warning' }}">{{ translate($order->shipping_assignment_status??'pending') }}</span>@if($sellerResponseStatus)<div class="small mt-2">{{ translate('seller_response') }}: <strong>{{ translate($sellerResponseStatus) }}</strong></div>@endif@if(in_array($sellerResponseStatus, ['pending','overdue'], true))<div class="small text-{{ $sellerResponseStatus === 'overdue' ? 'danger' : 'warning' }} mt-2">{{ translate($sellerResponseStatus === 'overdue' ? 'seller_has_not_started_preparing_overdue' : 'seller_has_not_started_preparing') }}</div>@endif@if($order->seller_shipping_response_due_at)<div class="small text-muted">{{ translate('due_date') }}: {{ dateFormat($order->seller_shipping_response_due_at) }}</div>@endif@if($order->seller_shipping_support_ticket_id)<div class="small text-danger">{{ translate('support_ticket') }} #{{ $order->seller_shipping_support_ticket_id }}</div>@endif</div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-4">
            <div><span class="text-muted d-block">{{ translate('customer') }}</span><strong>{{ trim(($order->customer?->f_name ?? '').' '.($order->customer?->l_name ?? '')) ?: translate('guest_customer') }}</strong></div>
            <div><span class="text-muted d-block">{{ translate('email') }}</span><strong>{{ $order->customer?->email ?? '—' }}</strong></div>
            <div><span class="text-muted d-block">{{ translate('phone') }}</span><strong>{{ $order->customer?->phone ?? '—' }}</strong></div>
            <div><span class="text-muted d-block">{{ translate('order_items') }}</span><strong>{{ $order->orderDetails->count() }}</strong></div>
            @if($postPurchaseInvoice && data_get($postPurchaseInvoice->metadata, 'offline_payment.payment_proof.image_name'))
                @php($proof = data_get($postPurchaseInvoice->metadata, 'offline_payment.payment_proof'))
                <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="{{ storageLink('offline-payment/post-purchase-invoice', $proof['image_name'], $proof['storage'] ?? 'public') }}">{{ translate('view_payment_proof') }}</a>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <form class="card mb-3 border-0 shadow-sm" dir="rtl" method="post" action="{{ route('admin.orders.admin-gate.assignment',$order->id) }}">@csrf
                <div class="card-header"><h4 class="mb-1">{{ translate('insurance_and_shipping_decision') }}</h4><p class="text-muted mb-0">{{ translate('admin_assignment_form_help') }}</p></div>
                <div class="card-body">
                    @if($order->sellerOrderInsurance && in_array($order->sellerOrderInsurance->status, ['paid','waived']))
                        <div class="alert alert-info">{{ translate('seller_insurance_is_already_paid_only_shipping_terms_will_be_updated') }}</div>
                    @endif
                    <h5 class="border-bottom pb-2 mb-3">{{ translate('seller_insurance_decision') }}</h5>
                    <div class="alert alert-info">{{ translate('rule_engine_suggestion') }}: <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $suggestion['amount']??0)) }}</strong> — {{ translate($suggestion['calculation_type']??'percentage') }} {{ $suggestion['calculation_value']??0 }}</div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><label class="form-label">{{ translate('decision_source') }}</label><select class="form-control" id="seller-insurance-mode" name="seller_insurance_mode" required><option value="default">{{ translate('use_default_rule') }}</option><option value="manual">{{ translate('manual_override') }}</option><option value="waive">{{ translate('waive_insurance') }}</option></select></div>
                        <div class="col-md-4 manual-insurance"><label class="form-label">{{ translate('calculation_type') }}</label><select class="form-control" name="seller_insurance_calculation_type"><option value="percentage">{{ translate('percentage') }}</option><option value="fixed">{{ translate('fixed_amount') }}</option></select></div>
                        <div class="col-md-4 manual-insurance"><label class="form-label">{{ translate('value') }}</label><input class="form-control" type="number" min="0.01" step="0.01" name="seller_insurance_calculation_value"></div>
                    </div>
                    <hr><h5 class="border-bottom pb-2 mb-3">{{ translate('shipping_decision') }}</h5>
                    <div class="row g-2">
                        <div class="col-md-4"><label class="form-label">{{ translate('shipping_party') }}</label><select class="form-control" id="shipping-mode" name="shipping_mode" required><option value="admin_shipping">{{ translate('company_shipping') }}</option><option value="seller_shipping">{{ translate('seller_shipping') }}</option></select></div>
                        <div class="col-md-4 admin-shipping"><label class="form-label">{{ translate('shipping_company') }}</label><select class="form-control" name="shipping_method_id"><option value="">{{ translate('select') }}</option>@foreach($shippingMethods as $method)<option value="{{ $method->id }}">{{ $method->title }} — {{ $method->cost }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">{{ translate('customer_shipping_cost') }}</label><input class="form-control" type="number" min="0" step="0.01" name="shipping_customer_cost" value="{{ $order->shipping_customer_cost }}"></div>
                        <div class="col-md-4"><label class="form-label">{{ translate('seller_shipping_cost') }}</label><input class="form-control" type="number" min="0" step="0.01" name="shipping_seller_cost" value="{{ $order->shipping_seller_cost }}"></div>
                        <div class="col-md-4"><label class="form-label">{{ translate('seller_entitlement') }}</label><input class="form-control" type="number" min="0" step="0.01" name="shipping_seller_entitlement" value="{{ $order->shipping_seller_entitlement }}"></div>
                        <div class="col-md-4"><label class="form-label">{{ translate('expected_delivery_date') }}</label><input class="form-control" type="date" name="shipping_expected_delivery_date" value="{{ optional($order->expected_delivery_date)->format('Y-m-d') }}"></div>
                        <div class="col-md-6"><label class="form-label">{{ translate('tracking_number') }}</label><input class="form-control" name="shipping_tracking_number" value="{{ $order->third_party_delivery_tracking_id }}"></div>
                        <div class="col-md-6"><label class="form-label">{{ translate('service_name') }}</label><input class="form-control" name="shipping_service_name" value="{{ $order->delivery_service_name }}"></div>
                        <div class="col-12"><label class="form-label">{{ translate('operational_address_snapshot') }}</label><div class="form-control bg-light" style="height:auto">{{ data_get((array)$order->shipping_address_data,'address','—') }} — {{ data_get((array)$order->shipping_address_data,'city','') }}</div></div>
                    </div>
                </div>
                <div class="card-footer"><button class="btn btn-primary">{{ translate('save_assignment_decision') }}</button></div>
            </form>

            <div class="card mb-3"><div class="card-header"><h4 class="mb-0">{{ translate('decision_history') }}</h4></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>{{ translate('date') }}</th><th>{{ translate('action') }}</th><th>{{ translate('admin') }}</th><th>{{ translate('status') }}</th><th>{{ translate('note') }}</th></tr></thead><tbody>@forelse($order->adminGateDecisions as $decision)<tr><td>{{ dateFormat($decision->created_at) }}</td><td>{{ translate($decision->action) }}</td><td>{{ $decision->admin?->name??'—' }}</td><td>{{ translate($decision->status_after) }}</td><td>{{ $decision->note }}</td></tr>@empty<tr><td colspan="5" class="text-center">{{ translate('no_data_found') }}</td></tr>@endforelse</tbody></table></div></div>
        </div>
        <div class="col-xl-4">
            <form class="card mb-3 border-0 shadow-sm" dir="rtl" method="post" action="{{ route('admin.orders.admin-gate.release',$order->id) }}">@csrf<div class="card-header"><h4 class="mb-0">{{ translate('release_order_to_seller') }}</h4></div><div class="card-body"><p class="text-muted">{{ translate('release_requires_complete_admin_decision') }}</p><button class="btn btn-success w-100" @disabled($openInvestigation)>{{ translate('approve_and_send_restricted_order') }}</button></div></form>

            @if(in_array($sellerResponseStatus, ['pending','overdue'], true))
            <form class="card border-warning mb-3" method="post" action="{{ route('admin.orders.admin-gate.seller-follow-up',$order->id) }}">@csrf
                <div class="card-header"><h4 class="mb-0">{{ translate('contact_seller_about_shipping') }}</h4></div>
                <div class="card-body"><p class="text-muted mb-2">{{ translate($sellerResponseStatus === 'overdue' ? 'seller_has_not_started_preparing_overdue' : 'seller_has_not_started_preparing') }}</p><textarea class="form-control mb-3" name="message" rows="3" required placeholder="{{ translate('message') }}"></textarea><button class="btn btn-warning w-100">{{ translate('send_message') }}</button></div>
            </form>
            @endif

            @if(!$openInvestigation)
            <form class="card border-danger mb-3" method="post" action="{{ route('admin.orders.admin-gate.investigation.open',$order->id) }}">@csrf<div class="card-header"><h4 class="text-danger mb-0">{{ translate('open_investigation') }}</h4></div><div class="card-body"><label class="form-label">{{ translate('risk_level') }}</label><select class="form-control mb-2" name="risk_level">@foreach(['low','medium','high','critical'] as $level)<option value="{{ $level }}">{{ translate($level) }}</option>@endforeach</select><label class="form-label">{{ translate('reason') }}</label><textarea class="form-control mb-3" name="reason" required></textarea><button class="btn btn-outline-danger w-100">{{ translate('freeze_and_open_investigation') }}</button></div></form>
            @else
            <div class="card border-danger mb-3"><div class="card-header"><h4 class="text-danger mb-0">{{ translate('investigation_center') }}</h4></div><div class="card-body">
                <p><strong>{{ translate('reason') }}:</strong> {{ $openInvestigation->reason }}</p>
                <form method="post" action="{{ route('admin.orders.admin-gate.investigation.message',$openInvestigation->id) }}" class="mb-3">@csrf<label class="form-label">{{ translate('recipient') }}</label><select class="form-control mb-2" name="recipient_type"><option value="customer">{{ translate('customer') }}</option><option value="seller">{{ translate('vendor') }}</option></select><textarea class="form-control mb-2" name="message" required placeholder="{{ translate('message') }}"></textarea><button class="btn btn-outline-primary w-100">{{ translate('send_message') }}</button></form>
                <form method="post" action="{{ route('admin.orders.admin-gate.investigation.resolve',$openInvestigation->id) }}">@csrf<label class="form-label">{{ translate('final_decision') }}</label><select class="form-control mb-2" name="action"><option value="resume">{{ translate('resume_order') }}</option><option value="cancel_and_refund">{{ translate('cancel_and_refund_purchase') }}</option><option value="confiscate_customer_and_refund">{{ translate('confiscate_customer_insurance_and_refund') }}</option><option value="confiscate_seller_and_resume">{{ translate('confiscate_seller_insurance_and_resume') }}</option><option value="confiscate_both_and_refund">{{ translate('confiscate_both_insurances_and_refund') }}</option></select><textarea class="form-control mb-2" name="note" required placeholder="{{ translate('mandatory_decision_reason') }}"></textarea><button class="btn btn-danger w-100">{{ translate('save_final_decision') }}</button></form>
            </div></div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const insuranceMode = document.getElementById('seller-insurance-mode');
    const shippingMode = document.getElementById('shipping-mode');
    function syncInsurance(){document.querySelectorAll('.manual-insurance').forEach(el=>el.style.display=insuranceMode.value==='manual'?'block':'none');}
    function syncShipping(){document.querySelectorAll('.admin-shipping').forEach(el=>el.style.display=shippingMode.value==='admin_shipping'?'block':'none');}
    insuranceMode.addEventListener('change',syncInsurance);shippingMode.addEventListener('change',syncShipping);syncInsurance();syncShipping();
});
</script>
@endpush
