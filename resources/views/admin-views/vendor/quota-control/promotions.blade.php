@extends('layouts.admin.app')

@section('title', translate('Advertising_Requests'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="mb-1">{{ translate('Advertising_Requests') }}</h1>
                <p class="mb-0 text-muted fs-12">{{ translate('Review_each_request_and_choose_its_exact_placement_order_and_period_before_it_appears') }}.</p>
            </div>
            <a class="btn btn-outline-primary" href="{{ route('admin.vendors.quota-control.index') }}">{{ translate('Advertising_Package_Control') }}</a>
        </div>

        <div class="alert alert-soft-info">
            {{ translate('Pending_requests_do_not_consume_the_package_quota_the_quota_is_consumed_only_after_admin_approval') }}.
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="get" class="row g-2">
                    <div class="col-md-5"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ translate('Search_by_request_product_vendor_email_or_phone') }}"></div>
                    <div class="col-md-3"><select name="approval_status" class="form-control"><option value="">{{ translate('All_Approval_Statuses') }}</option><option value="pending_admin_review" @selected(request('approval_status') === 'pending_admin_review')>{{ translate('Pending_Admin_Review') }}</option><option value="approved" @selected(request('approval_status') === 'approved')>{{ translate('Approved') }}</option><option value="rejected" @selected(request('approval_status') === 'rejected')>{{ translate('Rejected') }}</option></select></div>
                    <div class="col-md-2"><select name="promotion_type" class="form-control"><option value="">{{ translate('All_Ad_Types') }}</option><option value="search" @selected(request('promotion_type') === 'search')>{{ translate('Search_Ad') }}</option><option value="homepage" @selected(request('promotion_type') === 'homepage')>{{ translate('Homepage_Ad') }}</option></select></div>
                    <div class="col-md-2 d-flex gap-2"><button class="btn btn--primary flex-grow-1">{{ translate('Filter') }}</button><a href="{{ route('admin.vendors.quota-control.promotions.index') }}" class="btn btn-secondary">{{ translate('Reset') }}</a></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-align-middle mb-0">
                    <thead class="thead-light"><tr><th>#</th><th>{{ translate('Vendor') }}</th><th>{{ translate('Product') }}</th><th>{{ translate('Ad_Type') }}</th><th>{{ translate('Approval') }}</th><th>{{ translate('Placement') }}</th><th>{{ translate('Period') }}</th><th>{{ translate('Review_Decision') }}</th></tr></thead>
                    <tbody>
                    @forelse($promotions as $promotion)
                        <tr>
                            <td>#{{ $promotion->id }}</td>
                            <td><a href="{{ route('admin.vendors.quota-control.show', $promotion->seller_id) }}">M{{ $promotion->seller_id }} — {{ $promotion->seller?->shop?->name ?? $promotion->seller?->email }}</a></td>
                            <td>{{ $promotion->product?->name ?? '#'.$promotion->product_id }}</td>
                            <td>{{ translate($promotion->promotion_type === 'search' ? 'Search_Ad' : 'Homepage_Ad') }}</td>
                            <td><span class="badge badge-soft-{{ $promotion->approval_status === 'approved' ? 'success' : ($promotion->approval_status === 'rejected' ? 'danger' : 'warning') }}">{{ translate($promotion->approval_status) }}</span>@if($promotion->review_note)<div class="fs-11 text-muted mt-1 text-wrap">{{ $promotion->review_note }}</div>@endif</td>
                            <td><div>{{ translate($promotion->placement_scope ?: 'Not_Assigned') }}</div><div class="fs-11 text-muted">{{ $promotion->placement_key ?: translate('All') }} · #{{ $promotion->approved_position ?: $promotion->requested_position ?: '-' }}</div>@if(data_get($promotion->metadata, 'badge_label'))<div class="badge badge-soft-info mt-1">{{ translate(data_get($promotion->metadata, 'badge_label')) }}</div>@endif</td>
                            <td class="fs-12">{{ $promotion->starts_at ?: '-' }}<br>{{ $promotion->expires_at ?: '-' }}</td>
                            <td style="min-width:330px">
                                @if($promotion->approval_status === 'pending_admin_review')
                                    <form action="{{ route('admin.vendors.quota-control.promotions.review', $promotion->id) }}" method="post" class="row g-1">
                                        @csrf
                                        <div class="col-7"><select name="placement_scope" class="form-control form-control-sm" required>
                                            @if($promotion->promotion_type === 'search')
                                                <option value="search_results">{{ translate('All_Search_Results') }}</option><option value="category_search">{{ translate('Category_Search') }}</option><option value="keyword_search">{{ translate('Keyword_Search') }}</option>
                                            @else
                                                <option value="homepage_featured">{{ translate('Homepage_Featured') }}</option><option value="homepage_bestseller">{{ translate('Homepage_Best_Sellers') }}</option><option value="homepage_latest">{{ translate('Homepage_Latest_Products') }}</option><option value="homepage_popular">{{ translate('Homepage_Most_Popular') }}</option>
                                            @endif
                                        </select></div>
                                        <div class="col-5"><input name="placement_key" class="form-control form-control-sm" placeholder="{{ translate('Category_or_keyword_optional') }}"></div>
                                        <div class="col-4"><input type="number" min="1" name="approved_position" value="{{ $promotion->requested_position ?: 1 }}" class="form-control form-control-sm" placeholder="{{ translate('Order') }}" required></div>
                                        <div class="col-4"><input type="datetime-local" name="starts_at" class="form-control form-control-sm"></div>
                                        <div class="col-4"><input type="datetime-local" name="expires_at" class="form-control form-control-sm"></div>
                                        <div class="col-12"><input name="badge_label" value="Featured_Ad" maxlength="40" class="form-control form-control-sm" placeholder="{{ translate('Advertising_badge_label') }}"></div>
                                        <div class="col-12"><input name="review_note" class="form-control form-control-sm" maxlength="1000" placeholder="{{ translate('Decision_reason_is_required') }}" required></div>
                                        <div class="col-12 d-flex gap-1"><button name="decision" value="approve" class="btn btn-sm btn-success flex-grow-1">{{ translate('Approve_And_Publish') }}</button><button name="decision" value="reject" formnovalidate class="btn btn-sm btn-outline-danger flex-grow-1">{{ translate('Reject') }}</button></div>
                                    </form>
                                @else
                                    <div class="fs-12 mb-2">{{ $promotion->reviewedByAdmin?->name ?? '-' }}<br>{{ $promotion->reviewed_at ?? '-' }}</div>
                                    @if($promotion->approval_status === 'approved')<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.vendors.quota-control.promotions.performance', $promotion->id) }}">{{ translate('Performance_Report') }}</a>@endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-5 text-muted">{{ translate('No_advertising_requests_found') }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($promotions->hasPages())<div class="card-footer border-0">{{ $promotions->links() }}</div>@endif
        </div>
    </div>
@endsection
