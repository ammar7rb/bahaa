@extends('layouts.admin.app')
@section('title', __('financials.company'))
@include('admin-views.financials._setup')
@push('css_or_js')
<link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/company-financials.css') }}?v=20261022.4">
<link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/financial-activity.css') }}?v=20261022.4">
@endpush
@section('content')
@php
    $tr = static fn ($key) => __('company-financials.'.$key);
    $label = static fn ($value) => $value === null || $value === '' ? '—' : (\Illuminate\Support\Facades\Lang::has('financials.labels.'.$value) ? __('financials.labels.'.$value) : $value);
    $money = static fn ($value, $currency) => $value === null ? '—' : number_format((float)$value,3).' '.($currency ?: '—');
    $icons = ['incoming'=>'tio-receipt','sales'=>'tio-receipt','packages'=>'tio-cube','shipping'=>'tio-truck','invoice_dues'=>'tio-document-text','shipping_dues'=>'tio-time','returns'=>'tio-replay','vendor_accounts'=>'tio-wallet','settlements'=>'tio-time'];
    $columns = match($tab) {
        'sales'=>['reference','account','date','amount','merchandise_sale','procurement','merchandise_margin','commission','fulfillment','payment','settlement'],
        'packages'=>['reference','account','package','paid','historical','current_price','date','starts_at','expires_at','subscription_status','payment'],
        'shipping'=>['reference','account','date','shipping_cost','company_shipping','vendor_shipping','vendor_cost','payment','settlement'],
        'invoice_dues'=>['reference','account','date','amount','due_at','settlement_amount','fulfillment','payment','settlement'],
        'shipping_dues'=>['reference','account','date','vendor_shipping','due_at','fulfillment','payment','settlement'],
        'returns'=>['reference','account','date','returned','allocation','processing','payment'],
        default=>[],
    };
@endphp
<div class="content container-fluid financials company-financials" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <header class="fin-heading"><div><h1>{{ __('financials.company') }}</h1><p>{{ $tr('intro') }}</p></div><span class="fin-readonly"><i class="tio-lock-outlined" aria-hidden="true"></i>{{ $tab === 'settlements' && \App\Utils\Helpers::module_permission_check('people') ? __('financial-registers.admin_actions') : __('financials.read_only') }}</span></header>
    @include('admin-views.financials._navigation')
    <nav class="cf-tabs" aria-label="{{ __('financials.company') }}">
        @foreach($icons as $key=>$icon)
        <a href="{{ route('admin.report.financials.company', array_filter(\Illuminate\Support\Arr::only($filters,['from','to','seller_id','customer_id','q','per_page'])) + ['tab'=>$key]) }}" @if($tab === $key) aria-current="page" @endif><i class="{{ $icon }}" aria-hidden="true"></i>{{ in_array($key,['vendor_accounts','settlements']) ? __('financial-registers.tabs.'.$key) : $tr('tabs.'.$key) }}</a>
        @endforeach
    </nav>
    @if($loadError ?? false)
        <section class="fin-notice" role="alert"><p>{{ $tr('error') }}</p><a class="fin-button" href="{{ request()->fullUrl() }}">{{ $tr('retry') }}</a></section>
    @else
    @if($tab === 'incoming')
        @include('admin-views.financials._incoming')
    @elseif(in_array($tab,['vendor_accounts','settlements']))
        @include('admin-views.financials._company-vendors')
    @else
    @if($errors->any())<div class="fin-notice" role="alert">{{ $errors->first() }}</div>@endif
    @if($missing)<div class="fin-notice" role="status">{{ $tr('missing') }}</div>@endif
    <form method="get" action="{{ route('admin.report.financials.company') }}" class="fin-filters" data-cf-form>
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if(!empty($filters['metric']))<input type="hidden" name="metric" value="{{ $filters['metric'] }}">@endif
        <div class="fin-filter-grid">
            <label>{{ __('financials.from') }}<input type="date" name="from" value="{{ $filters['from'] }}" required></label>
            <label>{{ __('financials.to') }}<input type="date" name="to" value="{{ $filters['to'] }}" required></label>
            <label>{{ __('financials.seller_id') }}<input type="number" min="1" name="seller_id" value="{{ $filters['seller_id'] ?? '' }}"></label>
            <label>{{ __('financials.customer_id') }}<input type="number" min="1" name="customer_id" value="{{ $filters['customer_id'] ?? '' }}"></label>
            <label>{{ __('financials.status') }}<select name="status"><option value="">{{ $tr('all') }}</option>@foreach($statuses as $state)<option value="{{ $state }}" @selected(($filters['status'] ?? '') === $state)>{{ $label($state) }}</option>@endforeach</select></label>
            <label>{{ $tr('method') }}<select name="method"><option value="">{{ $tr('all') }}</option>@foreach($methods as $method)<option value="{{ $method }}" @selected(($filters['method'] ?? '') === $method)>{{ $label($method) }}</option>@endforeach</select></label>
            @if($tab === 'packages')<label>{{ $tr('account_type') }}<select name="account_type"><option value="">{{ $tr('all') }}</option>@foreach(['customer','vendor'] as $type)<option value="{{ $type }}" @selected(($filters['account_type'] ?? '') === $type)>{{ $tr($type) }}</option>@endforeach</select></label>@endif
            @if(in_array($tab,['overview','sales','shipping','invoice_dues','shipping_dues']))
            <label>{{ $tr('settlement_status') }}<select name="settlement_status"><option value="">{{ $tr('all') }}</option>@foreach(['held','disputed','released','reversed'] as $state)<option value="{{ $state }}" @selected(($filters['settlement_status'] ?? '') === $state)>{{ $tr($state) }}</option>@endforeach</select></label>
            @endif
            @if(in_array($tab,['invoice_dues','shipping_dues']))<label>{{ $tr('due') }}<select name="due"><option value="">{{ $tr('all') }}</option>@foreach(['upcoming'=>'upcoming','due'=>'today','overdue'=>'overdue'] as $key=>$title)<option value="{{ $key }}" @selected(($filters['due'] ?? '') === $key)>{{ $tr($title) }}</option>@endforeach</select></label>@endif
            @if($tab === 'returns')<label>{{ __('financials.source') }}<select name="flow"><option value="">{{ $tr('all') }}</option>@foreach(['refunds','refund_requests','refund_admin','refund_ledger','refund_edits'] as $flow)<option value="{{ $flow }}" @selected(($filters['flow'] ?? '') === $flow)>{{ in_array($flow,['refunds']) ? __('financials.sources.refunds') : $tr($flow) }}</option>@endforeach</select></label>@endif
            <label class="cf-search">{{ $tr('search') }}<input type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}"></label>
            <label>{{ __('financials.per_page') }}<select name="per_page">@foreach([20,50,100] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 20) == $size)>{{ $size }}</option>@endforeach</select></label>
        </div>
        <div class="fin-filter-actions"><button type="submit" class="fin-button fin-primary"><i class="tio-filter-list" aria-hidden="true"></i>{{ __('financials.apply') }}</button><a class="fin-button" href="{{ route('admin.report.financials.company',['tab'=>$tab]) }}">{{ __('financials.reset') }}</a><span class="fin-help cf-load" role="status" hidden>{{ $tr('loading') }}</span></div>
    </form>
    <div class="cf-content" id="cf-records">
        @if(!empty($filters['metric']))<div class="fin-notice">{{ __('financial-registers.selected_metric') }}: {{ \Illuminate\Support\Facades\Lang::has('company-financials.metrics.'.$filters['metric']) ? $tr('metrics.'.$filters['metric']) : $tr($filters['metric']) }} <a href="{{ route('admin.report.financials.company',\Illuminate\Support\Arr::except($filters,['metric','page'])) }}">{{ __('financial-registers.clear_metric') }}</a></div>@endif
        <p class="fin-help cf-filter-note">{{ $tr('filtered') }}</p>
        @foreach($totals as $category=>$metrics)
        <section class="cf-category" aria-labelledby="cf-title-{{ $category }}">
            <div class="fin-section-title"><h2 id="cf-title-{{ $category }}">{{ $tr('tabs.'.$category) }}</h2>@if($tab === 'overview')<a href="{{ route('admin.report.financials.company',array_replace(array_filter($filters),['tab'=>$category])) }}">{{ __('financials.details') }}</a>@endif</div>
            <div class="cf-metrics">
                @foreach($metrics as $key=>$values)
                <div class="cf-metric"><span>{{ \Illuminate\Support\Facades\Lang::has('company-financials.metrics.'.$key) ? $tr('metrics.'.$key) : $tr($key) }}</span><strong>@forelse($values as $value)<bdi>{{ $money($value->amount,$value->currency) }}</bdi>@empty<span class="fin-help">—</span>@endforelse</strong><small>{{ $values->sum('records') }} {{ $tr('count') }}</small><a class="fin-button cf-register-button" href="{{ route('admin.report.financials.company',array_replace(\Illuminate\Support\Arr::except($filters,['page','source','metric']),['tab'=>$category,'metric'=>$key])) }}">{{ __('financial-registers.open_register') }}</a></div>
                @endforeach
            </div>
            @if($category === 'sales')<p class="fin-help">{{ $tr('inhouse_note') }}</p>@endif
            @if($tab === 'overview')<p class="fin-help">{{ $tr('notes.'.$category) }}</p>@endif
        </section>
        @endforeach
        @if($tab === 'overview') @include('admin-views.financials._vendor-cards',['vendorCardGroups'=>['balances'=>['available','operating']]]) @endif
        @if($records)
        <section class="fin-panel cf-record-panel" aria-labelledby="cf-table-title">
            <div class="fin-panel-heading"><h2 id="cf-table-title">{{ $tr('tabs.'.$tab) }}</h2><span class="fin-help">{{ number_format($records->total()) }} {{ $tr('matching') }}</span></div>
            <p class="fin-table-note">{{ $tr('notes.'.$tab) }}</p>
            @if($records->isEmpty())<div class="cf-empty"><i class="tio-search" aria-hidden="true"></i><h2>{{ $tr('no_records') }}</h2><p>{{ $tr('reset_hint') }}</p><a class="fin-button" href="{{ route('admin.report.financials.company',['tab'=>$tab]) }}">{{ __('financials.reset') }}</a></div>
            @else
            <div class="fin-table-scroll" role="region" aria-label="{{ $tr('tabs.'.$tab) }}" tabindex="0"><table class="fin-table cf-table"><thead><tr>@foreach($columns as $column)<th scope="col">{{ $column === 'historical' ? $tr('historical') : $tr('columns.'.$column) }}</th>@endforeach</tr></thead><tbody>
                @foreach($records as $row)<tr>
                @foreach($columns as $column)<td @class(['cf-numeric'=>in_array($column,['amount','commission','paid','historical','current_price','shipping_cost','company_shipping','vendor_shipping','settlement_amount','returned'])])>
                    @switch($column)
                    @case('reference')
                        <strong><bdi>#{{ $row->id }}</bdi></strong>
                        @if(in_array($row->source,['orders','customer_packages','vendor_packages','refunds']))<a class="cf-record-link" href="{{ route('admin.report.financials.entry',[$row->source,$row->id]) }}">{{ $tr('view') }}</a>@endif
                        @if($row->order_id) @if(\App\Utils\Helpers::module_permission_check('orders'))<a class="cf-record-link" href="{{ route('admin.orders.details',$row->order_id) }}">{{ $tr('order') }} <bdi>#{{ $row->order_id }}</bdi></a>@else<small>{{ $tr('columns.reference') }} <bdi>#{{ $row->order_id }}</bdi></small>@endif @endif
                        @if($row->reference)<small><bdi>{{ $row->reference }}</bdi></small>@endif
                        @if($tab === 'returns')<small>{{ $row->source === 'refunds' ? __('financials.sources.refunds') : $tr($row->source) }}</small>@endif
                        @break
                    @case('account')
                        @foreach(['vendor'=>'seller_id','customer'=>'customer_id'] as $type=>$field) @if($row->$field) @php($party = $names[$type]->get($row->$field))<span class="cf-account">{{ $party ? trim($party->f_name.' '.$party->l_name) : $tr($type) }} <small>{{ $tr($type) }} <bdi>#{{ $row->$field }}</bdi></small></span>@endif @endforeach
                        @break
                    @case('date')<bdi>{{ $row->date ?? '—' }}</bdi>@break
                    @case('merchandise_sale') @case('procurement') @case('merchandise_margin') @case('amount') @case('commission') @case('shipping_cost') @case('vendor_shipping') @case('vendor_cost') @case('settlement_amount')<bdi>{{ $money($row->$column,$row->currency) }}</bdi>@break
                    @case('company_shipping')<bdi>{{ $money($row->confirmed && $row->fulfilled ? $row->company_shipping : null,$row->currency) }}</bdi>@break
                    @case('paid')<bdi>{{ $money($row->confirmed ? $row->amount : null,$row->currency) }}</bdi>@break
                    @case('historical') @case('returned')<bdi>{{ $money($row->amount,$row->currency) }}</bdi>@break
                    @case('current_price')<bdi>{{ $money($row->current_price,$row->account_type === 'customer' ? 'EGP' : $baseCurrency) }}</bdi>@break
                    @case('package')<strong>{{ $row->name ?? $tr('unknown') }}</strong>@break
                    @case('starts_at') @case('expires_at')<bdi>{{ $row->$column ?? '—' }}</bdi>@break
                    @case('subscription_status')<span class="fin-badge">{{ $label($row->subscription_status) }}</span>@break
                    @case('fulfillment')<span class="fin-badge">{{ \Illuminate\Support\Facades\Lang::has('company-financials.fulfillment_states.'.$row->name) ? $tr('fulfillment_states.'.$row->name) : $label($row->name) }}</span>@break
                    @case('payment')<span class="fin-badge">{{ $row->source === 'refund_requests' ? $tr('pending') : ($row->confirmed ? $tr('paid') : $label($row->status)) }}</span><small>{{ $label($row->method) }}</small>@if($row->status === 'paid' && !$row->confirmed)<small>{{ $tr('pending') }}</small>@endif @break
                    @case('settlement')<span class="fin-badge">{{ $row->settlement_status ? $tr($row->settlement_status) : $tr('no_settlement') }}</span>@break
                    @case('due_at')
                        @if($row->due_at)<bdi>{{ $row->due_at }}</bdi>@if(in_array($row->settlement_status,['held','disputed']))<span class="fin-badge">{{ $tr(\Carbon\Carbon::parse($row->due_at)->isToday() ? 'today' : (\Carbon\Carbon::parse($row->due_at)->isPast() ? 'overdue' : 'upcoming')) }}</span>@endif @else<span class="fin-help">{{ $tr('due_unknown') }}</span>@endif
                        @break
                    @case('allocation')<span>{{ in_array($row->allocation,['seller','admin']) ? $tr('allocation_'.($row->allocation === 'seller' ? 'vendor' : 'admin')) : $tr('unavailable') }}</span>@break
                    @case('processing')<span class="fin-badge">{{ $label($row->subscription_status ?? $row->status) }}</span>@break
                    @default<span class="fin-help">{{ $tr('unavailable') }}</span>
                    @endswitch
                </td>@endforeach
                </tr>@endforeach
            </tbody></table></div>
            <div class="cf-pagination">{{ $records->links() }}</div>
            @endif
        </section>
        @endif
        <details class="fin-summary cf-rules"><summary>{{ $tr('rules') }}<i class="tio-chevron-down" aria-hidden="true"></i></summary><div class="cf-rule-body"><p>{{ $tr('notes.'.$tab) }}</p>@if($tab === 'packages')<p>{{ $tr('currency_note') }}</p>@endif @if($tab !== 'overview')<p>{{ $tr('notes.overview') }}</p>@endif<a href="{{ route('admin.report.financials.company',['source'=>$tab === 'packages' ? 'customer_packages' : ($tab === 'returns' ? 'refunds' : 'orders'),'from'=>$filters['from'],'to'=>$filters['to']]) }}">{{ $tr('other_records') }}</a></div></details>
    </div>
    @endif
    @endif
</div>
@endsection
@push('script')
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/company-financials.js') }}?v=20261022.4" defer></script>
@endpush
