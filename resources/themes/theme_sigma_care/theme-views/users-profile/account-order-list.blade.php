@extends('theme-views.layouts.app')
@section('title', 'طلباتي | SIGMA')
@push('css_or_js')<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-orders.css') }}?v=20261106">@endpush
@section('content')
<main class="container py-4" dir="rtl"><div class="row g-3">
    @include('theme-views.partials._profile-aside')
    <section class="col-lg-9 customer-order-history">
        @if(session('customer_cancel_message'))<div class="alert alert-success">{{ session('customer_cancel_message') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <header class="customer-orders-heading"><div><h1>طلباتي</h1><p>تابع مشترياتك وحالة كل طلب.</p></div><a href="{{ route('account-oder', ['show_order'=>$show_order, 'order_by'=>request('order_by') === 'asc' ? 'desc' : 'asc']) }}">{{ request('order_by') === 'asc' ? 'الأحدث أولًا' : 'الأقدم أولًا' }} ↕</a></header>
        <nav class="nav nav-pills gap-2 mb-3" aria-label="فئات الطلبات">
            @foreach(['ongoing' => 'طلبات نشطة', 'previous' => 'طلبات سابقة'] as $category => $label)
                <a @class(['nav-link', 'active' => $show_order === $category])
                    @if($show_order === $category) aria-current="page" @endif
                    href="{{ route('account-oder', ['show_order' => $category, 'order_by' => request('order_by')]) }}">{{ $label }}</a>
            @endforeach
            <a class="nav-link" href="{{ route('customer.tracking.index') }}">تتبع الطلبات</a>
        </nav>
        @forelse($orders as $order)
            @php($state = $order->customer_checkout_operation_id ? app(\App\Services\CustomerPurchaseEligibilityService::class)->orderPayload($order) : null)
            <article class="customer-order-card">
                <header><div><a href="{{ route('account-order-details',['id'=>$order->id]) }}"><strong>طلب #{{ $order->id }}</strong></a><time>{{ $order->created_at?->format('Y/m/d') }}</time></div><span class="customer-order-status">{{ $state && in_array($order->order_status,['pending','confirmed','processing']) ? $state['message'] : translate($order->order_status === 'processing' ? 'packaging' : $order->order_status) }}</span></header>
                @php($stage = ['pending' => 1, 'confirmed' => 2, 'processing' => 2, 'out_for_delivery' => 3, 'delivered' => 4][$order->order_status] ?? null)
                @if($stage)
                    <ol class="customer-order-stages list-unstyled" aria-label="مراحل الطلب">
                        @foreach(['تم الطلب', 'جاري التجهيز', 'جاري الشحن', 'تم التسليم'] as $label)
                            <li @class(['customer-order-stage', 'is-complete' => $loop->iteration < $stage, 'is-current' => $loop->iteration === $stage])
                                @if($loop->iteration === $stage) aria-current="step" @endif>{{ $label }}</li>
                        @endforeach
                    </ol>
                @endif
                <div class="customer-order-products">
                    @foreach($order->details->take(3) as $detail)
                        @php($snapshot = json_decode($detail->product_details ?? '{}',true) ?: [])
                        <div class="customer-order-product"><img src="{{ getStorageImages(path:$detail->productAllStatus?->thumbnail_full_url, type:'product') }}" alt="{{ $detail->productAllStatus?->name ?? ($snapshot['name'] ?? 'منتج') }}" loading="lazy" width="72" height="72"><div><strong>{{ $detail->productAllStatus?->name ?? ($snapshot['name'] ?? 'منتج') }}</strong><span>الكمية: {{ $detail->qty }} @if($detail->variant) · {{ $detail->variant }} @endif</span></div></div>
                    @endforeach
                    @if($order->details->count() > 3)<p>و{{ $order->details->count() - 3 }} منتجات أخرى</p>@endif
                </div>
                <footer><div><small>إجمالي الطلب</small><strong>{{ webCurrencyConverter(amount:App\Utils\OrderManager::getOrderTotalPriceSummary(order:$order)['totalAmount']) }}</strong></div><span class="customer-order-payment">{{ $order->payment_status === 'paid' ? 'تم اعتماد الدفع' : 'جاري التحقق' }}</span><a class="customer-order-details" href="{{ route('account-order-details',['id'=>$order->id]) }}">تفاصيل الطلب ←</a></footer>
                @if($state && !$order->customer_eligible_at && !in_array($order->order_status,['canceled','failed','delivered','returned']))
                    <div class="customer-order-help"><span>{{ $state['message'] }}</span>
                        @include('customer-checkout.next-action', ['purchaseState' => $state, 'actionClass' => ''])
                        @if($state['can_cancel_for_package'] ?? false)<form method="post" action="{{ route('customer.order.package-cancellation', $order->id) }}" onsubmit="return confirm('تأكيد إلغاء الطلب وطلب رد المبلغ؟')">@csrf<button class="btn btn-outline-danger" type="submit">إلغاء الطلب وطلب رد المبلغ</button></form>@endif
                        @if($state['next_action'] === 'resubmit_payment_proof')<a href="{{ route('account-order-details',['id'=>$order->id]) }}">إعادة إرسال إثبات الدفع</a>@endif
                    </div>
                @endif
                @if($state['package_refund'] ?? null)<p class="customer-order-help">{{ $state['package_refund']['status'] === 'completed' ? 'تم تسجيل رد المبلغ.' : 'راجع تفاصيل الطلب لمعرفة حالة رد المبلغ.' }}</p>@endif
                @include('customer-checkout.cancellation-request')
            </article>
        @empty<div class="customer-order-empty"><h2>ليس لديك طلبات بعد</h2><p>ستظهر مشترياتك هنا لتتابعها بسهولة.</p><a href="{{ route('products') }}">تصفح المنتجات ←</a></div>@endforelse
        {{ $orders->links() }}
    </section>
</div></main>
@endsection
