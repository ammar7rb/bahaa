@php($paymentSummary = app(\App\Services\OrderPaymentSummaryService::class)->forOrder($order))
<div class="d-flex flex-column gap-1 mt-1 fs-12" data-order-payment-summary="{{ $order->id }}">
    @foreach($paymentSummary['lines'] as $paymentLine)
        <div class="text-{{ $paymentLine['tone'] }} fw-medium">
            {{ translate($paymentLine['label']) }}:
            @if($paymentLine['status']) {{ translate($paymentLine['status']) }} @endif
            @if($paymentLine['amount'] !== null)
                <span class="text-nowrap">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $paymentLine['amount']), currencyCode: getCurrencyCode()) }}</span>
            @endif
        </div>
    @endforeach
    @if($paymentSummary['incomplete'])
        <div class="text-danger fw-bold" role="status">
            <i class="fi fi-sr-exclamation me-1" aria-hidden="true"></i>{{ translate('order_payments_incomplete') }}
        </div>
    @endif
</div>
