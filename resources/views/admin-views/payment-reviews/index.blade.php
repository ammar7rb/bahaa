@extends('layouts.admin.app')

@section('title', isset($customer)?'مدفوعات العميل '.$customer->administrative_reference:translate('unified_payment_reviews'))
@push('css_or_js')<link rel="stylesheet" href="{{ dynamicAsset(path:'public/assets/back-end/css/customer-accounts.css') }}?v=20261107.5">@endpush

@section('content')
    <div class="content container-fluid {{ isset($customer)?'customer-payment-reviews':'' }}">
        @include('admin-views.customer.accounts.profile-navigation')
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="h1 mb-1">{{ isset($customer)?'مدفوعات العميل '.$customer->administrative_reference:translate('unified_payment_reviews') }} <span class="badge badge-soft-dark ca-review-count">{{ $reviews->total() }}</span></h2>
                <p class="text-muted mb-0">{{ isset($customer)?'مدفوعات وطلبات هذا العميل فقط، بجميع الحالات المسجلة. سجلات التأمين السابقة للعرض والمطابقة فقط.':translate('unified_payment_reviews_note') }}</p>
            </div>
        </div>
        @include('admin-views.payment-reviews._review-list')
    </div>
@endsection
