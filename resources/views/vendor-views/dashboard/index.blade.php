@extends('layouts.vendor.app')
@section('title', translate('dashboard'))
@section('content')
@php
    $account = $sellerOverview['account_overview'];
    $package = $account['subscription'];
    $capacity = $account['capacity'];
    $expiry = $package['expires_at'] ? \Carbon\Carbon::parse($package['expires_at'])->timezone(config('app.timezone'))->format('Y-m-d') : null;
    $seller = auth('seller')->user();
@endphp
<div class="content container-fluid vendor-dashboard sigma-dashboard">
    <div class="sigma-dashboard-intro">
        <div><h1>{{ translate('dashboard') }}</h1><p>{{ translate('vendor_workspace_intro') }}</p></div>
    </div>
    <div class="sigma-dashboard-main">
        <section class="sigma-panel sigma-wallet" aria-labelledby="sigma-wallet-title">
            <div class="sigma-panel-heading"><div><h2 id="sigma-wallet-title">{{ translate('finance_my_wallet') }}</h2><p>{{ translate('sigma_sales_available') }}</p></div><span class="sigma-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'wallet'])</span></div>
            <a class="sigma-balance" href="{{ route('vendor.balance.index') }}"><span>{{ translate('sigma_sales_balance') }}</span><strong><bdi>{{ webCurrencyConverter(amount: $account['balances']['available']) }}</bdi></strong><small>{{ translate('available_balance') }}</small></a>
            <div class="sigma-wallet-actions"><a href="{{ route('vendor.balance.index') }}">{{ translate('sigma_sales_balance') }}</a><a class="sigma-outline-action" href="{{ route('vendor.balance.payments') }}">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'add']) {{ translate('fund_wallet') }}</a></div>
            <p class="sigma-wallet-note">{{ translate('payments_balance') }}: <bdi>{{ webCurrencyConverter(amount: $account['balances']['operating']) }}</bdi></p>
        </section>
        <section class="sigma-panel sigma-package" aria-labelledby="sigma-package-title">
            <div class="sigma-panel-heading"><div><h2 id="sigma-package-title">{{ translate('phase5_current_package') }}</h2><p>{{ translate('vendor_subscriptions') }}</p></div><span class="sigma-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'marketing'])</span></div>
            <div class="sigma-package-name"><strong>{{ $package['package_name'] ?: translate('phase5_no_package') }}</strong><span class="sigma-package-status {{ $package['active'] ? 'is-active' : '' }}">{{ translate('phase5_status_'.$package['status']) }}</span></div>
            <dl class="sigma-package-details"><div><dt>{{ translate('phase5_expires') }}</dt><dd><bdi>{{ $expiry ?: '—' }}</bdi></dd></div><div><dt>{{ translate('sigma_ad_allowance') }}</dt><dd><bdi dir="ltr">{{ $capacity['used'] }} / {{ $capacity['total'] }}</bdi></dd></div></dl>
            <a class="sigma-text-link" href="{{ route('vendor.packages.index') }}">{{ translate('sigma_manage_subscription') }} @include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'arrow-left'])</a>
        </section>
    </div>
    <a class="sigma-offer" href="{{ route('vendor.packages.index') }}"><span class="sigma-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'marketing'])</span><span><strong>{{ translate('sigma_explore_packages') }}</strong><small>{{ translate('sigma_packages_hint') }}</small></span><span class="sigma-offer-link">{{ translate('sigma_explore_packages') }} @include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'arrow-left'])</span></a>
    <section class="sigma-shortcuts" aria-label="{{ translate('vendor_account_summary') }}">
        <a class="sigma-shortcut" href="{{ route('vendor.products.list', ['type'=>'all']) }}"><span class="sigma-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'marketing'])</span><h2>{{ translate('sigma_active_ads') }}</h2><strong>{{ $activeAdvertisementCount }}</strong><span class="sigma-shortcut-link">{{ translate('sigma_view_ads') }} @include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'arrow-left'])</span></a>
        <a class="sigma-shortcut" href="{{ route('vendor.orders.list', ['status'=>'all']) }}"><span class="sigma-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'orders'])</span><h2>{{ translate('orders') }}</h2><strong>{{ $sellerOverview['sales']['received_orders_count'] }}</strong><span class="sigma-shortcut-link">{{ translate('view_orders') }} @include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'arrow-left'])</span></a>
        <a class="sigma-shortcut" href="{{ route('vendor.products.list', ['type'=>'all']) }}"><span class="sigma-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'view'])</span><h2>عدد المشاهدات</h2><strong>{{ number_format($activeProductVisits + $activeProductViewAdditions) }}</strong><small>زيارات مسجلة {{ number_format($activeProductVisits) }} · إضافات إدارية {{ number_format($activeProductViewAdditions) }}</small><span class="sigma-shortcut-link">{{ translate('sigma_view_products') }} @include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'arrow-left'])</span></a>
        <a class="sigma-shortcut" href="{{ route('vendor.products.add') }}"><span class="sigma-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'add'])</span><h2>{{ translate('add_product') }}</h2><p>{{ translate('sigma_add_product_hint') }}</p><span class="sigma-shortcut-link">{{ translate('add_product') }} @include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'arrow-left'])</span></a>
        <a class="sigma-shortcut sigma-support" href="{{ route('vendor.messages') }}"><span class="sigma-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'messages'])</span><h2>{{ translate('sigma_technical_support') }}</h2><p>{{ translate('sigma_support_description') }}</p><span class="sigma-shortcut-link">{{ translate('sigma_contact_support') }} @include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'arrow-left'])</span></a>
    </section>
</div>
@endsection
