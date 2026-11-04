@extends('layouts.admin.app')
@section('title', __('financials.details'))
@include('admin-views.financials._setup')
@section('content')
@php($financialLabel = static fn ($value) => $value === null || $value === '' ? '—' : (\Illuminate\Support\Facades\Lang::has('financials.labels.'.$value) ? __('financials.labels.'.$value) : $value))
<div class="content container-fluid financials" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <a class="fin-back" href="{{ route('admin.report.financials.'.($entry['customer_id'] ? 'customers' : 'company'),['tab'=>'transactions','source'=>$entry['source'],'customer_id'=>$entry['customer_id'],'from'=>substr($entry['date'],0,10),'to'=>substr($entry['date'],0,10)]) }}">{{ __('financials.back') }}</a>
    <header class="fin-heading"><div><h1>{{ __('financials.details') }}</h1><p>{{ __('financials.sources.'.$entry['source']) }} · <bdi>{{ $entry['reference'] }}</bdi></p></div><span class="fin-readonly"><i class="tio-lock-outlined" aria-hidden="true"></i>{{ __('financials.read_only') }}</span></header>
    <section class="fin-panel">
        <div class="fin-entry-summary">
            @foreach($entry['source'] === 'wallet' ? ['credit'=>'credit','debit'=>'amount','admin_bonus'=>'bonus','balance'=>'balance'] : ['amount'=>'amount'] as $label=>$column)
                <div><span>{{ __('financials.'.$label) }}</span><strong><bdi>{{ number_format((float)$entry[$column],3) }}</bdi> <small>{{ $entry['currency'] }}</small></strong></div>
            @endforeach
            <div><span>{{ __('financials.original_status') }}</span><strong>{{ $financialLabel($entry['status']) }}</strong><small><bdi>{{ $entry['status'] }}</bdi></small></div><div><span>{{ __('financials.method') }}</span><strong>{{ $financialLabel($entry['method']) }}</strong></div>
        </div>
        @if($entry['source'] === 'wallet')<p class="fin-table-note">{{ __('financials.wallet_note') }}</p>@endif
        <dl class="fin-detail-grid">
            <div><dt>{{ __('financials.record_id') }}</dt><dd><bdi>#{{ $entry['id'] }}</bdi></dd></div>
            @foreach(['customer_id','seller_id'] as $party) @if($entry[$party])<div><dt>{{ __('financials.'.$party) }}</dt><dd><bdi>#{{ $entry[$party] }}</bdi></dd></div>@endif @endforeach
            @foreach($details as $field=>$value)
                <div><dt>{{ __('financials.fields.'.$field) }}</dt><dd>@if($value === null || $value === '') — @else <bdi>{{ $financialLabel($value) }}</bdi> @endif</dd></div>
            @endforeach
        </dl>
        <div class="fin-detail-actions">
            @if($entry['customer_id'])<a class="fin-button" href="{{ route('admin.report.financials.customers',['customer_id'=>$entry['customer_id'],'tab'=>'transactions','from'=>substr($entry['date'],0,10),'to'=>substr($entry['date'],0,10)]) }}">{{ __('financials.customer_statement') }}</a>@endif
            @if(\App\Utils\Helpers::module_permission_check('orders')) @foreach($orderIds as $orderId)<a class="fin-button" href="{{ route('admin.orders.details',$orderId) }}">{{ __('financials.order_link') }} <bdi>#{{ $orderId }}</bdi></a>@endforeach @endif
        </div>
    </section>
    @if($related)<section class="fin-panel"><div class="fin-panel-heading"><div><h2>{{ __('financials.related') }}</h2><p class="fin-help">{{ __('financials.related_note') }}</p></div></div><ul class="fin-linked">@foreach($related as $record)<li><a href="{{ route('admin.report.financials.entry',[$record['source'],$record['id']]) }}">{{ __('financials.sources.'.$record['source']) }} <bdi>#{{ $record['id'] }}</bdi></a><span>{{ $financialLabel($record['status']) }}</span></li>@endforeach</ul></section>@endif
    <p class="fin-help">{{ __('financials.rules_text') }}</p>
</div>
@endsection
