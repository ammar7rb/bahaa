@extends('layouts.vendor.app')

@section('title', translate('Featured_Search_Ads'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="mb-1 text-capitalize">{{ translate('Featured_Search_Ads') }}</h1>
                <p class="mb-0 text-muted fs-12">{{ translate('promoted_products_appear_first_in_matching_search_results') }}.</p>
            </div>
            <a href="{{ route('vendor.packages.index') }}" class="btn btn-outline-primary">
                <i class="fi fi-sr-box-open"></i> {{ translate('Vendor_Packages') }}
            </a>
        </div>

        @if(!$summary['insurance_satisfied'])
            <div class="alert alert-soft-warning mb-3">
                {{ translate('active_seller_insurance_is_required_before_promoting_products') }}.
            </div>
        @elseif(!$summary['active_subscription'])
            <div class="alert alert-soft-warning mb-3">
                {{ translate('active_seller_package_is_required_before_promoting_products') }}.
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <div class="fs-12 text-muted">{{ translate('Active_Package') }}</div>
                        <strong>{{ $summary['active_subscription']?->package_name ?? translate('Not_Available') }}</strong>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-12 text-muted">{{ translate('Featured_Search_Remaining') }}</div>
                        <strong class="text-primary fs-18">{{ $summary['remaining_search_promotion_limit'] }}</strong>
                        <span class="text-muted fs-12">/ {{ $summary['search_promotion_limit'] }}</span>
                    </div>
                    <div class="col-md-4">
                        <div class="fs-12 text-muted">{{ translate('Promotion_Duration') }}</div>
                        <strong>{{ $summary['search_promotion_duration_days'] }} {{ translate('days') }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="mb-0">{{ translate('Published_Products') }}</h3>
                <form method="get" class="d-flex gap-2">
                    <input type="search" name="searchValue" value="{{ $searchValue }}" class="form-control"
                           placeholder="{{ translate('Search_by_product_name') }}">
                    <button class="btn btn--primary" type="submit" title="{{ translate('Search') }}">
                        <i class="tio-search"></i>
                    </button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('Product') }}</th>
                        <th>{{ translate('Promotion_Status') }}</th>
                        <th>{{ translate('Starts_At') }}</th>
                        <th>{{ translate('Expires_At') }}</th>
                        <th class="text-center">{{ translate('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($products as $product)
                        @php($promotion = $product->activeSearchPromotion)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img width="48" height="48" class="rounded border object-fit-cover"
                                         src="{{ getStorageImages(path: $product->thumbnail_full_url, type: 'product') }}"
                                         alt="{{ $product->name }}">
                                    <div>
                                        <strong>{{ $product->name }}</strong>
                                        <div class="fs-12 text-muted">#{{ $product->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($promotion)
                                    <span class="badge badge-soft-success">{{ translate('Featured_Search') }}</span>
                                @else
                                    <span class="badge badge-soft-secondary">{{ translate('Not_Promoted') }}</span>
                                @endif
                            </td>
                            <td>{{ $promotion?->starts_at ?? '-' }}</td>
                            <td>{{ $promotion?->expires_at ?? '-' }}</td>
                            <td class="text-center">
                                @if($promotion)
                                    <button type="button" class="btn btn-sm btn-success" disabled>
                                        <i class="tio-checkmark-circle"></i> {{ translate('Active') }}
                                    </button>
                                @else
                                    <form action="{{ route('vendor.promotions.search.activate') }}" method="post">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                                        <button type="submit" class="btn btn-sm btn--primary" {{ !$summary['can_promote'] ? 'disabled' : '' }}>
                                            <i class="tio-trending-up"></i> {{ translate('Promote_In_Search') }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted">{{ translate('no_published_products_found') }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($products->hasPages())
                <div class="card-footer border-0">{{ $products->links() }}</div>
            @endif
        </div>

        <div class="card">
            <div class="card-header border-0"><h3 class="mb-0">{{ translate('Promotion_History') }}</h3></div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle mb-0">
                    <thead class="thead-light"><tr><th>{{ translate('Product') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Duration') }}</th><th>{{ translate('Starts_At') }}</th><th>{{ translate('Expires_At') }}</th></tr></thead>
                    <tbody>
                    @forelse($history as $promotion)
                        <tr>
                            <td>{{ $promotion->product?->name ?? '#'.$promotion->product_id }}</td>
                            <td><span class="badge badge-soft-{{ $promotion->status === 'active' ? 'success' : 'secondary' }}">{{ translate($promotion->status) }}</span></td>
                            <td>{{ $promotion->duration_days }} {{ translate('days') }}</td>
                            <td>{{ $promotion->starts_at }}</td>
                            <td>{{ $promotion->expires_at }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">{{ translate('No_promotion_history') }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
