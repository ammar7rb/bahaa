@extends('layouts.admin.app')

@section('title', translate('Vendor_Package_Control'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="mb-1 text-capitalize">{{ translate('Vendor_Package_Control') }}</h1>
                <p class="mb-0 text-muted fs-12">{{ translate('review_vendor_insurance_packages_quotas_and_promotions') }}.</p>
            </div>
            <a href="{{ route('admin.vendors.packages.index') }}" class="btn btn-outline-primary">
                <i class="tio-premium-outlined"></i> {{ translate('Vendor_Packages') }}
            </a>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <form method="get" class="row g-2 align-items-center w-100">
                    <div class="col-md-6">
                        <input type="search" name="searchValue" value="{{ request('searchValue') }}" class="form-control" placeholder="{{ translate('Search_by_vendor_name_email_phone_or_shop') }}">
                    </div>
                    <div class="col-md-3">
                        <select name="package_status" class="form-control">
                            <option value="">{{ translate('All_Package_Statuses') }}</option>
                            <option value="active" @selected(request('package_status') === 'active')>{{ translate('Active_Package') }}</option>
                            <option value="missing" @selected(request('package_status') === 'missing')>{{ translate('Without_Active_Package') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn--primary flex-grow-1" type="submit"><i class="tio-search"></i> {{ translate('Filter') }}</button>
                        <a href="{{ route('admin.vendors.quota-control.index') }}" class="btn btn-outline-secondary" title="{{ translate('Reset') }}"><i class="tio-refresh"></i></a>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle mb-0">
                    <thead class="thead-light">
                    <tr><th>{{ translate('Vendor') }}</th><th>{{ translate('Insurance') }}</th><th>{{ translate('Active_Package') }}</th><th>{{ translate('Sales_Commission') }}</th><th>{{ translate('Remaining_Quotas') }}</th><th>{{ translate('Active_Usage') }}</th><th class="text-center">{{ translate('Action') }}</th></tr>
                    </thead>
                    <tbody>
                    @forelse($sellers as $seller)
                        @php($subscription = $seller->activePackageSubscription)
                        <tr>
                            <td>
                                <strong>{{ trim($seller->f_name.' '.$seller->l_name) }}</strong>
                                <div class="fs-12 text-muted">{{ $seller->shop?->name ?? $seller->email }}</div>
                                <div class="fs-12 text-muted">{{ $seller->phone }}</div>
                            </td>
                            <td>
                                <span class="badge badge-soft-{{ $seller->activeInsurance ? 'success' : 'warning' }}">
                                    {{ $seller->activeInsurance ? translate('Active') : translate('Not_Active') }}
                                </span>
                            </td>
                            <td>
                                @if($subscription)
                                    <strong>{{ $subscription->package_name }}</strong>
                                    <div class="fs-12 text-muted">{{ $subscription->expires_at ?? translate('No_Expiry') }}</div>
                                @else
                                    <span class="badge badge-soft-secondary">{{ translate('No_Active_Package') }}</span>
                                @endif
                            </td>
                            <td>
                                @php($effectiveCommission = $seller->sales_commission_percentage !== null ? (float) $seller->sales_commission_percentage : (float) $defaultCommission)
                                <strong>{{ number_format($effectiveCommission, 2) }}%</strong>
                                <div class="fs-12 text-muted">
                                    {{ translate($seller->sales_commission_percentage !== null ? 'Vendor_Override' : 'System_Default') }}
                                </div>
                            </td>
                            <td class="fs-12">
                                @if($subscription)
                                    <div>{{ translate('Products') }}: <strong>{{ max(0, $subscription->product_limit + $subscription->product_adjustment_limit - $subscription->used_product_limit) }}</strong></div>
                                    <div>{{ translate('Search') }}: <strong>{{ max(0, $subscription->search_promotion_limit + $subscription->search_promotion_adjustment_limit - $subscription->used_search_promotion_limit) }}</strong></div>
                                    <div>{{ translate('Homepage') }}: <strong>{{ max(0, $subscription->homepage_promotion_limit + $subscription->homepage_promotion_adjustment_limit - $subscription->used_homepage_promotion_limit) }}</strong></div>
                                @else - @endif
                            </td>
                            <td class="fs-12">
                                <div>{{ translate('Listings') }}: <strong>{{ $seller->active_listings_count }}</strong></div>
                                <div>{{ translate('Search_Ads') }}: <strong>{{ $seller->active_search_promotions_count }}</strong></div>
                                <div>{{ translate('Homepage_Offers') }}: <strong>{{ $seller->active_homepage_promotions_count }}</strong></div>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.vendors.quota-control.show', $seller->id) }}" class="btn btn-outline-primary btn-sm" title="{{ translate('View_Details') }}"><i class="tio-visible"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">{{ translate('No_vendors_found') }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($sellers->hasPages())<div class="card-footer border-0">{{ $sellers->links() }}</div>@endif
        </div>
    </div>
@endsection
