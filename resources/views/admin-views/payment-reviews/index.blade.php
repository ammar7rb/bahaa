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
                <div class="col-md-2"><select name="kind" class="form-select"><option value="">{{ translate('all_payment_types') }}</option><option value="subscription" @selected(request('kind') === 'subscription')>الاشتراك الشهري</option><option value="order" @selected(request('kind') === 'order')>{{ translate('order') }}</option><option value="package" @selected(request('kind') === 'package')>{{ translate('package') }}</option><option value="insurance" @selected(request('kind') === 'insurance')>{{ translate('insurance') }}</option><option value="balance_deposit" @selected(request('kind') === 'balance_deposit')>إيداع رصيد التاجر</option></select></div>
                <div class="col-md-2"><select name="status" class="form-select"><option value="">{{ translate('all_Status') }}</option><option value="reconciliation" @selected(request('status') === 'reconciliation')>تحتاج مطابقة مالية</option><option value="pending" @selected(request('status') === 'pending')>{{ translate('pending') }}</option><option value="approved" @selected(request('status') === 'approved')>{{ translate('approved') }}</option><option value="rejected" @selected(request('status') === 'rejected')>{{ translate('rejected') }}</option></select></div>
                <div class="col-md-4"><input class="form-control" name="searchValue" value="{{ request('searchValue') }}" placeholder="{{ translate('search_seller_customer_or_reference') }}"></div>
                <div class="col-md-2"><button class="btn btn--primary w-100">{{ translate('filter') }}</button></div>
            </form>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>#</th><th>{{ translate('user_type') }}</th><th>{{ translate('user') }}</th><th>{{ translate('payment_type') }}</th><th>{{ translate('amount') }}</th><th>{{ translate('offline_payment_info') }}</th><th>{{ translate('status') }}</th><th>{{ translate('action') }}</th></tr></thead><tbody>
                @forelse ($reviews as $review)
                    <tr>
                        <td>{{ $loop->iteration + (($reviews->currentPage() - 1) * $reviews->perPage()) }}</td>
                        <td>{{ translate($review['audience']) }}
                            @if($review['customer_state']['is_urgent_order'] ?? false)
                                <div class="badge badge-soft-warning mt-1">حساب استعجالي</div>
                            @endif
                        </td>
                        <td><div class="fw-semibold">{{ trim(($review['actor']?->f_name ?? '') . ' ' . ($review['actor']?->l_name ?? '')) ?: '—' }}</div><small>{{ $review['actor']?->email }}</small><br><small>{{ $review['actor']?->phone }}</small></td>
                        <td>{{ translate($review['kind']) }}<br><small>{{ $review['label'] }}
                            @if(!empty($review['customer_state']['customer']))<div class="small mt-1" dir="rtl">{{ $review['customer_state']['customer']['onboarding_journey'] === 'checkout_first' ? 'عميل جديد — تسجيل من الدفع' : 'تسجيل عادي أو سابق' }} · {{ $review['customer_state']['customer']['account_activated'] ? 'مفعّل' : 'غير مفعّل' }} · {{ $review['customer_state']['customer']['subscription']['active'] ? 'اشتراك نشط' : 'اشتراك غير نشط' }}<br>{{ $review['customer_state']['message'] }}</div>@endif</small></td>
                        <td>{{ in_array($review['type'], ['customer_subscription','customer_legacy_receipt'], true) ? number_format($review['amount'],2).' '.$review['currency'] : setCurrencySymbol(amount: usdToDefaultCurrency(amount: $review['amount'])) }}</td>
                        <td>
                            @if($review['type'] === 'customer_subscription')
                                <a href="{{ route('admin.customer.monthly-subscriptions.index', ['status'=>$review['status'] === 'pending' ? 'pending_review' : $review['status']]) }}">عرض الحساب والاشتراك وإثبات الدفع</a>
                            @endif
                            @if (!empty($review['offline']) && is_array($review['offline']))
                                <strong>{{ translatePaymentText($review['offline']['method_name'] ?? translate('offline_payment')) }}</strong>
                                @if($review['type'] === 'customer_order' && !empty($review['offline']['private_retry_proof']))
                                    <div><a href="{{ route('admin.payment-reviews.customer-order.retry-proof', $review['id']) }}" target="_blank" rel="noopener">عرض إثبات الدفع المعاد تقديمه</a></div>
                                @endif
                                @foreach ($review['offline'] as $infoKey => $infoValue)
                                    @if (!in_array($infoKey, ['method_id', 'method_name', 'submitted_at', 'private_retry_proof', 'retry_key', 'retry_history'], true) && !empty($infoValue))
                                        <div class="fs-12 text-break">
                                            {{ translatePaymentText($infoKey) }}:
                                            @php
                                                $proofUrl = getOfflinePaymentProofUrl($infoValue, ['offline-payment/order-proof', 'offline-payment/activation-invoice-proof', 'offline-payment/customer-package-proof', 'seller-package/payment-proof', 'seller-insurance/payment-proof', 'seller-balance/payment-proof']);
                                            @endphp
                                            @if ($proofUrl)<a href="{{ $proofUrl }}" target="_blank" rel="noopener">{{ translate('view') }}</a>@else{{ formatOrderPaymentInfoValue($infoValue) }}@endif
                                        </div>
                                    @endif
                                @endforeach
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td><span class="badge {{ $review['status'] === 'pending' ? 'badge-soft-warning' : ($review['status'] === 'approved' ? 'badge-soft-success' : 'badge-soft-danger') }}">{{ $review['status'] === 'reconciliation' ? 'تحتاج مطابقة مالية' : translate($review['status']) }}</span><br><small>{{ $review['reference'] }}</small></td>
                        <td>
                            @if($review['customer_state']['can_request_package'] ?? false)
                                @if($review['customer_state']['package_request_sent'] ?? false)
                                    <span class="text-success">تم إرسال طلب اختيار الباقة</span>
                                @else
                                    <form method="post" action="{{ route('admin.payment-reviews.request-package', $review['id']) }}" class="mb-2">
                                        @csrf
                                        <button class="btn btn-sm btn--primary">إبلاغ العميل باعتماد الدفع واختيار باقة</button>
                                    </form>
                                @endif
                            @endif
                            @if ($review['status'] === 'pending')
                                <div class="d-flex gap-1">
                                    <form method="post" action="{{ route('admin.payment-reviews.approve', [$review['type'], $review['id']]) }}">@csrf<button class="btn btn-sm btn-outline-success">{{ translate('approve') }}</button></form>
                                    <form method="post" action="{{ route('admin.payment-reviews.reject', [$review['type'], $review['id']]) }}">@csrf<input class="form-control form-control-sm mb-1" name="review_note" required placeholder="{{ translate('rejection_reason') }}"><button class="btn btn-sm btn-outline-danger">{{ translate('reject') }}</button></form>
                                </div>
                            @elseif (!($review['customer_state']['can_request_package'] ?? false))
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
