@php
    $package = $overview['subscription'];
    $capacity = $overview['capacity'];
    $expiry = $package['expires_at'] ? \Carbon\Carbon::parse($package['expires_at'])->timezone(config('app.timezone'))->format('Y-m-d') : null;
@endphp
<section class="vendor-account-overview" aria-label="{{ translate('vendor_account_summary') }}">
    <a class="vendor-summary-card" href="{{ route('vendor.packages.index') }}">
        <span class="vendor-summary-heading"><span class="vendor-summary-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon' => 'marketing'])</span><span>{{ translate('phase5_current_package') }}</span></span>
        <strong>{{ $package['package_name'] ?: translate('phase5_no_package') }}</strong>
        <span class="{{ $package['active'] ? 'vendor-summary-status-active' : 'vendor-summary-status-inactive' }}">{{ translate('phase5_status_'.$package['status']) }}</span>
        @if($expiry)<span>{{ translate('phase5_expires') }} <bdi>{{ $expiry }}</bdi></span>@endif
        <span class="vendor-summary-link">{{ translate($package['active'] ? 'vendor_subscriptions' : 'phase5_renew_package') }}</span>
    </a>
    @foreach([['available', 'wallet', 'finance_my_wallet', 'vendor.balance.index', 'phase5_wallet_hint'], ['operating', 'invoices', 'phase5_payments', 'vendor.balance.payments', 'phase5_payments_hint']] as [$bucket, $icon, $label, $destination, $hint])
        <a class="vendor-summary-card" href="{{ route($destination) }}">
            <span class="vendor-summary-heading"><span class="vendor-summary-icon">@include('layouts.vendor.partials.v2._workspace-icon', compact('icon'))</span><span>{{ translate($label) }}</span></span>
            <strong><bdi>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $overview['balances'][$bucket]), currencyCode: getCurrencyCode()) }}</bdi></strong>
            <span>{{ translate($hint) }}</span>
            <span class="vendor-summary-link">{{ translate('phase5_view_details') }}</span>
        </a>
    @endforeach
    <a class="vendor-summary-card" href="{{ route('vendor.products.list', ['type' => 'all']) }}">
        <span class="vendor-summary-heading"><span class="vendor-summary-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon' => 'products'])</span><span>{{ translate('phase5_spaces') }}</span></span>
        <strong><bdi dir="ltr">{{ $capacity['used'] }} / {{ $capacity['total'] }}</bdi> <small>{{ translate('phase5_used') }}</small></strong>
        <span>{{ translate('phase5_available') }}: {{ $capacity['available'] }} · {{ translate('phase5_reserved') }}: {{ $capacity['reserved'] }}</span>
        <span>{{ translate('phase5_included') }}: {{ $capacity['included'] }} · {{ translate('phase5_additional') }}: {{ $capacity['additional'] }}</span>
        <span class="vendor-summary-link">{{ translate('phase5_manage_spaces') }}</span>
    </a>
</section>
