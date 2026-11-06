<div class="vendor-invoice-overview">
    <div><span>{{ translate('available_balance') }}</span><strong>{{ webCurrencyConverter(amount:$data['summary']['available_balance']) }}</strong></div>
    <div><span>{{ translate('sigma_future_receivables') }}</span><strong>{{ webCurrencyConverter(amount:$data['summary']['upcoming_receivables']) }}</strong></div>
</div>
<h3>{{ translate('sigma_invoice_recent') }}</h3>
<div class="vendor-invoice-list">
@forelse($data['records'] as $item)
    <a class="vendor-invoice-row" href="{{ $item['action_url'] }}">
        @if($item['locked'])
            <span><strong>***{{ $item['order_number_suffix'] }}</strong><small>{{ translate('vendor_order_waiting_for_administrative_release') }}</small></span><strong>—</strong>
        @else
            <span><strong>{{ translate('sales_invoice_number') }} #{{ $item['invoice_number'] }}</strong><small>{{ translate('settlement_'.$item['status']) }} · {{ $item['due_at'] ? \Carbon\Carbon::parse($item['due_at'])->format('Y-m-d') : '—' }}</small></span><strong><bdi>{{ webCurrencyConverter(amount:$item['receivable']) }}</bdi></strong>
        @endif
    </a>
@empty
    <p>{{ translate('no_data_found') }}</p>
@endforelse
</div>
<a class="vendor-invoice-all" href="{{ route('vendor.invoices') }}">{{ translate('vendor_view_all') }}</a>
