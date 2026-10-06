@extends('layouts.vendor.app')
@section('title', translate('dashboard'))
@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid vendor-dashboard">
        <div class="vendor-dashboard-welcome">
            <div><h1>{{ translate('welcome') }}، {{ auth('seller')->user()->f_name }}</h1><p>{{ translate('vendor_workspace_intro') }}</p></div>
            <div class="vendor-dashboard-actions"><a class="btn btn-outline-primary" href="{{ route('vendor.orders.list', 'all') }}">{{ translate('review_orders_and_due_dates') }}</a><a class="btn btn--primary" href="{{ route('vendor.products.list', ['type'=>'all']) }}">{{ translate('products') }}</a></div>
        </div>
        @if(($sellerSetup['registration']['activation_status'] ?? '') !== 'active')
            @include('vendor-views.dashboard.partials.seller-setup-status', ['setup' => $sellerSetup])
        @endif
        {{--
            The controller supplies this summary.  Keeping this fallback protects the dashboard
            from a stale PHP opcode cache during deployments, without changing its data source.
        --}}
        @php($sellerOverview = $sellerOverview ?? app(\App\Services\SellerDashboardOverviewService::class)->build(auth('seller')->user()))
        @include('vendor-views.dashboard.partials.unified-overview', ['sellerOverview' => $sellerOverview])
        <div class="card mb-3 remove-card-shadow" id="vendor-wallet">
            <div class="card-body">
                <div class="row justify-content-between align-items-center g-2 mb-3">
                    <div class="col-sm-6 col-md-8">
                        <h4 class="d-flex align-items-center text-capitalize gap-10 mb-0">
                            <img src="{{dynamicAsset(path: 'public/assets/back-end/img/business_analytics.png')}}" alt="">
                            {{ translate('Business_Analytics') }}
                        </h4>
                    </div>
                    <div class="col-sm-6 col-md-4 d-flex justify-content-sm-end">
                        <div class="min-w-200">
                            <select class="custom-select w-auto" id="statistics_type" name="statistics_type">
                                <option value="overall"
                                    {{ session()->has('vendor_statistics_type') && session('vendor_statistics_type') == 'overall' ? 'selected' : '' }}>
                                    {{ translate('Overall_Statistics') }}
                                </option>
                                <option value="today"
                                    {{ session()->has('vendor_statistics_type') && session('vendor_statistics_type') == 'today' ? 'selected' : '' }}>
                                    {{ translate('Today_Statistics') }}
                                </option>
                                <option value="this_week"
                                    {{ session()->has('vendor_statistics_type') && session('vendor_statistics_type') == 'this_week' ? 'selected' : '' }}>
                                    {{ translate('This_Week_Statistics') }}
                                </option>
                                <option value="this_month"
                                    {{ session()->has('vendor_statistics_type') && session('vendor_statistics_type') == 'this_month' ? 'selected' : '' }}>
                                    {{ translate('This_Month_Statistics') }}
                                </option>
                                <option value="this_year"
                                    {{ session()->has('vendor_statistics_type') && session('vendor_statistics_type') == 'this_year' ? 'selected' : '' }}>
                                    {{ translate('This_Year_Statistics') }}
                                </option>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="row g-2" id="order_stats">
                    @include('vendor-views.partials._dashboard-order-status',['orderStatus'=>$dashboardData['orderStatus']])
                </div>
            </div>
        </div>
        <div class="card mb-3 remove-card-shadow">
            <div class="card-body">
                <div class="row justify-content-between align-items-center g-2 mb-3">
                    <div class="col-sm-6">
                        <h4 class="d-flex align-items-center text-capitalize gap-10 mb-0">
                            <img width="20" class="mb-1" src="{{dynamicAsset(path: 'public/assets/back-end/img/admin-wallet.png')}}" alt="">
                            {{translate('vendor_Wallet')}}
                        </h4>
                    </div>
                </div>
                <div class="row g-2" id="wallet_stats">
                    @include('vendor-views.partials._dashboard-wallet-status',['dashboardData' => $dashboardData])
                </div>
            </div>
        </div>
        @include('vendor-views.dashboard.partials.order-insurance-wallet', ['sellerOverview' => $sellerOverview])

        <div class="modal fade" id="balance-modal" tabindex="-1" aria-labelledby="balanceModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="balanceModalLabel">{{ translate('withdraw_Request') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <form action="{{ route('vendor.dashboard.withdraw-request') }}" method="post" class="d-flex flex-column flex-grow-1 max-h-100vh-150px">
                        @csrf

                        @include("vendor-views.withdraw._withdraw-request-form")

                        <div class="modal-footer p-3 border-top bg-white d-flex flex-nowrap gap-1">
                            <button type="button" class="btn btn-secondary w-100" data-dismiss="modal">
                                {{ translate('close') }}
                            </button>
                            <button type="submit" class="btn btn--primary w-100">
                                {{ translate('Send_Request') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-12" id="earn-statistics-div">
                @include('vendor-views.dashboard.partials.earning-statistics')
            </div>
            <div class="col-lg-6">
                <div class="card h-100 remove-card-shadow">
                    @include('vendor-views.partials._top-rated-products',['topRatedProducts'=>$dashboardData['topRatedProducts']])
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100 remove-card-shadow">
                    @include('vendor-views.partials._top-selling-products',['topSell'=>$dashboardData['topSell']])
                </div>
            </div>
        </div>
    </div>
    <span id="withdraw-method-url" data-url="{{ route('vendor.dashboard.method-list') }}"></span>
    <span id="order-status-url" data-url="{{ route('vendor.dashboard.order-status', ['type' => ':type']) }}"></span>
    <span id="seller-text" data-text="{{ translate('vendor')}}"></span>
    <span id="in-house-text" data-text="{{ translate('In-house')}}"></span>
    <span id="customer-text" data-text="{{ translate('customer')}}"></span>
    <span id="store-text" data-text="{{ translate('store')}}"></span>
    <span id="product-text" data-text="{{ translate('product')}}"></span>
    <span id="order-text" data-text="{{ translate('order')}}"></span>
    <span id="brand-text" data-text="{{ translate('brand')}}"></span>
    <span id="business-text" data-text="{{ translate('business')}}"></span>
    <span id="customers-text" data-text="{{ $dashboardData['customers'] }}"></span>
    <span id="products-text" data-text="{{ $dashboardData['products'] }}"></span>
    <span id="orders-text" data-text="{{ $dashboardData['orders'] }}"></span>
    <span id="brands-text" data-text="{{ $dashboardData['brands'] }}"></span>
@endsection

@push('script_2')
    <script src="{{dynamicAsset(path: 'public/assets/back-end/js/apexcharts.js')}}"></script>
    <script src="{{dynamicAsset(path: 'public/assets/back-end/js/vendor/dashboard.js')}}"></script>
@endpush
