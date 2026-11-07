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
        @include('admin-views.payment-reviews._review-list')
    </div>
@endsection
