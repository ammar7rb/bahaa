@php
    $insuranceDue = max(0, ($summary['insurance_amount'] ?? 0) - ($summary['insurance_balance_paid'] ?? 0));
    $availableBalance = max(0, $insuranceBalance['available_balance'] ?? 0);
    $amountDue = max(0, $summary['external_amount_due'] ?? 0);
@endphp
<style>
.second-stage-page{text-align:start;--invoice-surface:#fff;--invoice-soft:#f3f7fd;--invoice-border:#dce5f0;--invoice-text:#182b43;--invoice-muted:#60718b}
.second-stage-wrap{max-width:940px;margin:auto;color:var(--invoice-text)}
.second-stage-hero,.second-stage-panel,.second-stage-option{background:var(--invoice-surface);border:1px solid var(--invoice-border);border-radius:18px;box-shadow:0 8px 28px rgba(21,43,78,.055)}
.second-stage-hero{padding:clamp(22px,4vw,36px);background:linear-gradient(120deg,#e9f2ff,#fff 70%);margin-bottom:22px}
.second-stage-kicker{color:#1465bf;font-weight:700;font-size:.9rem;margin-bottom:10px}
.second-stage-hero h1{font-size:clamp(1.45rem,3vw,2rem);font-weight:750;margin:0 0 8px}.second-stage-muted{color:var(--invoice-muted)}
.second-stage-hero p{margin:0;line-height:1.7}.second-stage-due{display:flex;align-items:end;justify-content:space-between;flex-wrap:wrap;gap:12px;border-top:1px solid var(--invoice-border);margin-top:22px;padding-top:20px}
.second-stage-due strong{display:block;font-size:clamp(1.6rem,4vw,2.2rem);color:#1264be}
.second-stage-panel{padding:clamp(18px,3vw,28px);margin-bottom:22px}.second-stage-panel h2{font-size:1.24rem;font-weight:700;margin:0 0 6px}
.second-stage-line{display:flex;justify-content:space-between;align-items:baseline;gap:15px;border-bottom:1px solid var(--invoice-border);padding:12px 0}.second-stage-line:last-child{border:0}.second-stage-line strong{white-space:nowrap}.second-stage-line-total{color:#1264be;font-weight:700;font-size:1.07rem}
.second-stage-note,.second-stage-transfer{background:var(--invoice-soft);border-radius:12px;padding:14px 16px;margin-top:15px;line-height:1.7}
.second-stage-choices{display:grid;gap:12px;margin-top:20px}.second-stage-option{overflow:hidden}.second-stage-option[open]{border-color:#2881e5;box-shadow:0 0 0 3px rgba(40,129,229,.1)}
.second-stage-option summary{display:flex;align-items:center;gap:14px;list-style:none;cursor:pointer;padding:18px}.second-stage-option summary::-webkit-details-marker{display:none}.second-stage-option summary:after{content:'⌄';margin-inline-start:auto;font-size:1.5rem;color:#4573aa}.second-stage-option[open] summary:after{transform:rotate(180deg)}
.second-stage-option-icon{display:flex;align-items:center;justify-content:center;flex:0 0 46px;width:46px;height:46px;background:#eaf3ff;color:#1875d2;border-radius:13px}.second-stage-option-icon svg{width:24px;height:24px}
.second-stage-option-title{display:block;color:var(--invoice-text);font-weight:700}.second-stage-option-subtitle{display:block;color:var(--invoice-muted);font-size:.87rem;margin-top:3px;line-height:1.5}.second-stage-option-body{padding:0 18px 18px;border-top:1px solid var(--invoice-border)}
.second-stage-transfer{display:grid;gap:10px;margin-bottom:16px}.second-stage-transfer>div{display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px}.second-stage-transfer bdi{direction:ltr;unicode-bidi:isolate;font-weight:700}
.second-stage-form{display:grid;gap:12px}.second-stage-form label{display:block;font-weight:600;margin-bottom:6px}.second-stage-form .form-control{width:100%;min-height:44px}.second-stage-action{min-height:44px;padding-inline:24px;border-radius:10px;font-weight:700;background:#1676d6;border-color:#1676d6;color:#fff}.second-stage-action:hover,.second-stage-action:focus{background:#105da9;border-color:#105da9;color:#fff}
.second-stage-page .second-stage-help{color:#1264be!important;font-weight:700;border:1px solid var(--invoice-border);border-radius:10px;padding:12px 16px;text-decoration:none}
:root[data-ba-storefront-theme="dark"] .second-stage-page .second-stage-help{color:#8dbfff!important;background:#213553}
:root[data-ba-storefront-theme="dark"] .second-stage-page .second-stage-form .form-control{background:#101a2d;color:#edf3ff;border-color:#344665}
.second-stage-option summary:focus-visible,.second-stage-help:focus-visible{outline:3px solid #73a5ff;outline-offset:2px}
:root[data-ba-storefront-theme="dark"] .second-stage-page{--invoice-surface:#172238;--invoice-soft:#1d2a43;--invoice-border:#344665;--invoice-text:#edf3ff;--invoice-muted:#b6c4dc}
:root[data-ba-storefront-theme="dark"] .second-stage-hero{background:linear-gradient(120deg,#1b3557,#172238 70%)}
:root[data-ba-storefront-theme="dark"] .second-stage-kicker,:root[data-ba-storefront-theme="dark"] .second-stage-due strong,:root[data-ba-storefront-theme="dark"] .second-stage-line-total{color:#8dbfff}
:root[data-ba-storefront-theme="dark"] .second-stage-option-icon{background:#213553;color:#8dbfff}
@media(max-width:575px){.second-stage-option summary{padding:14px}.second-stage-option-body{padding:0 14px 14px}.second-stage-line{font-size:.9rem}}
</style>
<div class="second-stage-wrap" dir="{{ in_array(getDefaultLanguage(), ['ar', 'sa']) ? 'rtl' : 'ltr' }}">
    <div class="alert alert-info" id="second-invoice-changed" hidden>{{ translate('second_invoice_updated_review') }} <a class="second-stage-help" href="{{ route('post-purchase-invoices.show', $invoice->id) }}">{{ translate('second_invoice_refresh') }}</a></div>
    @if(session('checkout_pending_notice'))<div class="alert alert-success">{{ translate('checkout_request_received_pending') }}</div>@endif
    <header class="second-stage-hero">
        <div class="second-stage-kicker">{{ translate('second_invoice_step') }} · <bdi>{{ $invoice->invoice_number }}</bdi> · #{{ $invoice->order_id }}</div>
        <h1>{{ translate('second_invoice_heading') }}</h1>
        <p class="second-stage-muted">{{ translate('second_invoice_intro') }}</p>
        <div class="second-stage-due"><div><span class="second-stage-muted">{{ translate('second_invoice_amount_now') }}</span><strong><bdi>{{ webCurrencyConverter($amountDue) }}</bdi></strong></div>@if($invoice->payment_due_at)<small class="second-stage-muted">{{ translate('second_invoice_deadline') }}: {{ $invoice->payment_due_at->format('d M Y, h:i A') }}</small>@endif</div>
    </header>
    <section class="second-stage-panel" aria-labelledby="stage-summary-title">
        <h2 id="stage-summary-title">{{ translate('second_invoice_details') }}</h2>
        <p class="second-stage-muted mb-0">{{ translate('second_invoice_active_items') }}</p>
        <div class="second-stage-line"><span>{{ translate('second_invoice_purchases_shipping') }} <small class="second-stage-muted">({{ translate('second_invoice_first') }})</small></span><strong><bdi>{{ webCurrencyConverter($summary['first_payment_amount'] ?? 0) }}</bdi></strong></div>
        @if(($summary['insurance_amount'] ?? 0) > 0)<div class="second-stage-line"><span>{{ translate('second_invoice_insurance') }}</span><strong><bdi>{{ webCurrencyConverter($summary['insurance_amount']) }}</bdi></strong></div>@endif
        @if(($summary['tax_amount'] ?? 0) > 0)<div class="second-stage-line"><span>{{ translate('second_invoice_tax') }}</span><strong><bdi>{{ webCurrencyConverter($summary['tax_amount']) }}</bdi></strong></div>@endif
        <div class="second-stage-line"><span>{{ translate('second_invoice_total') }}</span><strong><bdi>{{ webCurrencyConverter($summary['total_amount']) }}</bdi></strong></div>
        @if(($summary['insurance_balance_paid'] ?? 0) > 0)<div class="second-stage-line"><span>{{ translate('second_invoice_balance_paid') }}</span><strong><bdi>{{ webCurrencyConverter($summary['insurance_balance_paid']) }}</bdi></strong></div>@endif
        <div class="second-stage-line second-stage-line-total"><span>{{ translate('second_invoice_remaining') }}</span><strong><bdi>{{ webCurrencyConverter($amountDue) }}</bdi></strong></div>
        @if(($summary['insurance_amount'] ?? 0) > 0)<div class="second-stage-note second-stage-muted">{{ translate(($summary['tax_amount'] ?? 0) > 0 ? 'second_invoice_balance_notice' : 'second_invoice_balance_hint') }}</div>@endif
    </section>
    <section class="second-stage-panel" aria-labelledby="stage-payment-title">
        <h2 id="stage-payment-title">{{ translate('second_invoice_payment_title') }}</h2>
        @if($invoice->status === \App\Models\PostPurchaseInvoice::STATUS_PAID)
            <div class="alert alert-success mt-3 mb-0">{{ translate('payment_received_order_waiting_for_admin_review') }}</div>
        @elseif($invoice->status === \App\Models\PostPurchaseInvoice::STATUS_AWAITING_REVIEW)
            <div class="alert alert-info mt-3 mb-0">{{ translate('offline_payment_is_waiting_for_admin_review') }}</div>
        @elseif($invoice->status === \App\Models\PostPurchaseInvoice::STATUS_EXPIRED)
            <div class="alert alert-danger mt-3 mb-0">{{ translate('payment_window_has_expired_contact_support') }}</div>
        @elseif($invoice->status === \App\Models\PostPurchaseInvoice::STATUS_CANCELLED)
            <div class="alert alert-info mt-3 mb-0">{{ translate('second_invoice_cancelled') }}</div>
        @elseif(!$invoice->isPayable())
            <div class="alert alert-info mt-3 mb-0">{{ translate('second_invoice_unavailable') }}</div>
        @else
            <p class="second-stage-muted mb-0">{{ translate('second_invoice_choose') }}</p>
            <div class="second-stage-choices">
                @if($insuranceDue > 0)
                    <details class="second-stage-option"><summary><span class="second-stage-option-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="15" rx="2"/><path d="M2 9h20M16 15h3"/></svg></span><span><span class="second-stage-option-title">{{ translate('insurance_balance') }}</span><span class="second-stage-option-subtitle">{{ translate('second_invoice_balance_hint') }}</span></span></summary>
                        <div class="second-stage-option-body"><div class="second-stage-transfer"><div><span>{{ translate('second_invoice_insurance_remaining') }}</span><bdi>{{ webCurrencyConverter($insuranceDue) }}</bdi></div><div><span>{{ translate('second_invoice_available_balance') }}</span><bdi>{{ webCurrencyConverter($availableBalance) }}</bdi></div></div>
                            @if($availableBalance < $insuranceDue)<div class="alert alert-warning">{{ translate('insurance_balance_is_insufficient_deposit_or_use_transfer') }} <a href="{{ route('wallet') }}">{{ translate('deposit_into_insurance_balance') }}</a></div>
                            @else<form method="post" action="{{ route('post-purchase-invoices.use-insurance-balance', $invoice->id) }}">@csrf<button class="btn btn--primary second-stage-action" type="submit">{{ translate('second_invoice_use_balance') }}</button></form>@if(($summary['tax_amount'] ?? 0) > 0)<p class="second-stage-muted mt-2 mb-0">{{ translate('second_invoice_tax_after_balance') }}</p>@endif @endif
                        </div></details>
                @endif
                @if($amountDue > 0 && $offlinePaymentAvailable)
                    @foreach($offlinePaymentMethods as $method)
                        <details class="second-stage-option"><summary><span class="second-stage-option-icon" aria-hidden="true">@if($method->payment_channel === 'instapay')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m22 2-7 20-4-9-9-4 20-7ZM11 13 22 2"/></svg>@else<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="15" rx="2"/><path d="M2 9h20M16 15h3"/></svg>@endif</span><span><span class="second-stage-option-title">{{ translate($method->payment_channel === 'instapay' ? 'second_invoice_instapay' : 'second_invoice_wallet') }}</span><span class="second-stage-option-subtitle">{{ translatePaymentText($method->method_name) }} · {{ translate('second_invoice_transfer_hint') }}</span></span></summary>
                            <div class="second-stage-option-body"><div class="second-stage-transfer"><div><span>{{ translate('second_invoice_transfer_amount') }}</span><bdi>{{ webCurrencyConverter($amountDue) }}</bdi></div>@foreach($method->method_fields ?? [] as $field)<div><span>{{ translatePaymentText($field['input_name'] ?? '') }}</span><bdi>{{ $field['input_data'] ?? '' }}</bdi></div>@endforeach</div>
                                <form class="second-stage-form" method="post" enctype="multipart/form-data" action="{{ route('post-purchase-invoices.offline-payment', $invoice->id) }}">@csrf<input type="hidden" name="method_id" value="{{ $method->id }}"><div><label for="stage-proof-{{ $method->id }}">{{ translate('second_invoice_proof') }}</label><input id="stage-proof-{{ $method->id }}" class="form-control" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required></div><div><label for="stage-note-{{ $method->id }}">{{ translate('second_invoice_payment_note') }} <span class="second-stage-muted">({{ translate('second_invoice_optional') }})</span></label><textarea id="stage-note-{{ $method->id }}" class="form-control" name="payment_note" rows="2" maxlength="1000"></textarea></div><div><button class="btn btn--primary second-stage-action" type="submit">{{ translate('second_invoice_submit_proof') }}</button></div></form>
                            </div></details>
                    @endforeach
                @endif
                @if($amountDue > 0 && $digitalPaymentAvailable)
                    @foreach($paymentGatewayList as $gateway)
                        <details class="second-stage-option"><summary><span class="second-stage-option-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20M6 16h4"/></svg></span><span><span class="second-stage-option-title">{{ $gateway['key_name'] }}</span><span class="second-stage-option-subtitle">{{ translate('second_invoice_digital_hint') }}</span></span></summary><div class="second-stage-option-body"><div class="second-stage-transfer"><div><span>{{ translate('second_invoice_amount_now') }}</span><bdi>{{ webCurrencyConverter($amountDue) }}</bdi></div></div><form method="post" action="{{ route('post-purchase-invoices.pay-digital', $invoice->id) }}">@csrf<input type="hidden" name="payment_method" value="{{ $gateway['key_name'] }}"><button class="btn btn--primary second-stage-action" type="submit">{{ translate('second_invoice_continue_payment') }}</button></form></div></details>
                    @endforeach
                @endif
            </div>
        @endif
        <a class="second-stage-help d-inline-block mt-3" href="{{ route('account-tickets') }}">{{ translate('second_invoice_help') }}</a>
    </section>
</div>
<script>
(function () {
    var revision = @json($invoiceRevision);
    var url = @json(route('post-purchase-invoices.state', $invoice->id));
    var page = document.querySelector('.second-stage-wrap');
    var changed = false, dirty = false, busy = false;
    page.addEventListener('input', function () { dirty = true; });
    page.addEventListener('change', function () { dirty = true; });
    page.addEventListener('submit', function (event) { if (changed) event.preventDefault(); });
    async function refresh() {
        if (document.hidden || busy || changed) return;
        busy = true;
        try {
            var response = await fetch(url, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}});
            if (!response.ok) return;
            var state = await response.json();
            if (state.revision && state.revision !== revision) {
                if (!dirty) { window.location.reload(); return; }
                changed = true;
                document.getElementById('second-invoice-changed').hidden = false;
                page.querySelectorAll('button[type="submit"]').forEach(function (button) { button.disabled = true; });
            }
        } catch (error) { /* Retry when connectivity returns. */ }
        finally { busy = false; }
    }
    window.setInterval(refresh, 30000);
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', refresh);
}());
</script>
