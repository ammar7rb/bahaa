@php($gateSuggestion = app(\App\Services\AdminOrderGateService::class)->sellerInsuranceSuggestion($order))
@php($gateAddress = (array) ($order->shipping_address_data ?? []))
<div class="card mb-3 border-0 shadow-sm order-review-panel" id="admin-order-review" dir="rtl">
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap py-3">
        <div><h3 class="mb-1">{{ translate('order_admin_review_title') }}</h3><p class="text-muted mb-0">{{ translate('order_admin_review_help') }}</p></div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="bg-section rounded p-3 h-100"><h5><i class="fi fi-sr-user text-primary me-2"></i>{{ translate('customer') }}</h5><small class="text-muted d-block mb-2">{{ translate('customer_contact_for_this_order') }}</small>
                @if($order->customer)<a href="{{ route('admin.customer.view', ['user_id'=>$order->customer->id]) }}">{{ trim($order->customer->f_name.' '.$order->customer->l_name) }}</a><div class="small mt-2"><bdi>{{ $order->customer->email }}</bdi><br><bdi>{{ $order->customer->phone }}</bdi></div>@else {{ translate('guest_customer') }} @endif
            </div></div>
            <div class="col-md-4"><div class="bg-section rounded p-3 h-100"><h5><i class="fi fi-sr-shop text-primary me-2"></i>{{ translate('seller') }}</h5><small class="text-muted d-block mb-2">{{ translate('seller_receives_order_only_after_approval') }}</small>
                @if($order->seller)<a href="{{ route('admin.vendors.view', ['id'=>$order->seller->id]) }}">{{ $order->seller->shop?->name ?: $order->seller->f_name }}</a><div class="small mt-2"><bdi>{{ $order->seller->email }}</bdi><br><bdi>{{ $order->seller->phone }}</bdi></div>@endif
            </div></div>
            <div class="col-md-4"><div class="bg-section rounded p-3 h-100"><h5><i class="fi fi-sr-marker text-primary me-2"></i>{{ translate('shipping_address') }}</h5><small class="text-muted d-block mb-2">{{ translate('customer_delivery_destination') }}</small>
                <p class="mb-2">{{ implode('، ', array_filter([$gateAddress['state'] ?? '', $gateAddress['city'] ?? '', $gateAddress['address'] ?? ''])) }}</p>
                @if(!empty($gateAddress['latitude']) && !empty($gateAddress['longitude']))<a target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ urlencode($gateAddress['latitude'].','.$gateAddress['longitude']) }}">{{ translate('view_on_map') }}</a>@endif
            </div></div>
        </div>
        @if($order->seller_is === 'seller' && $order->seller)
            @if($order->payment_status !== 'paid')<div class="alert alert-warning">{{ translate('purchase_payment_must_be_paid_before_admin_review') }}</div>@endif
            <form action="{{ route('admin.orders.admin-gate.assignment', $order->id) }}" method="post">
                @csrf
                <div class="row g-3">
                    <div class="col-lg-6 review-decision-section">
                        <h4>{{ translate('seller_insurance_decision') }}</h4>
                        <p class="text-muted">{{ translate('rule_engine_suggestion') }}: <strong>{{ webCurrencyConverter($gateSuggestion['amount'] ?? 0) }}</strong></p>
                        <label>{{ translate('decision_source') }}</label>
                        <select class="form-control mb-3" name="seller_insurance_mode" id="review-insurance-mode"><option value="default">{{ translate('use_default_rule') }}</option><option value="manual">{{ translate('manual_override') }}</option><option value="waive">{{ translate('waive_insurance') }}</option></select>
                        <div class="row g-2" id="review-manual-insurance"><div class="col-6"><label>{{ translate('calculation_type') }}</label><select class="form-control" name="seller_insurance_calculation_type"><option value="percentage">{{ translate('percentage') }}</option><option value="fixed">{{ translate('fixed_amount') }}</option></select></div>
                        <div class="col-6"><label>{{ translate('value') }}</label><input class="form-control" name="seller_insurance_calculation_value" type="number" min="0" step="0.01" value="{{ old('seller_insurance_calculation_value', $order->sellerOrderInsurance?->calculation_value ?? $gateSuggestion['calculation_value'] ?? 0) }}"></div></div>
                        <p class="small text-muted mt-2">{{ translate('zero_seller_insurance_help') }}</p>
                    </div>
                    <div class="col-lg-6 review-decision-section">
                        <h4>{{ translate('shipping_by_seller') }}</h4><input type="hidden" name="shipping_mode" value="seller_shipping">
                        <p class="text-muted">{{ translate('seller_shipping_assignment_help') }}</p>
                        <label>{{ translate('customer_shipping_cost') }}</label><div class="form-control bg-section mb-3">{{ webCurrencyConverter($order->shipping_cost) }}</div>
                        <label>{{ translate('seller_shipping_cost') }}</label><input class="form-control mb-3" type="number" name="shipping_seller_cost" min="0" step="0.01" required value="{{ old('shipping_seller_cost',$order->shipping_seller_cost ?? 0) }}">
                        <label>{{ translate('expected_delivery_date') }}</label><input class="form-control" type="date" name="shipping_expected_delivery_date" value="{{ optional($order->expected_delivery_date)->format('Y-m-d') }}">
                    </div>
                </div>
                <div class="mt-3 text-end"><button class="btn btn-primary px-4" type="submit">{{ translate('save_assignment_decision') }}</button></div>
            </form>
            <form action="{{ route('admin.orders.admin-gate.release', $order->id) }}" method="post" class="border-top mt-4 pt-3">
                @csrf
                <button class="btn btn-primary" type="submit" @disabled($order->commerce_flow_status !== 'admin_assignment_in_progress' || $order->payment_status !== 'paid')>{{ translate('approve_and_send_to_seller') }}</button>
                <a class="btn btn-link" href="{{ route('admin.orders.admin-gate.show', $order->id) }}">{{ translate('decision_history') }}</a>
            </form>
        @endif
    </div>
</div>
<style>
    .order-review-panel .review-decision-section {
        border: 1px solid var(--bs-border-color, #344766);
        border-radius: 14px;
        background: var(--bs-tertiary-bg, rgba(82, 123, 185, .08));
        padding: 20px;
    }
    .order-review-panel .review-decision-section h4 {
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--bs-border-color, #344766);
    }
    .order-review-panel label { display: block; font-weight: 600; margin-bottom: 6px; }
    .order-review-panel .form-control { min-height: 42px; }
    .order-review-panel .bg-section { border: 1px solid var(--bs-border-color, #344766); }
    @media (max-width: 991px) { .order-review-panel .review-decision-section { padding: 16px; } }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mode = document.getElementById('review-insurance-mode');
    const manual = document.getElementById('review-manual-insurance');
    if (mode && manual) {
        const sync = () => { manual.hidden = mode.value !== 'manual'; };
        mode.addEventListener('change', sync);
        sync();
    }
});
</script>
