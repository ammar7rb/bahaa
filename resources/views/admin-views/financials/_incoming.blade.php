@php
    $financialLabel=$label;
    $statement=false;
    $action=route('admin.report.financials.company');
@endphp
<div class="fa-intro"><h2>{{ __('financial-activity.incoming') }}</h2><p>{{ __('financial-activity.incoming_intro') }}</p></div>
@include('admin-views.financials._activity-filters')
<div class="cf-content">
    <div class="fa-totals">@foreach(['external','internal'] as $movement)<section><span>{{ __('financial-activity.totals.'.$movement) }}</span><strong>@forelse($totals[$movement] as $total)<bdi>{{ number_format((float)$total->amount,1) }} {{ $total->currency ?: '—' }}</bdi>@empty — @endforelse</strong><small>{{ __('financial-activity.total_notes.'.$movement) }}</small></section>@endforeach</div>
    <p class="fin-help fa-context-note">{{ __('financial-activity.no_profit') }}</p>
    @include('admin-views.financials._activity-table')
    <details class="fin-summary cf-rules"><summary>{{ __('company-financials.rules') }}<i class="tio-chevron-down" aria-hidden="true"></i></summary><div class="cf-rule-body"><p>{{ __('financial-activity.receipt_rules') }}</p></div></details>
</div>
