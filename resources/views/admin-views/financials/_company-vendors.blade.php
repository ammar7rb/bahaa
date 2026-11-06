@php($isSettlements = $tab === 'settlements')
@if($missing)<div class="fin-notice" role="status">{{ __('company-financials.missing') }}</div>@endif
<p class="fin-help cf-filter-note">{{ __('financial-registers.intro') }}</p>
<form method="get" action="{{ route('admin.report.financials.company') }}" class="fin-filters" data-cf-form>
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="fin-filter-grid">
    <label>{{ __('financials.from') }}<input type="date" name="from" value="{{ $filters['from'] }}" required></label><label>{{ __('financials.to') }}<input type="date" name="to" value="{{ $filters['to'] }}" required></label>
    <label>{{ __('financials.seller_id') }}<input name="seller_id" type="number" min="1" value="{{ $filters['seller_id'] ?? '' }}"></label>
    @unless($isSettlements)<label>{{ __('financial-registers.register') }}<select name="register">@foreach(\App\Services\Admin\VendorFinancialsReadService::FLOWS as $register)<option value="{{ $register }}" @selected($flow === $register)>{{ __('financial-registers.flows.'.$register) }}</option>@endforeach</select></label>@endunless
    @if($isSettlements || in_array($flow,['deposits','withdrawals']))<label>{{ __('financials.status') }}<select name="status"><option value="">{{ __('financials.all') }}</option>@foreach($isSettlements ? ['held','disputed','released','reversed'] : ($flow === 'deposits' ? ['pending','paid','rejected','reversed'] : ['0','1','2']) as $state)<option value="{{ $state }}" @selected((string)($filters['status'] ?? '') === $state)>{{ $flow === 'withdrawals' ? __('financial-registers.cards.withdrawals_'.$state) : $label($state) }}</option>@endforeach</select></label>
    @else<label>{{ __('financial-registers.account') }}<select name="bucket"><option value="">{{ __('financials.all') }}</option>@foreach(\App\Services\Admin\VendorFinancialsReadService::BUCKETS as $bucket)<option value="{{ $bucket }}" @selected(($filters['bucket'] ?? '') === $bucket)>{{ __('financial-registers.cards.'.$bucket) }}</option>@endforeach</select></label><label>{{ __('financial-registers.direction') }}<select name="direction"><option value="">{{ __('financials.all') }}</option>@foreach(['credit','debit'] as $direction)<option value="{{ $direction }}" @selected(($filters['direction'] ?? '') === $direction)>{{ $label($direction) }}</option>@endforeach</select></label>
    <label>{{ __('financial-registers.date_scope') }}<select name="all_dates"><option value="0" @selected(empty($filters['all_dates']))>{{ __('financial-registers.selected_period') }}</option><option value="1" @selected(!empty($filters['all_dates']))>{{ __('financial-registers.all_dates') }}</option></select></label>@endif
    @if($isSettlements)<label>{{ __('company-financials.due') }}<select name="settlement_window"><option value="">{{ __('financials.all') }}</option>@foreach(['overdue','due_soon','escalated'] as $window)<option value="{{ $window }}" @selected(($filters['settlement_window'] ?? '') === $window)>{{ __('financial-registers.cards.'.$window) }}</option>@endforeach</select></label>@endif
    <label>{{ __('financial-registers.search') }}<input name="q" type="search" maxlength="100" value="{{ $filters['q'] ?? '' }}"></label>
    <label>{{ __('financials.per_page') }}<select name="per_page">@foreach([20,50,100] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 20) == $size)>{{ $size }}</option>@endforeach</select></label>
    </div><div class="fin-filter-actions"><button class="fin-button fin-primary">{{ __('financials.apply') }}</button><a class="fin-button" href="{{ route('admin.report.financials.company',['tab'=>$tab]) }}">{{ __('financials.reset') }}</a><span class="fin-help cf-load" role="status" hidden>{{ __('company-financials.loading') }}</span></div>
</form>
<div class="cf-content">
    @include('admin-views.financials._vendor-cards',['vendorCardGroups'=>$isSettlements ? ['settlements'=>['settlements_held','settlements_disputed','settlements_released','settlements_reversed','overdue','due_soon','escalated']] : ['balances'=>['available','operating']]])
    <section class="fin-panel cf-record-panel"><div class="fin-panel-heading"><h2>{{ __('financial-registers.flows.'.$flow) }}</h2><span>{{ number_format($rows->total()) }} {{ __('company-financials.matching') }}</span></div>
    <p class="fin-table-note">{{ __('financial-registers.'.($flow === 'ledger' || in_array($flow,['missing_references','duplicate_keys']) ? 'ledger_note' : ($flow === 'settlements' ? 'settlement_note' : 'funding_note'))) }}</p>
    @if($rows->isEmpty())<div class="cf-empty"><h2>{{ __('company-financials.no_records') }}</h2><p>{{ __('company-financials.reset_hint') }}</p></div>@else
    <div class="fin-table-scroll" role="region" tabindex="0" aria-label="{{ __('financial-registers.flows.'.$flow) }}"><table class="fin-table cf-table"><thead><tr><th>{{ __('financials.reference') }}</th><th>{{ __('financials.vendor') }}</th><th>{{ __('financials.amount') }}</th><th>{{ __('financials.status') }}</th><th>{{ __('financial-registers.details') }}</th><th>{{ __('financials.date') }}</th>@if($isSettlements && \App\Utils\Helpers::module_permission_check('people'))<th>{{ __('financial-registers.actions') }}</th>@endif</tr></thead><tbody>
    @foreach($rows as $entry) @php($name = $vendorNames->get($entry->seller_id))<tr>
        <td><strong><bdi>#{{ $entry->id }}</bdi></strong>
        @if($flow === 'settlements')<small>{{ __('financials.order_link') }} <bdi>#{{ $entry->order_id }}</bdi></small>@if(\App\Utils\Helpers::module_permission_check('orders'))<a class="cf-record-link" href="{{ route('admin.orders.details',$entry->order_id) }}">{{ __('company-financials.order') }}</a>@endif
        @elseif($flow !== 'withdrawals')<a class="cf-record-link" href="{{ route('admin.report.financials.entry',[$flow === 'deposits' ? 'vendor_deposits' : 'vendor_ledger',$entry->id]) }}">{{ __('company-financials.view') }}</a>
        @elseif(\App\Utils\Helpers::module_permission_check('people'))<a class="cf-record-link" href="{{ route('admin.vendors.withdraw_view',[$entry->id,$entry->seller_id]) }}">{{ __('company-financials.view') }}</a>@endif</td>
        <td>{{ $name ? trim($name->f_name.' '.$name->l_name) : __('financials.vendor') }} <small><bdi>#{{ $entry->seller_id }}</bdi></small></td>
        <td class="cf-numeric"><bdi>{{ number_format((float)$entry->amount,3) }} {{ $flow === 'deposits' ? ($entry->currency_code ?: '—') : $baseCurrency }}</bdi></td>
        <td><span class="fin-badge">{{ $flow === 'withdrawals' ? __('financial-registers.cards.withdrawals_'.$entry->approved) : $label($entry->status ?? $entry->direction) }}</span></td>
        <td>@if($flow === 'settlements')<span>{{ __('company-financials.columns.due_at') }}: <bdi>{{ $entry->due_at ?: '—' }}</bdi></span>@if($entry->released_at)<small>{{ __('company-financials.released') }}: <bdi>{{ $entry->released_at }}</bdi></small>@endif
        @elseif($flow === 'deposits'){{ $label($entry->payment_method) }}<small><bdi>{{ $entry->transaction_reference ?: '—' }}</bdi></small>
        @elseif($flow !== 'withdrawals'){{ __('financial-registers.cards.'.$entry->bucket) }}<small>{{ $label($entry->event_type) }}</small><small><bdi>{{ $entry->reference_code ?: '—' }}</bdi></small>@endif</td>
        <td><bdi>{{ $entry->created_at }}</bdi></td>
        @if($isSettlements && \App\Utils\Helpers::module_permission_check('people'))<td>@include('admin-views.financials._settlement-actions',['settlement'=>$entry])</td>@endif
    </tr>@endforeach
    </tbody></table></div><div class="cf-pagination">{{ $rows->links() }}</div>@endif
    </section>
    @unless($isSettlements)
    <details class="fin-summary cf-extra-summaries"><summary>{{ __('financial-registers.more_summaries') }}<i class="tio-chevron-down" aria-hidden="true"></i></summary><div class="cf-rule-body cf-extra-body">
        @include('admin-views.financials._vendor-cards',['vendorCardGroups'=>['positions'=>['pending','sales_total','pending_withdraw','withdrawn'],'deposits'=>['deposits_pending','deposits_paid','deposits_rejected','deposits_reversed'],'withdrawals'=>['withdrawals_0','withdrawals_1','withdrawals_2'],'checks'=>['missing_references','duplicate_keys']]])
    </div></details>
    @endunless
</div>
