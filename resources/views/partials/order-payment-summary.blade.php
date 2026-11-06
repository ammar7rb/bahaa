@php($paymentSummary = app(\App\Services\OrderPaymentSummaryService::class)->forOrder($order))
<span class="badge badge-soft-{{ $paymentSummary['lines'][0]['tone'] }} order-payment-status" data-order-payment-summary="{{ $order->id }}">
    {{ translate($paymentSummary['lines'][0]['status']) }}
</span>
@if($paymentSummary['edit_due_amount'] > 0)
<small class="d-block text-warning mt-1">{{ translate('due') }}: <bdi>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $paymentSummary['edit_due_amount']), currencyCode: getCurrencyCode()) }}</bdi></small>
@endif
