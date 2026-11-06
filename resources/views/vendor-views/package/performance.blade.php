@extends('layouts.vendor.app')

@section('title', translate('Package_Performance'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="mb-1">{{ translate('Package_Performance') }} — {{ $subscription->package_name }}</h1>
                <p class="mb-0 text-muted fs-12">{{ $performance['period_from'] }} — {{ $performance['period_to'] }}</p>
            </div>
            <a href="{{ route('vendor.packages.index') }}" class="btn btn-outline-primary">{{ translate('Back') }}</a>
        </div>
        <div class="row g-3">
            @foreach([
                'published_products' => 'Published_Products',
                'impressions' => 'Search_Impressions',
                'visits' => 'Product_Visits',
                'orders' => 'Orders',
                'units_sold' => 'Units_Sold',
                'visit_rate' => 'Visit_Rate',
                'conversion_rate' => 'Conversion_Rate',
            ] as $key => $label)
                <div class="col-lg-3 col-md-4 col-6">
                    <div class="card h-100"><div class="card-body">
                        <div class="text-muted fs-12">{{ translate($label) }}</div>
                        <strong class="fs-20">{{ $performance[$key] }}{{ in_array($key, ['visit_rate', 'conversion_rate']) ? '%' : '' }}</strong>
                    </div></div>
                </div>
            @endforeach
            <div class="col-lg-3 col-md-4 col-6">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted fs-12">{{ translate('Sales_Amount') }}</div>
                    <strong class="fs-20">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $performance['sales_amount'])) }}</strong>
                </div></div>
            </div>
        </div>
        <div class="alert alert-soft-info mt-3 mb-0">{{ translate('package_performance_is_aggregated_without_customer_identity') }}</div>
    </div>
@endsection
