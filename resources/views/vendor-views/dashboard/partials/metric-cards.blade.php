@php
    $package = $overview['subscription'];
    $capacity = $overview['capacity'];
    $expiry = $package['expires_at'] ? \Carbon\Carbon::parse($package['expires_at'])->timezone(config('app.timezone'))->format('Y-m-d') : null;
@endphp
<section class="vendor-dashboard-metrics" aria-label="{{ translate('vendor_account_summary') }}">
    <a class="vendor-dashboard-metric metric-package" href="{{ route('vendor.packages.index') }}">
        <span class="vendor-dashboard-metric-label">{{ translate('phase5_current_package') }}</span>
        <span class="vendor-dashboard-metric-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'marketing'])</span>
        <strong>{{ $package['package_name'] ?: translate('phase5_no_package') }}</strong>
        <span class="vendor-dashboard-metric-detail">{{ translate('phase5_status_'.$package['status']) }}@if($expiry) · {{ translate('phase5_expires') }} <bdi>{{ $expiry }}</bdi>@endif</span>
    </a>
    @foreach([['available','wallet','finance_my_wallet','vendor.balance.index'],['operating','invoices','phase5_payments','vendor.balance.payments']] as [$bucket,$icon,$label,$destination])
        <a class="vendor-dashboard-metric metric-{{ $bucket }}" href="{{ route($destination) }}">
            <span class="vendor-dashboard-metric-label">{{ translate($label) }}</span>
            <span class="vendor-dashboard-metric-icon">@include('layouts.vendor.partials.v2._workspace-icon', compact('icon'))</span>
            <strong><bdi>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $overview['balances'][$bucket]), currencyCode: getCurrencyCode()) }}</bdi></strong>
        </a>
    @endforeach
    <a class="vendor-dashboard-metric metric-orders" href="{{ route('vendor.orders.list', ['status'=>'all']) }}">
        <span class="vendor-dashboard-metric-label">{{ translate('orders') }}</span>
        <span class="vendor-dashboard-metric-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'orders'])</span>
        <strong>{{ $sales['received_orders_count'] }}</strong>
    </a>
    <a class="vendor-dashboard-metric metric-spaces" href="{{ route('vendor.products.list', ['type'=>'all']) }}">
        <span class="vendor-dashboard-metric-label">{{ translate('phase5_spaces') }}</span>
        <span class="vendor-dashboard-metric-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'products'])</span>
        <strong><bdi dir="ltr">{{ $capacity['used'] }} / {{ $capacity['total'] }}</bdi> <small>{{ translate('phase5_used') }}</small></strong>
        <span class="vendor-dashboard-metric-detail">{{ translate('phase5_available') }}: {{ $capacity['available'] }} · {{ translate('phase5_reserved') }}: {{ $capacity['reserved'] }}<br>{{ translate('phase5_included') }}: {{ $capacity['included'] }} · {{ translate('phase5_additional') }}: {{ $capacity['additional'] }}</span>
    </a>
    <a class="vendor-dashboard-metric metric-sales" href="{{ route('vendor.report.order-report') }}">
        <span class="vendor-dashboard-metric-label">{{ translate('sold_units') }}</span>
        <span class="vendor-dashboard-metric-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'reports'])</span>
        <strong>{{ $sales['sold_units'] }}</strong>
    </a>
</section>
