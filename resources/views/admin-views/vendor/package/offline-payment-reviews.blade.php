@extends('layouts.admin.app')

@section('title', translate('Vendor_Package_Offline_Reviews'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <h2 class="h1 mb-0 text-capitalize">{{ translate('Vendor_Package_Offline_Reviews') }} <span class="badge badge-soft-dark">{{ $subscriptions->total() }}</span></h2>
            <a href="{{ route('admin.vendors.packages.index') }}" class="btn btn-secondary">{{ translate('Vendor_Packages') }}</a>
        </div>

        <div class="alert alert-info mb-3">{{ translate('every_offline_package_payment_requires_admin_approval_before_the_package_and_quotas_are_activated') }}.</div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
                    <h3 class="mb-0">{{ translate('Review_List') }}</h3>
                    <form action="{{ url()->current() }}" method="get">
                        <div class="d-flex flex-wrap gap-2">
                            <div class="select-wrapper">
                                <select name="status" class="form-select">
                                    <option value="">{{ translate('Pending_Approved_And_Rejected') }}</option>
                                    <option value="pending_review" {{ request('status') === 'pending_review' ? 'selected' : '' }}>{{ translate('Pending_Review') }}</option>
                                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ translate('Approved') }}</option>
                                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>{{ translate('Rejected') }}</option>
                                </select>
                            </div>
                            <div class="input-group max-w-280">
                                <input type="search" name="searchValue" class="form-control" placeholder="{{ translate('search_vendor_or_package') }}" value="{{ request('searchValue') }}">
                                <div class="input-group-append search-submit"><button type="submit"><i class="fi fi-rr-search"></i></button></div>
                            </div>
                            <a href="{{ route('admin.vendors.package-payments.index') }}" class="btn btn-secondary">{{ translate('reset') }}</a>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light thead-50"><tr><th>{{ translate('SL') }}</th><th>{{ translate('Vendor') }}</th><th>{{ translate('Package') }}</th><th>{{ translate('Snapshot') }}</th><th>{{ translate('Transfer_Details') }}</th><th>{{ translate('Proof') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Action') }}</th></tr></thead>
                        <tbody>
                        @forelse($subscriptions as $key => $subscription)
                            @php($offline = $subscription->metadata['offline_payment'] ?? [])
                            @php($proof = $offline['payment_proof'] ?? null)
                            @php($proofStorage = $proof ? storageLink('seller-package/payment-proof', $proof['image_name'] ?? '', $proof['storage'] ?? 'public') : null)
                            @php($proofUrl = is_array($proofStorage) ? ($proofStorage['path'] ?? null) : $proofStorage)
                            <tr>
                                <td>{{ $subscriptions->firstItem() + $key }}</td>
                                <td><div class="fw-semibold">{{ trim(($subscription->seller?->f_name ?? '') . ' ' . ($subscription->seller?->l_name ?? '')) ?: translate('vendor_not_found') }}</div><div class="fs-12 text-muted">{{ $subscription->seller?->email }}</div><div class="fs-12 text-muted">{{ $subscription->seller?->phone }}</div></td>
                                <td><div class="fw-semibold">{{ $subscription->package_name }}</div><div class="text-primary">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $subscription->paid_package_price)) }}</div><div class="fs-12 text-muted">#{{ $subscription->id }}</div></td>
                                <td class="fs-12"><div>{{ translate('Products') }}: <strong>{{ $subscription->product_limit }}</strong> / {{ $subscription->product_duration_days }} {{ translate('days') }}</div><div>{{ translate('Search') }}: <strong>{{ $subscription->search_promotion_limit }}</strong> / {{ $subscription->search_promotion_duration_days }} {{ translate('days') }}</div><div>{{ translate('Homepage') }}: <strong>{{ $subscription->homepage_promotion_limit }}</strong> / {{ $subscription->homepage_promotion_duration_days }} {{ translate('days') }}</div></td>
                                <td class="fs-12"><div><strong>{{ translate('Method') }}:</strong> {{ $offline['method_name'] ?? translate('N/A') }}</div>@foreach(($offline['method_information'] ?? []) as $field => $value)<div class="text-break"><strong>{{ ucwords(str_replace('_', ' ', $field)) }}:</strong> {{ $value }}</div>@endforeach @if(!empty($offline['payment_note']))<div class="text-muted text-break">{{ $offline['payment_note'] }}</div>@endif</td>
                                <td>@if($proofUrl)<a href="{{ $proofUrl }}" target="_blank" rel="noopener"><img src="{{ $proofUrl }}" width="72" height="72" class="rounded border object-cover" alt="{{ translate('Payment_Proof') }}"></a>@else<span class="text-muted">{{ translate('No_proof') }}</span>@endif</td>
                                <td><span class="badge badge-soft-{{ $subscription->status === 'active' ? 'success' : ($subscription->status === 'rejected' ? 'danger' : 'warning') }}">{{ translate($subscription->status) }}</span></td>
                                <td>
                                    @if($subscription->status === 'pending_review')
                                        <div class="d-flex gap-2">
                                            <form action="{{ route('admin.vendors.package-payments.approve', ['id' => $subscription->id]) }}" method="post">@csrf<button class="btn btn-outline-success btn-sm" type="submit">{{ translate('Approve') }}</button></form>
                                            <button class="btn btn-outline-danger btn-sm" type="button" data-toggle="modal" data-target="#reject-package-{{ $subscription->id }}">{{ translate('Reject') }}</button>
                                        </div>
                                        <div class="modal fade" id="reject-package-{{ $subscription->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered"><form action="{{ route('admin.vendors.package-payments.reject', ['id' => $subscription->id]) }}" method="post" class="modal-content">@csrf<div class="modal-header"><h5 class="modal-title">{{ translate('Reject_Package_Payment') }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><label class="form-label">{{ translate('Rejection_Reason') }}</label><textarea class="form-control" name="review_note" rows="3" required></textarea></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('cancel') }}</button><button type="submit" class="btn btn-danger">{{ translate('Confirm_Reject') }}</button></div></form></div>
                                        </div>
                                    @else
                                        <span class="text-muted">{{ data_get($subscription->metadata, 'offline_payment_review.reviewed_at') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">{{ translate('No_data_found') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end p-4">{!! $subscriptions->links() !!}</div>
            </div>
        </div>
    </div>
@endsection
