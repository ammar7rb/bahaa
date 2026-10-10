@extends('layouts.admin.app')
@section('title', translate('dashboard'))
@section('content')
<div class="admin-dashboard">
    <div class="admin-page-heading"><div><h1>{{ translate('dashboard') }}</h1><p>{{ translate('workspace_dashboard_help') }}</p></div></div>
    <div class="admin-summary-grid">
        @foreach([
            'orders' => ['orders', 'shopping-bag', route('admin.orders.list', ['status' => 'all'])],
            'customers' => ['customers', 'users', route('admin.customer.list')],
            'vendors' => ['vendors', 'shop', route('admin.vendors.vendor-list')],
            'products' => ['products', 'box', route('admin.products.product-gallery')]
        ] as $key => [$label, $icon, $url])
            @if($totals[$key] !== null)
                <a href="{{ $url }}" class="admin-summary-card"><span class="admin-summary-top"><span class="admin-summary-icon"><i class="fi fi-rr-{{ $icon }}" aria-hidden="true"></i></span><span>{{ translate($label) }}</span></span><span class="admin-summary-bottom"><strong data-workspace-total="{{ $key }}">{{ number_format($totals[$key]) }}</strong><span class="admin-summary-link"><i class="fi fi-rr-arrow-small-left" aria-hidden="true"></i><span class="sr-only">{{ translate('view_all') }}</span></span></span></a>
            @else
                <div class="admin-summary-card is-restricted"><span class="admin-summary-top">{{ translate($label) }}<i class="fi fi-rr-lock" aria-hidden="true"></i></span><span>{{ translate('workspace_access_restricted') }}</span></div>
            @endif
        @endforeach
    </div>
    @if(\App\Utils\Helpers::module_permission_check('people'))
        @include('admin-views.system.partials.seller-activation-inbox')
    @endif
</div>
@endsection
