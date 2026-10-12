@extends('layouts.admin.app')
@section('title', translate('Advertising_Performance_Report'))
@section('content')
<div class="content container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="mb-1">{{ translate('Advertising_Performance_Report') }} #{{ $promotion->id }}</h1><p class="text-muted mb-0">{{ $promotion->product?->name }} — M{{ $promotion->seller_id }} {{ $promotion->seller?->shop?->name }}</p></div><a href="{{ route('admin.vendors.quota-control.promotions.index') }}" class="btn btn-outline-primary">{{ translate('Back') }}</a></div>
    <div class="alert alert-soft-info">{{ translate('This_report_contains_aggregate_campaign_metrics_only_and_does_not_store_customer_browsing_history') }}.</div>
    <div class="row g-3">
        @foreach(['impressions' => 'Impressions', 'clicks' => 'Clicks', 'orders' => 'Orders', 'units_sold' => 'Units_Sold', 'sales_amount' => 'Sales_Amount', 'click_rate' => 'Click_Rate', 'conversion_rate' => 'Conversion_Rate'] as $key => $label)
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted fs-12">{{ translate($label) }}</div><strong class="fs-22">@if($key === 'sales_amount'){{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $performance[$key])) }}@else{{ $performance[$key] }}{{ in_array($key, ['click_rate','conversion_rate']) ? '%' : '' }}@endif</strong></div></div></div>
        @endforeach
    </div>
    <div class="card mt-3"><div class="card-body"><strong>{{ translate('Campaign_Period') }}:</strong> {{ $performance['period_from'] }} — {{ $performance['period_to'] }}<br><strong>{{ translate('Placement') }}:</strong> {{ translate($promotion->placement_scope) }} / {{ $promotion->placement_key ?: translate('All') }} / #{{ $promotion->approved_position }}</div></div>
</div>
@endsection
