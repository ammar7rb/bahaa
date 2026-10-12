@extends('layouts.vendor.app')

@section('title', translate('insurance_pending_orders'))

@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h1 class="page-header-title mb-1">{{ translate('insurance_pending_orders') }}</h1><p class="text-muted mb-0">{{ translate('insurance_pending_orders_help') }}</p></div>
        <a class="btn btn-outline-primary" href="{{ route('vendor.orders.list', 'all') }}">{{ translate('all_orders') }}</a>
    </div>
    <form class="card card-body mb-3" method="get"><div class="input-group"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="***123"><button class="btn btn--primary">{{ translate('search') }}</button></div></form>
    <div class="row g-3">
        @forelse($orders as $order)
            @php($insurance = $order->sellerOrderInsurance)
            <div class="col-md-6 col-xl-4"><div class="card h-100 border-warning"><div class="card-body">
                <div class="d-flex justify-content-between gap-2 mb-3"><strong>***{{ str_pad(substr((string)$order->id,-3),3,'0',STR_PAD_LEFT) }}</strong><span class="badge badge-soft-warning">{{ translate($insurance?->status ?? 'pending_payment') }}</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{ translate('Order_Amount') }}</span><strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $order->order_amount)) }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{ translate('Insurance_Amount') }}</span><strong>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $insurance?->amount ?? 0)) }}</strong></div>
                <div class="fs-12 text-muted mb-3">{{ translate('seller_order_insurance_expires_at') }}: {{ $insurance?->expires_at ?: '-' }}</div>
                <a class="btn btn--primary w-100" href="{{ route('vendor.orders.details', app(\App\Services\OrderCommerceContractService::class)->restrictedAccessToken($order)) }}">{{ translate('view_and_pay_insurance') }}</a>
            </div></div></div>
        @empty
            <div class="col-12"><div class="card card-body text-center p-5">{{ translate('no_insurance_pending_orders') }}</div></div>
        @endforelse
    </div>
    <div class="mt-3">{{ $orders->links() }}</div>
</div>
@endsection
