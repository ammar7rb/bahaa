<section class="fin-panel fa-register" aria-labelledby="{{ ($statement ?? false) ? 'statement-records-title' : 'incoming-records-title' }}">
    <div class="fin-panel-heading"><h2 id="{{ ($statement ?? false) ? 'statement-records-title' : 'incoming-records-title' }}">{{ __('financial-activity.'.(($statement ?? false) ? 'all_activity' : 'incoming')) }}</h2><span class="fin-help">{{ number_format($records->total()) }} {{ __('company-financials.matching') }}</span></div>
    @if($unknownAmounts)<p class="fin-notice">{{ __('financial-activity.unknown_amounts',['count'=>$unknownAmounts]) }}</p>@endif
    @if($missing)<p class="fin-notice">{{ __('company-financials.missing') }}</p>@endif
    <div class="fin-table-scroll" role="region" tabindex="0" aria-label="{{ __('financial-activity.all_activity') }}"><table class="fin-table fa-table"><thead><tr>
        <th scope="col">{{ __('financials.date') }}</th><th scope="col">{{ __('financials.reference') }}</th>
        @unless($statement ?? false)<th scope="col">{{ __('financials.party') }}</th>@endunless
        <th scope="col">{{ __('financial-activity.category') }}</th><th scope="col">{{ __('financials.amount') }}</th><th scope="col">{{ __('financial-activity.movement') }}</th><th scope="col">{{ __('financials.status') }}</th><th scope="col">{{ __('financials.method') }}</th>
        @if($statement ?? false)<th scope="col">{{ __('financial-activity.recorded_balance') }}</th>@endif
    </tr></thead><tbody>
    @forelse($records as $row)
    @php($person=$names[$row->account_type]->get($row->account_id))
    <tr><td><bdi>{{ $row->date ?: '—' }}</bdi></td><td><strong><bdi>#{{ $row->id }}</bdi></strong><small><bdi>{{ $row->reference ?: '—' }}</bdi></small>
        @php($detailSource=$row->source === 'wallet_funding' ? 'wallet' : $row->source)
        @if(in_array($detailSource,['orders','deposits','vendor_deposits','customer_packages','vendor_packages','extra_ads','wallet','vendor_ledger','refunds','settlements']))<a class="cf-record-link" href="{{ route('admin.report.financials.entry',[$detailSource,$row->id]) }}" @if($statement ?? false) target="_blank" rel="noopener" @endif>{{ __('company-financials.view') }}</a>@endif
        @if($row->order_id)<small>{{ __('financials.order_link') }} <bdi>#{{ $row->order_id }}</bdi></small>@if(\App\Utils\Helpers::module_permission_check('orders'))<a class="cf-record-link" href="{{ route('admin.orders.details',$row->order_id) }}" @if($statement ?? false) target="_blank" rel="noopener" @endif>{{ __('company-financials.order') }}</a>@endif @endif
    </td>
    @unless($statement ?? false)<td><strong>{{ $person ? trim($person->f_name.' '.$person->l_name) : __('company-financials.'.$row->account_type) }}</strong><small>{{ __('company-financials.'.$row->account_type) }} <bdi>#{{ $row->account_id }}</bdi></small></td>@endunless
    <td>{{ __('financial-activity.categories.'.$row->category) }}@if($row->operation)<small>{{ $financialLabel($row->operation) }}</small>@endif @if($row->bucket)<small>{{ $financialLabel($row->bucket) }}</small>@endif</td>
    <td class="fin-number"><bdi>{{ $row->amount === null ? '—' : number_format((float)$row->amount,3) }}</bdi><small>{{ $row->currency ?: '—' }}</small>@if((float)$row->bonus != 0.0)<small>{{ __('financials.admin_bonus') }}: <bdi>{{ number_format((float)$row->bonus,3) }}</bdi></small>@endif</td>
    <td><span class="fa-effect" data-effect="{{ $row->movement }}">{{ __('financial-activity.movements.'.$row->movement) }}</span>@if(in_array($row->direction,['credit','debit','out']))<small>{{ $row->direction === 'out' ? __('financial-activity.refund_direction') : $financialLabel($row->direction) }}</small>@endif</td>
    <td><span class="fin-status" data-tone="{{ $row->movement === 'ledger' ? (in_array((string)$row->status,['pending','held','disputed','0']) ? 'pending' : 'neutral') : ($row->confirmed ? 'success' : 'pending') }}">{{ $row->source === 'withdrawals' ? __('financial-registers.cards.withdrawals_'.$row->status) : ($row->source === 'refund_ledger' && $row->status === 'available' ? __('financial-activity.refund_available') : ($row->status === 'completed' ? __('financial-activity.completed') : $financialLabel($row->status))) }}</span>@if($row->review)<small>{{ __('financials.review') }}: {{ $financialLabel($row->review) }}</small>@endif @if(!$row->confirmed && $row->movement !== 'ledger')<small>{{ __('financial-activity.unconfirmed') }}</small>@endif</td>
    <td>{{ $financialLabel($row->method) }}</td>
    @if($statement ?? false)<td class="fin-number"><bdi>{{ $row->balance === null ? '—' : number_format((float)$row->balance,3) }}</bdi></td>@endif
    </tr>
    @empty<tr><td colspan="9" class="fin-empty"><i class="tio-search" aria-hidden="true"></i><strong>{{ __('company-financials.no_records') }}</strong><span>{{ __('company-financials.reset_hint') }}</span></td></tr>@endforelse
    </tbody></table></div><div class="fin-pagination" @if($statement ?? false) data-statement-pagination @endif>{{ $records->links() }}</div>
</section>
