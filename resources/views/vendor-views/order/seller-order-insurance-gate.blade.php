@extends('layouts.vendor.app')
@section('title', translate('Seller_Order_Insurance'))
@push('css_or_js')
<style>
.insurance-gate{max-width:1050px;margin-inline:auto}.insurance-gate__hero{overflow:hidden;border:1px solid rgba(39,99,235,.2);background:linear-gradient(135deg,rgba(39,99,235,.11),rgba(20,184,166,.06));border-radius:22px}.insurance-gate__icon,.insurance-pay-option__icon{display:grid;place-items:center;background:rgba(39,99,235,.13);color:#2763eb}.insurance-gate__icon{width:62px;height:62px;border-radius:18px;font-size:27px}.insurance-gate__blur{position:relative;min-height:170px;border-radius:18px;overflow:hidden;background:var(--bs-light,#f4f7fb);border:1px solid rgba(148,163,184,.25)}.insurance-gate__blur-content{filter:blur(10px);opacity:.4;user-select:none;pointer-events:none;padding:28px}.insurance-gate__lock{position:absolute;inset:0;display:grid;place-items:center;text-align:center;padding:20px;background:rgba(255,255,255,.28);backdrop-filter:blur(2px)}.insurance-pay-option{height:100%;border:1px solid rgba(148,163,184,.3);border-radius:16px;padding:18px;background:var(--bs-body-bg,#fff);transition:.2s}.insurance-pay-option:hover,.insurance-pay-option.is-active{transform:translateY(-2px);border-color:#2763eb;box-shadow:0 12px 28px rgba(39,99,235,.1)}.insurance-pay-option__icon{width:44px;height:44px;border-radius:13px;font-size:20px}.insurance-policy-note{border-inline-start:4px solid #14b8a6;background:rgba(20,184,166,.08);border-radius:14px}.insurance-payment-panel{display:none;margin-top:20px;border:1px solid rgba(39,99,235,.25);border-radius:18px;background:rgba(39,99,235,.04);padding:22px;animation:insurancePanelIn .2s ease}.insurance-payment-panel.is-open{display:block}.insurance-transfer-details{background:rgba(39,99,235,.08);border-radius:14px;padding:16px}.insurance-transfer-details>div{display:flex;justify-content:space-between;gap:20px;padding:7px 0;border-bottom:1px dashed rgba(148,163,184,.35)}.insurance-transfer-details>div:last-child{border-bottom:0}@keyframes insurancePanelIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}.dark .insurance-gate__lock,[data-theme=dark] .insurance-gate__lock{background:rgba(15,23,42,.42)}.dark .insurance-gate__blur,[data-theme=dark] .insurance-gate__blur{background:#111c31}.dark .insurance-pay-option,[data-theme=dark] .insurance-pay-option,.dark .insurance-payment-panel,[data-theme=dark] .insurance-payment-panel{background:#162238;color:#eef4ff}
</style>
@endpush
@section('content')
@php
    $insuranceCredit = app(\App\Services\SellerLedgerService::class)->summary(auth('seller')->id())[\App\Services\SellerLedgerService::ORDER_INSURANCE_CREDIT] ?? 0;
    $offlineMethods = \App\Models\OfflinePaymentMethod::query()->where('status', 1)->whereIn('payment_channel', ['wallet', 'instapay'])->get();
    $canSubmit = $sellerOrderInsurance->status === \App\Models\SellerOrderInsurance::STATUS_PENDING_PAYMENT;
@endphp
<div class="content container-fluid"><div class="insurance-gate">
    <div class="insurance-gate__hero card border-0 shadow-sm mb-3"><div class="card-body p-4 p-lg-5">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <span class="insurance-gate__icon"><i class="fi fi-sr-shield-check"></i></span>
            <div class="flex-grow-1"><span class="badge badge-soft-warning mb-2">{{ translate('details_locked_until_seller_insurance_payment') }}</span><h1 class="h3 mb-1">{{ translate('new_approved_order') }}</h1><p class="text-muted mb-0">{{ translate('seller_order_insurance_details_hidden_notice') }}</p></div>
            <div class="text-center text-lg-end"><small class="text-muted d-block">{{ translate('Insurance_Amount') }}</small><strong class="h2 text-primary mb-0">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $sellerOrderInsurance->amount)) }}</strong></div>
        </div>
        <div class="insurance-gate__blur mb-4" aria-label="{{ translate('details_hidden_until_insurance_is_paid') }}">
            <div class="insurance-gate__blur-content"><div class="row g-3"><div class="col-md-4"><div class="bg-white rounded p-4">{{ translate('customer') }}<br><strong>Customer Name</strong></div></div><div class="col-md-4"><div class="bg-white rounded p-4">{{ translate('shipping_address') }}<br><strong>Full shipping address</strong></div></div><div class="col-md-4"><div class="bg-white rounded p-4">{{ translate('products') }}<br><strong>Order product details</strong></div></div></div></div>
            <div class="insurance-gate__lock"><div><i class="fi fi-sr-lock fs-28 text-primary"></i><h4 class="mt-2 mb-1">{{ translate('order_details_are_securely_hidden') }}</h4><p class="text-muted mb-0">{{ translate('pay_insurance_to_unlock_full_order_details') }}</p></div></div>
        </div>
        <div class="row g-3"><div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">{{ translate('Order_ID') }}</small><strong>***{{ substr(str_pad((string)$order->id, 3, '0', STR_PAD_LEFT), -3) }}</strong></div></div><div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">{{ translate('seller_order_insurance_expires_at') }}</small><strong>{{ $sellerOrderInsurance->expires_at?->translatedFormat('d M Y - h:i A') ?: '-' }}</strong></div></div><div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">{{ translate('payment_status') }}</small><strong class="text-warning">{{ translate($sellerOrderInsurance->status) }}</strong></div></div></div>
    </div></div>

    <div class="insurance-policy-note p-3 p-lg-4 mb-3"><div class="d-flex gap-3"><i class="fi fi-sr-info text-info fs-20 mt-1"></i><div><strong class="d-block mb-1">{{ translate('insurance_is_refundable_when_platform_policies_are_followed') }}</strong><span class="text-muted">{{ translate('insurance_refund_policy_explanation') }}</span><div class="mt-2"><a href="{{ route('business-page.view', ['slug'=>'terms-and-conditions']) }}" target="_blank">{{ translate('Terms_And_Conditions') }}</a><span class="mx-2">•</span><a href="{{ route('business-page.view', ['slug'=>'privacy-policy']) }}" target="_blank">{{ translate('Privacy_Policy') }}</a></div></div></div></div>

    <div class="card border-0 shadow-sm"><div class="card-body p-4"><h3 class="mb-1">{{ translate('continue_to_pay_insurance') }}</h3><p class="text-muted mb-4">{{ translate('choose_insurance_payment_method') }}</p>
    @if($canSubmit)
        <div class="row g-3">
            <div class="col-md-4"><div class="insurance-pay-option d-flex flex-column" data-payment-card="balance"><span class="insurance-pay-option__icon"><i class="fi fi-sr-wallet"></i></span><h5 class="mt-3 mb-1">{{ translate('insurance_balance') }}</h5><p class="text-muted fs-12 flex-grow-1">{{ translate('available_balance') }}: <strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $insuranceCredit)) }}</strong></p><button class="btn btn-outline-primary w-100" type="button" data-insurance-panel="balance">{{ translate('continue_to_payment') }} <i class="tio-chevron-down ms-1"></i></button></div></div>
            @foreach(['wallet'=>'pay_with_electronic_wallet','instapay'=>'pay_with_instapay'] as $channel => $label)
                @php($method = $offlineMethods->firstWhere('payment_channel', $channel))
                <div class="col-md-4"><div class="insurance-pay-option d-flex flex-column" data-payment-card="{{ $channel }}"><span class="insurance-pay-option__icon"><i class="{{ $channel === 'wallet' ? 'fi fi-sr-mobile-button' : 'fi fi-sr-bank' }}"></i></span><h5 class="mt-3 mb-1">{{ translate($label) }}</h5><p class="text-muted fs-12 flex-grow-1">{{ translate('submit_transfer_proof_for_admin_review') }}</p><button class="btn btn-outline-primary w-100" type="button" @disabled(!$method) data-insurance-panel="{{ $channel }}">{{ translate('continue_to_payment') }} <i class="tio-chevron-down ms-1"></i></button></div></div>
            @endforeach
        </div>
        <div class="insurance-payment-panel" data-panel-name="balance"><h4>{{ translate('insurance_balance') }}</h4><div class="row g-3 align-items-center"><div class="col-md-7"><div class="insurance-transfer-details"><div><span>{{ translate('available_balance') }}</span><strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $insuranceCredit)) }}</strong></div><div><span>{{ translate('required_insurance_amount') }}</span><strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $sellerOrderInsurance->amount)) }}</strong></div></div></div><div class="col-md-5">@if($insuranceCredit >= $sellerOrderInsurance->amount)<form method="post" action="{{ route('vendor.orders.seller-order-insurance.pay', $accessReference) }}">@csrf<input type="hidden" name="payment_method" value="seller_order_insurance_credit"><button class="btn btn--primary w-100">{{ translate('confirm_and_pay_insurance') }}</button></form>@else<div class="alert alert-warning">{{ translate('insurance_balance_is_insufficient_deposit_or_use_transfer') }}</div><a class="btn btn--primary w-100" href="{{ route('vendor.balance.index') }}#insurance">{{ translate('deposit_into_insurance_balance') }}</a>@endif</div></div></div>
        @foreach(['wallet'=>'pay_with_electronic_wallet','instapay'=>'pay_with_instapay'] as $channel => $label)
            @php($method = $offlineMethods->firstWhere('payment_channel', $channel))
            @if($method)<div class="insurance-payment-panel" data-panel-name="{{ $channel }}"><div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">{{ translate($label) }}</h4><p class="text-muted mb-0">{{ translate('transfer_exact_amount_then_upload_proof') }}</p></div><strong class="h4 text-primary">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $sellerOrderInsurance->amount)) }}</strong></div><div class="insurance-transfer-details mb-3">@foreach($method->method_fields ?? [] as $field)<div><span>{{ translatePaymentText($field['input_name'] ?? '') }}</span><strong>{{ translatePaymentText($field['input_data'] ?? '') }}</strong></div>@endforeach</div><form method="post" enctype="multipart/form-data" action="{{ route('vendor.orders.seller-order-insurance.offline-payment', $accessReference) }}">@csrf<input type="hidden" name="method_id" value="{{ $method->id }}"><div class="row g-3">@foreach($method->method_informations ?? [] as $field)@continue(empty($field['customer_input']) || $field['customer_input'] === 'payment_screenshot')<div class="col-md-6"><label class="form-label">{{ translatePaymentText($field['customer_placeholder'] ?? $field['customer_input']) }}</label><input class="form-control" name="method_information[{{ $field['customer_input'] }}]" maxlength="255" {{ ($field['is_required'] ?? false) ? 'required' : '' }}></div>@endforeach<div class="col-md-6"><label class="form-label">{{ translate('deposit_transfer_reference') }}</label><input class="form-control" name="payment_reference" maxlength="191" required></div><div class="col-md-6"><label class="form-label">{{ translate('payment_proof') }}</label><input class="form-control" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required></div><div class="col-12"><label class="form-label">{{ translate('payment_note') }}</label><textarea class="form-control" name="payment_note" rows="2" maxlength="1000"></textarea></div><div class="col-12"><button class="btn btn--primary px-5">{{ translate('submit_for_review') }}</button></div></div></form></div>@endif
        @endforeach
    @else
        <div class="alert alert-warning mb-0"><i class="fi fi-sr-hourglass-start me-2"></i>{{ translate('offline_payment_submitted_and_waiting_for_admin_review') }}</div>
    @endif
    </div></div>
</div></div>
@endsection
@push('script')
<script>
document.addEventListener('click', function (event) {
    const trigger = event.target.closest('[data-insurance-panel]');
    if (!trigger) return;
    const name = trigger.dataset.insurancePanel;
    const target = document.querySelector('[data-panel-name="' + name + '"]');
    const willOpen = target && !target.classList.contains('is-open');
    document.querySelectorAll('[data-panel-name]').forEach(panel => panel.classList.toggle('is-open', willOpen && panel.dataset.panelName === name));
    document.querySelectorAll('[data-payment-card]').forEach(card => card.classList.toggle('is-active', willOpen && card.dataset.paymentCard === name));
    const opened = document.querySelector('[data-panel-name="' + name + '"].is-open');
    if (opened) setTimeout(() => opened.scrollIntoView({behavior:'smooth', block:'nearest'}), 80);
});
</script>
@endpush
