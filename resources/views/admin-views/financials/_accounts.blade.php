<div class="fin-table-scroll" role="region" tabindex="0" aria-label="{{ __('financials.accounts') }}">
    <table class="fin-table"><thead><tr><th>{{ __('financials.customer') }}</th><th>{{ __('financials.balance') }} <small>{{ $baseCurrency }}</small></th><th>{{ __('financials.allowance') }}</th><th>{{ __('financials.committed') }}</th><th>{{ __('financials.remaining') }}</th><th>{{ __('financials.expiry') }}</th><th>{{ __('financials.view') }}</th></tr></thead>
    <tbody>@forelse($accounts as $customer)
        <tr><td><strong>{{ trim($customer->f_name.' '.$customer->l_name) ?: '#'.$customer->id }}</strong><small><bdi>#{{ $customer->id }} · {{ $customer->phone }}</bdi></small></td>
            <td class="fin-number"><bdi>{{ number_format($customer->wallet_balance,3) }}</bdi></td>
            @foreach(['allowance','committed','remaining'] as $column)<td class="fin-number fin-limit"><bdi>{{ number_format($customer->{$column},3) }}</bdi></td>@endforeach
            <td><bdi>{{ $customer->expires_at ? substr($customer->expires_at,0,10) : '—' }}</bdi></td>
            <td><a class="fin-row-link" href="{{ route('admin.report.financials.customers',['customer_id'=>$customer->id,'tab'=>'transactions','source'=>'wallet','from'=>$filters['from'],'to'=>$filters['to']]) }}">{{ __('financials.customer_statement') }}<i class="tio-visible-outlined" aria-hidden="true"></i></a></td>
        </tr>
    @empty<tr><td colspan="7" class="fin-empty"><i class="tio-search" aria-hidden="true"></i><strong>{{ __('financials.empty') }}</strong><span>{{ __('financials.empty_help') }}</span></td></tr>@endforelse</tbody></table>
</div>
