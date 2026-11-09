@php($gateAddress = (array) ($order->shipping_address_data ?? []))
<div class="card mb-3 border-0 shadow-sm order-review-panel" id="admin-order-review" dir="rtl">
    <div class="card-header d-flex justify-content-between gap-3 flex-wrap py-3">
        <div><h3 class="mb-1">{{ translate('order_admin_review_title') }}</h3><p class="text-muted mb-0">{{ translate('complete_shipping_before_vendor_release') }}</p></div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="bg-section rounded p-3 h-100"><h5><i class="fi fi-sr-user text-primary me-2"></i>{{ translate('customer') }}</h5><small class="text-muted d-block mb-2">{{ translate('customer_contact_for_this_order') }}</small>
                @if($order->customer)<a href="{{ route('admin.customer.view', ['user_id'=>$order->customer->id]) }}">{{ trim($order->customer->f_name.' '.$order->customer->l_name) }}</a><div class="small mt-2"><bdi>{{ $order->customer->email }}</bdi><br><bdi>{{ $order->customer->phone }}</bdi></div>@else {{ translate('guest_customer') }} @endif
            </div></div>
            <div class="col-md-4"><div class="bg-section rounded p-3 h-100"><h5><i class="fi fi-sr-shop text-primary me-2"></i>{{ translate('seller') }}</h5><small class="text-muted d-block mb-2">{{ translate('seller_receives_order_only_after_approval') }}</small>
                @if($order->seller)<a href="{{ route('admin.vendors.view', ['id'=>$order->seller->id]) }}">{{ $order->seller->shop?->name ?: $order->seller->f_name }}</a><div class="small mt-2"><bdi>{{ $order->seller->email }}</bdi><br><bdi>{{ $order->seller->phone }}</bdi></div>@endif
            </div></div>
            <div class="col-md-4"><div class="bg-section rounded p-3 h-100"><h5><i class="fi fi-sr-marker text-primary me-2"></i>{{ translate('shipping_address') }}</h5><small class="text-muted d-block mb-2">{{ translate('customer_delivery_destination') }}</small>
                <p class="mb-2">{{ implode('، ', array_filter([$gateAddress['state'] ?? '', $gateAddress['city'] ?? '', $gateAddress['address'] ?? ''])) }}</p>
                @if(!empty($gateAddress['latitude']) && !empty($gateAddress['longitude']))<a target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ urlencode($gateAddress['latitude'].','.$gateAddress['longitude']) }}">{{ translate('view_on_map') }}</a>@endif
            </div></div>
        </div>
        @if($order->seller_is === 'seller' && $order->seller)
            @if($order->payment_status !== 'paid')<div class="alert alert-warning">{{ translate('purchase_payment_must_be_paid_before_admin_review') }}</div>@endif
            @include('admin-views.order.partials._approval-form')
        @endif
    </div>
</div>
<style>
    .order-review-panel .review-decision-section {
        border: 1px solid var(--bs-border-color, #344766);
        border-radius: 14px;
        background: var(--bs-tertiary-bg, rgba(82, 123, 185, .08));
        padding: 20px;
    }
    .order-review-panel .review-decision-section h4 {
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--bs-border-color, #344766);
    }
    .order-review-panel label { display: block; font-weight: 600; margin-bottom: 6px; }
    .order-review-panel .form-control { min-height: 42px; }
    .order-review-panel .bg-section { border: 1px solid var(--bs-border-color, #344766); }
    @media (max-width: 991px) { .order-review-panel .review-decision-section { padding: 16px; } }
</style>
