@extends('layouts.vendor.app')
@section('title', 'طلبات التوريد والتنفيذ')
@push('css_or_js')
<link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/vendor-fulfillment.css') }}?v=20261024.1">
@endpush
@section('content')
<div class="content container-fluid vf-page" dir="rtl">
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    <nav class="d-flex gap-2 mb-3"><a class="btn btn-outline-primary" href="{{ route('vendor.orders.list', ['status'=>'all']) }}">كل الطلبات</a><a class="btn btn-outline-danger" href="{{ route('vendor.orders.list', ['status'=>'all','fulfillment'=>'pending_vendor']) }}">طلبات معلقة من جهة التاجر</a></nav>
    <header class="vf-page-heading"><div><h1>طلبات التوريد والتنفيذ</h1><p>تابع طلباتك، جهّز المنتجات وحدّث حالة التسليم من مكان واحد.</p></div>@if($document)<button type="button" class="btn btn-outline-primary vf-print" onclick="window.print()"><i class="fi fi-rr-print" aria-hidden="true"></i> طباعة مستند التوريد</button>@endif</header>
    @foreach($orders as $row)
    @if(!$document && !($row['details_locked'] ?? false)) @include('vendor-views.order._fulfillment-portions') @endif
    @if($row['details_locked'] ?? false)
        <article class="vf-order"><header class="vf-order-heading"><h2>طلب #{{ $row['id'] }}</h2></header><div class="vf-order-body">@include('vendor-views.order.eligibility-lock') @if(!$document) @include('vendor-views.order._fulfillment-portions') @endif</div></article>
        @continue
    @endif
    @php
        $stageLabels = ['confirmed'=>'استلام الطلب','processing'=>'جاري التجهيز','out_for_delivery'=>'جاري الشحن','delivered'=>'تم التسليم'];
        $stageIcons = ['confirmed'=>'clipboard-list-check','processing'=>'box-open','out_for_delivery'=>'truck-side','delivered'=>'check-circle'];
        $stageIndex = array_search($row['order_status'], array_keys($stageLabels), true);
        $shipping = $row['shipping_assignment'];
        $responseRequired = $shipping['seller_shipping_response']['required'];
        $phone = preg_replace('/[^0-9+]/', '', $row['shipping_address_data']['phone'] ?? '');
    @endphp
    <article class="vf-order" aria-labelledby="vf-order-{{ $row['id'] }}">
        <header class="vf-order-heading"><div class="vf-order-reference"><span class="vf-icon"><i class="fi fi-rr-clipboard-list-check" aria-hidden="true"></i></span><div><h2 id="vf-order-{{ $row['id'] }}"><a href="{{ route('vendor.orders.details', $row['id']) }}">طلب #{{ $row['id'] }}</a></h2><span>{{ count($row['order_details']) }} منتج في الطلب</span></div></div><span class="vf-status">{{ translate($row['order_status']) }}</span></header>
        <div class="vf-order-body">
            @if($row['partially_locked'] ?? false)@include('vendor-views.order.eligibility-lock')@endif
            <div class="vf-progress" aria-label="مراحل تنفيذ الطلب">
                @foreach($stageLabels as $state => $label)
                <div class="vf-stage {{ $stageIndex !== false && ($loop->index < $stageIndex || ($state === 'delivered' && $row['order_status'] === 'delivered')) ? 'is-done' : '' }} {{ $row['order_status'] === $state && $state !== 'delivered' ? 'is-current' : '' }}" @if($row['order_status'] === $state) aria-current="step" @endif><span><i class="fi fi-rr-{{ $stageIndex !== false && ($loop->index < $stageIndex || ($state === 'delivered' && $row['order_status'] === 'delivered')) ? 'check' : $stageIcons[$state] }}" aria-hidden="true"></i></span><strong>{{ $label }}</strong>
                    @if(!$document && $state === 'confirmed' && $responseRequired)
                        <form method="post" action="{{ route('vendor.orders.shipping-response.store', $row['id']) }}">@csrf<button class="btn btn-sm btn-outline-primary" name="decision" value="accept">قبول الطلب</button></form>
                    @elseif(!$document && !$responseRequired && in_array($state, $shipping['allowed_seller_statuses'], true))
                        @if($state === 'delivered')<a class="btn btn-sm btn-outline-primary" href="#vf-proof-{{ $row['id'] }}">تم التسليم</a>
                        @else<form class="vendor-status-form" method="post" action="{{ route('vendor.orders.status') }}">@csrf<input type="hidden" name="id" value="{{ $row['id'] }}"><input type="hidden" name="order_status" value="{{ $state }}"><button class="btn btn-sm btn-outline-primary">تأكيد</button></form>@endif
                    @endif
                </div>
                @endforeach
            </div>
            <div class="vf-layout">
                <div class="vf-main">
                    <section class="vf-section"><h3><i class="fi fi-rr-box-open" aria-hidden="true"></i> المنتجات المطلوبة</h3><div class="table-responsive"><table class="vf-table"><thead><tr><th scope="col">منتجك</th><th scope="col">الكمية</th><th scope="col">سعر التوريد للوحدة</th></tr></thead><tbody>@foreach($row['order_details'] as $line)<tr><td>{{ $line['product_details']['name'] }}</td><td><span class="vf-quantity">{{ $line['qty'] }}</span></td><td>{{ $line['price_available'] ? webCurrencyConverter($line['price']) : 'غير محفوظ' }}</td></tr>@endforeach</tbody></table></div></section>
                    <section class="vf-section"><h3><i class="fi fi-rr-marker" aria-hidden="true"></i> بيانات التسليم</h3><dl class="vf-contact"><div><dt><i class="fi fi-rr-user" aria-hidden="true"></i> المستلم</dt><dd>{{ $row['shipping_address_data']['contact_person_name'] ?? 'غير متاح' }}</dd></div><div><dt><i class="fi fi-rr-phone-call" aria-hidden="true"></i> رقم الهاتف</dt><dd>@if($phone)<a href="tel:{{ $phone }}"><bdi>{{ $row['shipping_address_data']['phone'] }}</bdi></a>@else غير متاح @endif</dd></div><div class="vf-address"><dt><i class="fi fi-rr-marker" aria-hidden="true"></i> العنوان</dt><dd>{{ $row['shipping_address_data']['address'] ?? 'غير متاح' }}</dd></div></dl>@if(!empty($shipping['instructions']))<div class="vf-instructions"><strong>تعليمات التنفيذ</strong><p>{{ $shipping['instructions'] }}</p></div>@endif</section>
                    @if(!$document)
                    @foreach($row['order_details'] as $line)
                        @if(($line['product_details']['digital_product_type'] ?? '') === 'ready_after_sell')
                        <form class="vf-section" method="post" enctype="multipart/form-data" action="{{ route('vendor.orders.digital-file-upload-after-sell') }}">@csrf<input type="hidden" name="order_id" value="{{ $line['id'] }}"><label for="vf-digital-{{ $line['id'] }}">ملف التسليم الرقمي: {{ $line['product_details']['name'] }}</label><input id="vf-digital-{{ $line['id'] }}" type="file" class="form-control my-2" name="digital_file_after_sell" required><button class="btn btn-outline-primary"><i class="fi fi-rr-upload" aria-hidden="true"></i> حفظ ملف التسليم</button></form>
                        @endif
                    @endforeach
                    @endif
                </div>
                <aside class="vf-sidebar">
                    <section class="vf-section vf-dues"><h3><i class="fi fi-rr-wallet" aria-hidden="true"></i> مستحقاتك</h3><dl><div><dt>توريد معتمد</dt><dd>{{ $row['procurement_approved'] ? webCurrencyConverter($row['vendor_procurement_amount']) : 'غير متاح' }}</dd></div><div><dt>استحقاق الشحن</dt><dd>{{ webCurrencyConverter($row['shipping_seller_entitlement']) }}</dd></div></dl>@if(!$row['procurement_approved'])<p class="vf-muted">قيمة التوريد المعتمدة غير محفوظة لهذا الطلب التاريخي.</p>@endif<div class="vf-dates">@foreach(['expected_delivery_date'=>'التسليم المتوقع','sales_due_at'=>'استحقاق التوريد'] as $dateKey => $dateLabel)@if(!empty($shipping[$dateKey]))<div><i class="fi fi-rr-calendar" aria-hidden="true"></i><span>{{ $dateLabel }}</span><time>{{ \Carbon\Carbon::parse($shipping[$dateKey])->timezone(config('app.timezone'))->format('Y/m/d') }}</time></div>@endif
@endforeach</div></section>
                    @if(!$document)
                    <section class="vf-section vf-actions"><h3><i class="fi fi-rr-refresh" aria-hidden="true"></i> الخطوة التالية</h3><p class="vf-cta">{{ $row['fulfillment_cta'] }}</p><div class="vf-feedback" role="alert" hidden></div>
                        @if($responseRequired)
                        <p class="vf-muted">راجع المستحقات وبيانات التسليم، ثم اقبل الطلب لبدء التجهيز.</p>
                        @endif
                        @foreach($shipping['allowed_seller_statuses'] as $status)
                            @if($status === 'delivered')
                            <form class="vf-proof" enctype="multipart/form-data" method="post" action="{{ route('vendor.orders.shipping-proof.store', $row['id']) }}">@csrf<input type="hidden" name="shipping_status" value="delivered"><label for="vf-proof-{{ $row['id'] }}">إثبات التسليم</label><input id="vf-proof-{{ $row['id'] }}" type="file" name="proof" required accept="image/*,application/pdf,video/*" class="form-control my-2"><label for="vf-note-{{ $row['id'] }}">ملاحظة التسليم (اختياري)</label><input id="vf-note-{{ $row['id'] }}" type="text" name="note" maxlength="1000" class="form-control my-2"><button class="btn btn-primary vf-primary"><i class="fi fi-rr-check-circle" aria-hidden="true"></i> تم التسليم</button></form>
                            @endif
                        @endforeach
                        @if(!$responseRequired && empty($shipping['allowed_seller_statuses']))<p class="vf-muted mb-0">{{ $row['order_status'] === 'delivered' ? 'اكتمل تسليم هذا الطلب.' : 'لا توجد خطوة تنفيذ متاحة حاليًا.' }}</p>@endif
                    </section>
                    <a class="vf-document" href="{{ route('vendor.orders.generate-invoice', $row['id']) }}"><i class="fi fi-rr-document" aria-hidden="true"></i> مستند التوريد <i class="fi fi-rr-angle-small-left" aria-hidden="true"></i></a>
                    @endif
                </aside>
            </div>
        </div>
    </article>
    @endforeach
    @if($pagination){{ $pagination->links() }}@endif
    @if($orders->isEmpty())<section class="vf-empty"><i class="fi fi-rr-box-open" aria-hidden="true"></i><h2>لا توجد طلبات محالة إليك حاليًا</h2><p>ستظهر الطلبات هنا بعد اعتمادها وإرسالها من الإدارة.</p></section>@endif
</div>
@endsection
@push('script')
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/vendor-fulfillment.js') }}?v=20261024.1"></script>
@endpush
