@php($walletSource = $filters['source'] === 'wallet')
<div class="fin-table-scroll" role="region" tabindex="0" aria-label="{{ __('financials.transactions') }}">
    <table class="fin-table"><thead><tr><th>{{ __('financials.reference') }}</th><th>{{ __('financials.party') }}</th><th>{{ __('financials.kind') }}</th><th>{{ __('financials.status') }}</th><th>{{ __('financials.method') }}</th>
        @if($walletSource)<th>{{ __('financials.credit') }}</th><th>{{ __('financials.debit') }}</th><th>{{ __('financials.admin_bonus') }}</th><th>{{ __('financials.balance') }}</th>@else<th>{{ __('financials.amount') }}</th>@endif
        <th>{{ __('financials.date') }}</th><th>{{ __('financials.view') }}</th></tr></thead>
    <tbody>@forelse($records as $entry)
        <tr><td><a class="fin-reference" href="{{ route('admin.report.financials.entry',[$entry['source'],$entry['id']]) }}"><bdi>{{ $entry['reference'] }}</bdi></a><small>#{{ $entry['id'] }}</small></td>
            <td>@if($entry['customer_id']){{ $entry['customer_name'] ?: __('financials.customer') }} <bdi>#{{ $entry['customer_id'] }}</bdi>@endif @if($entry['seller_id'])<small>{{ $entry['seller_name'] ?: __('financials.vendor') }} <bdi>#{{ $entry['seller_id'] }}</bdi></small>@endif @if(!$entry['customer_id'] && !$entry['seller_id'])—@endif</td>
            <td>{{ $financialLabel($entry['kind']) }}@if($entry['bucket'])<small>{{ $financialLabel($entry['bucket']) }} · {{ $financialLabel($entry['direction']) }}</small>@endif</td>
            <td><span class="fin-status" data-tone="{{ in_array($entry['status'],['paid','approved']) ? 'success' : (in_array($entry['status'],['pending','pending_review','held','disputed','legacy_review']) ? 'pending' : 'neutral') }}">{{ $financialLabel($entry['status']) }}</span>
                @if($entry['review'])<small>{{ __('financials.review') }}: {{ $financialLabel($entry['review']) }}</small>@endif
            </td><td>{{ $financialLabel($entry['method']) }}</td>
            @if($walletSource) @foreach(['credit','amount','bonus','balance'] as $column)<td class="fin-number"><bdi>{{ number_format((float)$entry[$column],3) }}</bdi><small>{{ $entry['currency'] }}</small></td>@endforeach
            @else<td class="fin-number"><bdi>{{ number_format((float)$entry['amount'],3) }}</bdi><small>{{ $entry['currency'] }}</small></td>@endif
            <td><time><bdi>{{ $entry['date'] }}</bdi></time></td><td><a class="fin-row-link" href="{{ route('admin.report.financials.entry',[$entry['source'],$entry['id']]) }}" aria-label="{{ __('financials.view') }} #{{ $entry['id'] }}"><i class="tio-visible-outlined" aria-hidden="true"></i></a></td></tr>
    @empty<tr><td colspan="{{ $walletSource ? 11 : 8 }}" class="fin-empty"><i class="tio-search" aria-hidden="true"></i><strong>{{ __('financials.empty') }}</strong><span>{{ __('financials.empty_help') }}</span></td></tr>@endforelse</tbody></table>
</div>
