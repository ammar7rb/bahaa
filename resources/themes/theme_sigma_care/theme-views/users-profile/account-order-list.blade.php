@extends('theme-views.layouts.app')
@section('title', 'طلباتي | SIGMA')
@push('css_or_js')<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-orders.css') }}">@endpush
@section('content')
<main class="container py-4" dir="rtl"><div class="row g-3">
    @include('theme-views.partials._profile-aside')
    <section class="col-lg-9 customer-order-history">
        <header class="customer-orders-heading"><div><h1>طلباتي</h1><p>تابع مشترياتك وحالة كل طلب.</p></div><a href="{{ route('account-oder', ['order_by'=>request('order_by') === 'asc' ? 'desc' : 'asc']) }}">{{ request('order_by') === 'asc' ? 'الأحدث أولًا' : 'الأقدم أولًا' }} ↕</a></header>
        @forelse($orders as $order)
            @php($state = $order->customer_checkout_operation_id ? app(\App\Services\CustomerPurchaseEligibilityService::class)->orderPayload($order) : null)
            <article class="customer-order-card">
                <header><div><a href="{{ route('account-order-details',['id'=>$order->id]) }}"><strong>طلب #{{ $order->id }}</strong></a><time>{{ $order->created_at?->format('Y/m/d') }}</time></div><span class="customer-order-status">{{ $state && in_array($order->order_status,['pending','confirmed','processing']) ? $state['message'] : translate($order->order_status === 'processing' ? 'packaging' : $order->order_status) }}</span></header>
                <div class="customer-order-products">
                    @foreach($order->details->take(3) as $detail)
                        @php($snapshot = json_decode($detail->product_details ?? '{}',true) ?: [])
                        <div class="customer-order-product"><img src="{{ getStorageImages(path:$detail->productAllStatus?->thumbnail_full_url, type:'product') }}" alt="{{ $detail->productAllStatus?->name ?? ($snapshot['name'] ?? 'منتج') }}" loading="lazy" width="72" height="72"><div><strong>{{ $detail->productAllStatus?->name ?? ($snapshot['name'] ?? 'منتج') }}</strong><span>الكمية: {{ $detail->qty }} @if($detail->variant) · {{ $detail->variant }} @endif</span></div></div>
                    @endforeach
                    @if($order->details->count() > 3)<p>و{{ $order->details->count() - 3 }} منتجات أخرى</p>@endif
                </div>
                <footer><div><small>إجمالي الطلب</small><strong>{{ webCurrencyConverter(amount:App\Utils\OrderManager::getOrderTotalPriceSummary(order:$order)['totalAmount']) }}</strong></div><span class="customer-order-payment">{{ $order->payment_status === 'paid' ? 'تم اعتماد الدفع' : 'جاري التحقق' }}</span><a class="customer-order-details" href="{{ route('account-order-details',['id'=>$order->id]) }}">تفاصيل الطلب ←</a></footer>
                @if($order->postPurchaseInvoice?->isPayable())<a class="customer-order-invoice" href="{{ route('post-purchase-invoices.show',$order->postPurchaseInvoice->id) }}">عرض الفاتورة الإضافية</a>@endif
                @if($state && $order->payment_status === 'paid' && !$order->customer_eligible_at && !in_array($order->order_status,['canceled','failed','delivered','returned']))<div class="customer-order-help"><span>{{ $state['message'] }}</span><a href="{{ route('account-tickets') }}">التواصل مع الإدارة</a><a href="{{ route('customer.subscription.index') }}">الاشتراك الشهري</a></div>@endif
            </article>
        @empty<div class="customer-order-empty"><h2>ليس لديك طلبات بعد</h2><p>ستظهر مشترياتك هنا لتتابعها بسهولة.</p><a href="{{ route('products') }}">تصفح المنتجات ←</a></div>@endforelse
        {{ $orders->links() }}
    </section>
</div></main>
@endsection
