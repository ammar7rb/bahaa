@extends('layouts.admin.app')

@section('title', translate('unified_payment_reviews'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="h1 mb-1">{{ translate('unified_payment_reviews') }} <span class="badge badge-soft-dark">{{ $reviews->total() }}</span></h2>
                <p class="text-muted mb-0">{{ translate('unified_payment_reviews_note') }}</p>
            </div>
        </div>
        <div class="card"><div class="card-body">
            <form method="get" class="row g-2 mb-4">
                <div class="col-md-2"><select name="audience" class="form-select"><option value="">{{ translate('all_users') }}</option><option value="vendor" @selected(request('audience') === 'vendor')>{{ translate('vendor') }}</option><option value="customer" @selected(request('audience') === 'customer')>{{ translate('customer') }}</option></select></div>
                <div class="col-md-2"><select name="kind" class="form-select"><option value="">{{ translate('all_payment_types') }}</option><option value="order" @selected(request('kind') === 'order')>{{ translate('order') }}</option><option value="package" @selected(request('kind') === 'package')>{{ translate('package') }}</option><option value="insurance" @selected(request('kind') === 'insurance')>{{ translate('insurance') }}</option></select></div>
                <div class="col-md-2"><select name="status" class="form-select"><option value="">{{ translate('all_Status') }}</option><option value="pending" @selected(request('status') === 'pending')>{{ translate('pending') }}</option><option value="approved" @selected(request('status') === 'approved')>{{ translate('approved') }}</option><option value="rejected" @selected(request('status') === 'rejected')>{{ translate('rejected') }}</option></select></div>
                <div class="col-md-4"><input class="form-control" name="searchValue" value="{{ request('searchValue') }}" placeholder="{{ translate('search_seller_customer_or_reference') }}"></div>
                <div class="col-md-2"><button class="btn btn--primary w-100">{{ translate('filter') }}</button></div>
            </form>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>#</th><th>{{ translate('user_type') }}</th><th>{{ translate('user') }}</th><th>{{ translate('payment_type') }}</th><th>{{ translate('amount') }}</th><th>{{ translate('offline_payment_info') }}</th><th>{{ translate('status') }}</th><th>{{ translate('action') }}</th></tr></thead><tbody>
                @forelse ($reviews as $review)
                    <tr>
                        <td>{{ $loop->iteration + (($reviews->currentPage() - 1) * $reviews->perPage()) }}</td>
                        <td>{{ translate($review['audience']) }}</td>
                        <td><div class="fw-semibold">{{ trim(($review['actor']?->f_name ?? '') . ' ' . ($review['actor']?->l_name ?? '')) ?: '—' }}</div><small>{{ $review['actor']?->email }}</small><br><small>{{ $review['actor']?->phone }}</small></td>
                        <td>{{ translate($review['kind']) }}<br><small>{{ $review['label'] }}</small></td>
                        <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $review['amount'])) }}</td>
                        <td>
                            @if (!empty($review['offline']) && is_array($review['offline']))
                                <strong>{{ translatePaymentText($review['offline']['method_name'] ?? translate('offline_payment')) }}</strong>
                                @foreach ($review['offline'] as $infoKey => $infoValue)
                                    @if (!in_array($infoKey, ['method_id', 'method_name', 'submitted_at'], true) && !empty($infoValue))
                                        <div class="fs-12 text-break">
                                            {{ translatePaymentText($infoKey) }}:
                                            @php
                                                $proofUrl = getOfflinePaymentProofUrl($infoValue, ['offline-payment/order-proof', 'offline-payment/activation-invoice-proof', 'offline-payment/customer-package-proof', 'seller-package/payment-proof', 'seller-insurance/payment-proof']);
                                            @endphp
                                            @if ($proofUrl)<a href="{{ $proofUrl }}" target="_blank" rel="noopener">{{ translate('view') }}</a>@else{{ formatOrderPaymentInfoValue($infoValue) }}@endif
                                        </div>
                                    @endif
                                @endforeach
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td><span class="badge {{ $review['status'] === 'pending' ? 'badge-soft-warning' : ($review['status'] === 'approved' ? 'badge-soft-success' : 'badge-soft-danger') }}">{{ translate($review['status']) }}</span><br><small>{{ $review['reference'] }}</small></td>
                        <td>
                            @if ($review['status'] === 'pending')
                                <div class="d-flex gap-1">
                                    <form method="post" action="{{ route('admin.payment-reviews.approve', [$review['type'], $review['id']]) }}">@csrf<button class="btn btn-sm btn-outline-success">{{ translate('approve') }}</button></form>
                                    <form method="post" action="{{ route('admin.payment-reviews.reject', [$review['type'], $review['id']]) }}">@csrf<input class="form-control form-control-sm mb-1" name="review_note" required placeholder="{{ translate('rejection_reason') }}"><button class="btn btn-sm btn-outline-danger">{{ translate('reject') }}</button></form>
                                </div>
                            @else
                                &mdash;
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">{{ translate('no_offline_payment_review_found') }}</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="d-flex justify-content-end mt-3">{{ $reviews->links() }}</div>
        </div></div>
    </div>
@endsection
