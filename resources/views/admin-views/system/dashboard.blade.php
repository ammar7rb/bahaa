@extends('layouts.admin.app')
@section('title', translate('dashboard'))
@section('content')
<div class="admin-dashboard">
    <div class="admin-page-heading"><div><h1>{{ translate('dashboard') }}</h1><p>{{ translate('workspace_dashboard_help') }}</p></div></div>
    <div class="admin-summary-grid">
        @foreach([
            'orders' => ['workspace_total_orders', 'shopping-cart', route('admin.orders.list', ['status' => 'all'])],
            'customers' => ['workspace_total_customers', 'users', route('admin.customer.list')],
            'vendors' => ['workspace_total_vendors', 'shop', route('admin.vendors.vendor-list')],
            'products' => ['workspace_total_products', 'box', route('admin.products.product-gallery')],
            'visits' => ['workspace_site_visits', 'chart-histogram', route('admin.report.operational-print', ['section'=>'visits', 'q'=>'web'])]
        ] as $key => [$label, $icon, $url])
            @if($totals[$key] !== null)
                <a href="{{ $url }}" class="admin-summary-card" data-summary-kind="{{ $key }}" @if($key === 'visits') title="{{ translate('workspace_visits_definition') }}" @endif><span class="admin-summary-icon"><i class="fi fi-rr-{{ $icon }}" aria-hidden="true"></i></span><span class="admin-summary-copy"><span>{{ translate($label) }}</span><strong data-workspace-total="{{ $key }}">{{ number_format($totals[$key]) }}</strong></span></a>
            @else
                <div class="admin-summary-card is-restricted" data-summary-kind="{{ $key }}"><span class="admin-summary-icon"><i class="fi fi-rr-lock" aria-hidden="true"></i></span><span class="admin-summary-copy"><span>{{ translate($label) }}</span><small>{{ translate('workspace_access_restricted') }}</small></span></div>
            @endif
        @endforeach
    </div>
    @if(\App\Utils\Helpers::module_permission_check('people'))
        @include('admin-views.system.partials.seller-activation-inbox')
    @endif
</div>
@endsection
