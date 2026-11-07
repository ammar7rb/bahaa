@extends('layouts.admin.app')
@section('title', __('financials.'.($customers ? 'customers' : 'company')))
@include('admin-views.financials._setup')
@section('content')
@php
    $financialLabel = static fn ($value) => $value === null || $value === '' ? '—' : (\Illuminate\Support\Facades\Lang::has('financials.labels.'.$value) ? __('financials.labels.'.$value) : $value);
    $accountMode = $customers && ($filters['tab'] ?? 'accounts') === 'accounts';
    $routeName = 'admin.report.financials.'.($customers ? 'customers' : 'company');
@endphp
<div class="content container-fluid financials" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <header class="fin-heading">
        <div><h1>{{ __('financials.'.($customers ? 'customers' : 'company')) }}</h1><p>{{ __('financials.'.($customers ? 'customers_intro' : 'company_intro')) }}</p></div>
        <span class="fin-readonly"><i class="tio-lock-outlined" aria-hidden="true"></i>{{ __('financials.read_only') }}</span>
    </header>
    @include('admin-views.financials._navigation')
    @if($errors->any())<div class="fin-notice" role="alert">{{ $errors->first() }}</div>@endif
    @if($unavailable)
        <div class="fin-notice" role="status"><strong>{{ __('financials.missing') }}:</strong> {{ implode('، ', array_map(fn($key) => __('financials.sources.'.$key),$unavailable)) }}. {{ __('financials.unavailable') }}</div>
    @endif
    @if(!empty($filters['metric']))<div class="fin-notice">{{ __('financial-registers.selected_metric') }}: {{ __('financials.metrics.'.$filters['metric']) }} <a href="{{ route($routeName,\Illuminate\Support\Arr::except($filters,['metric','page'])) }}">{{ __('financial-registers.clear_metric') }}</a></div>@endif
    <form method="get" action="{{ route($routeName) }}" class="fin-filters">
        @if($customers)<input type="hidden" name="tab" value="{{ $filters['tab'] ?? 'accounts' }}">@endif
        @foreach(['metric','all_dates','bucket'] as $preserved) @if(isset($filters[$preserved]))<input type="hidden" name="{{ $preserved }}" value="{{ $filters[$preserved] }}">@endif @endforeach
        <div class="fin-filter-grid">
            <label>{{ __('financials.from') }}<input type="date" name="from" value="{{ $filters['from'] }}" required></label>
            <label>{{ __('financials.to') }}<input type="date" name="to" value="{{ $filters['to'] }}" required></label>
            <label>{{ __('financials.customer_id') }}<input name="customer_id" type="number" min="1" value="{{ $filters['customer_id'] ?? '' }}" inputmode="numeric"></label>
            @unless($customers)<label>{{ __('financials.seller_id') }}<input name="seller_id" type="number" min="1" value="{{ $filters['seller_id'] ?? '' }}" inputmode="numeric"></label>@endunless
            @unless($accountMode)
                <label>{{ __('financials.source') }}<select name="source">@foreach($sources as $key=>$definition)<option value="{{ $key }}" @selected($filters['source'] === $key)>{{ __('financials.sources.'.$key) }}</option>@endforeach</select></label>
                <label>{{ __('financials.status') }}<select name="status"><option value="">{{ __('financials.all') }}</option>@foreach($options['statuses'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $financialLabel($status) }}</option>@endforeach</select></label>
                <label>{{ __('financials.method') }}<select name="method"><option value="">{{ __('financials.all') }}</option>@foreach($options['methods'] as $method)<option value="{{ $method }}" @selected(($filters['method'] ?? '') === $method)>{{ $financialLabel($method) }}</option>@endforeach</select></label>
                <label>{{ __('financials.kind') }}<select name="kind"><option value="">{{ __('financials.all') }}</option>@foreach($options['kinds'] as $kind)<option value="{{ $kind }}" @selected(($filters['kind'] ?? '') === $kind)>{{ $financialLabel($kind) }}</option>@endforeach</select></label>
                @if($filters['source'] === 'orders')<label>{{ __('financials.review') }}<select name="review"><option value="">{{ __('financials.all') }}</option>@foreach($options['reviews'] as $review)<option value="{{ $review }}" @selected(($filters['review'] ?? '') === $review)>{{ $financialLabel($review) }}</option>@endforeach</select></label>@endif
            @endunless
            <label>{{ __('financials.'.($accountMode ? 'search_accounts' : 'q')) }}<input type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}"></label>
            <label>{{ __('financials.per_page') }}<select name="per_page">@foreach([20,50,100] as $size)<option @selected(($filters['per_page'] ?? 20) == $size)>{{ $size }}</option>@endforeach</select></label>
        </div>
        <div class="fin-filter-actions"><button class="fin-button fin-primary" type="submit"><i class="tio-filter-list" aria-hidden="true"></i>{{ __('financials.apply') }}</button><a class="fin-button" href="{{ route($routeName) }}">{{ __('financials.reset') }}</a></div>
    </form>
    <section aria-labelledby="fin-overview-title" class="fin-overview">
        <div class="fin-section-title"><h2 id="fin-overview-title">{{ __('financials.overview') }}</h2><span><bdi>{{ $filters['from'] }} — {{ $filters['to'] }}</bdi></span></div>
        <p class="fin-help">{{ __('financials.scope') }}</p>
        <div class="fin-summary-grid">
            @foreach([['revenue','activity','costs'],['balances','pending','settlements']] as $columnGroups)
            <div class="fin-summary-column">
            @foreach($groups as $group=>$metrics)
                @continue(!in_array($group,$columnGroups))
                <details class="fin-summary" @if(in_array($group, $customers ? ['balances','activity'] : ['revenue','balances'])) open @endif>
                    <summary><span>{{ __('financials.groups.'.$group) }}</span><i class="tio-chevron-down" aria-hidden="true"></i></summary>
                    <dl>
                        @foreach($metrics as $metric)
                            <div class="fin-metric"><dt><span>{{ __('financials.metrics.'.$metric['key']) }}</span><small>{{ __('financials.notes.'.$metric['key']) }}</small><a class="fin-row-link" href="{{ route($routeName, array_filter(\Illuminate\Support\Arr::only($filters,['from','to','customer_id','seller_id'])) + ['tab'=>$metric['key'] === 'customer_wallets' ? 'accounts' : 'transactions','source'=>$metric['source'],'metric'=>$metric['key'] === 'customer_wallets' ? null : $metric['key'],'all_dates'=>$metric['period'] ? 0 : 1]) }}">{{ __('financial-registers.open_register') }}</a></dt>
                                <dd>
                                    @if($metric['value'] === null)<span class="fin-help">{{ __('financials.not_available') }}</span>
                                    @elseif(!$metric['value'])<span>—</span>
                                    @else @foreach($metric['value'] as $value)<span class="fin-money"><bdi>{{ number_format((float)$value['amount'],3) }}</bdi> <small>{{ $value['currency'] }}</small></span>@endforeach
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </details>
            @endforeach
            </div>
            @endforeach
        </div>
    </section>
    <section class="fin-panel" aria-labelledby="fin-records-title">
        <div class="fin-panel-heading">
            <div><h2 id="fin-records-title">{{ __('financials.'.($accountMode ? 'accounts' : 'transactions')) }}</h2><span class="fin-help">{{ number_format(($accounts ?? $records)->total()) }} {{ __('financials.results') }}</span></div>
            @if($customers)
                <nav class="fin-tabs" aria-label="{{ __('financials.customers') }}">
                    @foreach(['accounts','transactions'] as $tab)<a href="{{ route($routeName,array_merge($filters,['tab'=>$tab,'page'=>1,'q'=>null,'status'=>null,'method'=>null])) }}" @if(($tab === 'accounts') === $accountMode) aria-current="page" @endif>{{ __('financials.'.$tab) }}</a>@endforeach
                </nav>
            @else <span class="fin-category">{{ __('financials.sources.'.$filters['source']) }}</span>@endif
        </div>
        @if($accountMode)
            <p class="fin-table-note">{{ __('financials.balances_note') }} {{ __('financials.limit_note') }}</p>
            @include('admin-views.financials._accounts')
        @else
            @if($filters['source'] === 'wallet')<p class="fin-table-note">{{ __('financials.wallet_note') }}</p>@endif
            @include('admin-views.financials._records')
        @endif
        <div class="fin-pagination">{{ ($accounts ?? $records)->links() }}</div>
    </section>
    <details class="fin-rules" open><summary>{{ __('financials.rules') }}</summary><p>{{ __('financials.rules_text') }}</p><p>{{ __('financials.dates_note') }}</p><p>{{ __('financials.legacy_note') }}</p></details>
</div>
@endsection
