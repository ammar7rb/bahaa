@php($gateSuggestion = app(\App\Services\AdminOrderGateService::class)->sellerInsuranceSuggestion($order))
@php($gateAddress = (array) ($order->shipping_address_data ?? []))
<div class="card mb-3" id="admin-order-review">
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap">
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
                    <div class="col-lg-6">
                        <h4>{{ translate('seller_insurance_decision') }}</h4>
                        <p class="text-muted">{{ translate('rule_engine_suggestion') }}: <strong>{{ webCurrencyConverter($gateSuggestion['amount'] ?? 0) }}</strong></p>
                        <label>{{ translate('decision_source') }}</label>
                        <select class="form-control mb-3" name="seller_insurance_mode"><option value="default">{{ translate('use_default_rule') }}</option><option value="manual">{{ translate('manual_override') }}</option><option value="waive">{{ translate('waive_insurance') }}</option></select>
                        <div class="row g-2"><div class="col-6"><label>{{ translate('calculation_type') }}</label><select class="form-control" name="seller_insurance_calculation_type"><option value="percentage">{{ translate('percentage') }}</option><option value="fixed">{{ translate('fixed_amount') }}</option></select></div>
                        <div class="col-6"><label>{{ translate('value') }}</label><input class="form-control" name="seller_insurance_calculation_value" type="number" min="0" step="0.01" value="{{ old('seller_insurance_calculation_value', $order->sellerOrderInsurance?->calculation_value ?? $gateSuggestion['calculation_value'] ?? 0) }}"></div></div>
                        <p class="small text-muted mt-2">{{ translate('zero_seller_insurance_help') }}</p>
                        <label>{{ translate('override_reason') }}</label><input class="form-control" name="seller_insurance_override_reason" value="{{ old('seller_insurance_override_reason') }}">
                    </div>
                    <div class="col-lg-6">
                        <h4>{{ translate('shipping_by_seller') }}</h4><input type="hidden" name="shipping_mode" value="seller_shipping">
                        <p class="text-muted">{{ translate('seller_shipping_assignment_help') }}</p>
                        <label>{{ translate('customer_shipping_cost') }}</label><div class="form-control bg-section mb-3">{{ webCurrencyConverter($order->shipping_cost) }}</div>
                        <label>{{ translate('seller_shipping_cost') }}</label><input class="form-control mb-3" type="number" name="shipping_seller_cost" min="0" step="0.01" required value="{{ old('shipping_seller_cost',$order->shipping_seller_cost ?? 0) }}">
                        <label>{{ translate('expected_delivery_date') }}</label><input class="form-control" type="date" name="shipping_expected_delivery_date" value="{{ optional($order->expected_delivery_date)->format('Y-m-d') }}">
                    </div>
                    <div class="col-12"><label>{{ translate('decision_note') }}</label><textarea class="form-control" name="decision_note" rows="2" required>{{ old('decision_note') }}</textarea></div>
                </div>
                <button class="btn btn-outline-primary mt-3" type="submit">{{ translate('save_assignment_decision') }}</button>
            </form>
            <form action="{{ route('admin.orders.admin-gate.release', $order->id) }}" method="post" class="border-top mt-4 pt-3">
                @csrf
                <label>{{ translate('release_note') }}</label><input class="form-control mb-3" name="note" required>
                <button class="btn btn-primary" type="submit" @disabled($order->commerce_flow_status !== 'admin_assignment_in_progress' || $order->payment_status !== 'paid')>{{ translate('approve_and_send_to_seller') }}</button>
                <a class="btn btn-link" href="{{ route('admin.orders.admin-gate.show', $order->id) }}">{{ translate('decision_history') }}</a>
            </form>
        @endif
    </div>
</div>
