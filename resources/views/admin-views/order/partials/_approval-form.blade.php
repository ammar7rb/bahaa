@php
    $pricing = app(\App\Services\OrderVendorPricingService::class)->summary($order);
    $approvedPricing = \App\Models\OrderVendorPricing::where('order_id', $order->id)->first();
    $reviewReleased = $order->admin_order_review_status === 'approved';
@endphp
@include('admin-views.order.admin-gate.vendor-pricing', ['unifiedReview' => true])
@if($reviewReleased)
    <div class="alert alert-success"><i class="fi fi-rr-check-circle" aria-hidden="true"></i> تم اعتماد الطلب وإرساله إلى التاجر.</div>
@else
<form id="unified-order-review" method="post" action="{{ route('admin.orders.admin-gate.approve-and-release', $order->id) }}" dir="rtl" class="card mb-3">
    @csrf
    <input type="hidden" name="review_version" value="{{ app(\App\Services\AdminOrderGateService::class)->reviewVersion($order) }}">
    <div class="card-header"><h3 class="mb-1"><i class="fi fi-rr-truck-side" aria-hidden="true"></i> الشحن ومستحقات التاجر</h3><p class="text-muted mb-0">حدد مستحق التاجر للمنتجات والشحن لهذا الطلب. مبلغ العميل المؤكد لن يتغير.</p></div>
    <div class="card-body">
        @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="vendor-order-amount">مبلغ المنتجات للتاجر في هذا الطلب</label><input id="vendor-order-amount" name="vendor_order_amount" class="form-control" type="number" min="0" max="999999999999" step="0.01" required value="{{ old('vendor_order_amount', $approvedPricing?->procurement_amount ?? $pricing['procurement_amount']) }}" @readonly($approvedPricing !== null)><small class="text-muted">إجمالي المنتجات لكل الكميات، دون الشحن.</small></div>
            <div class="col-md-6"><label class="form-label">شحن العميل المثبت عند الشراء</label><div class="form-control bg-section">{{ webCurrencyConverter($order->shipping_cost) }}</div></div>
            <div class="col-md-6"><label class="form-label" for="review-shipping-entitlement">مبلغ الشحن المخصص للتاجر</label><input id="review-shipping-entitlement" name="shipping_seller_entitlement" class="form-control" type="number" min="0" max="{{ $order->shipping_cost }}" step="0.01" required value="{{ old('shipping_seller_entitlement', $order->shipping_seller_entitlement ?? $order->seller_shipping_allocation ?? $order->shipping_seller_cost ?? 0) }}"></div>
            <div class="col-md-6"><label class="form-label" for="review-delivery-date">تاريخ التسليم المتوقع</label><input id="review-delivery-date" name="shipping_expected_delivery_date" class="form-control" type="date" required value="{{ old('shipping_expected_delivery_date', ($order->expected_delivery_date ?: $order->shipping_eta_to) ? \Carbon\Carbon::parse($order->expected_delivery_date ?: $order->shipping_eta_to)->format('Y-m-d') : '') }}"></div>
            <div class="col-md-6"><label class="form-label" for="review-shipping-due">موعد استحقاق التاجر للمنتجات والشحن</label><input id="review-shipping-due" name="shipping_settlement_due_at" class="form-control" type="date" required value="{{ old('shipping_settlement_due_at', optional($order->sales_settlement_due_at ?: $order->shipping_settlement_due_at)->format('Y-m-d')) }}"></div>
            <div class="col-12"><label class="form-label" for="review-shipping-instructions">تعليمات التنفيذ للتاجر</label><textarea id="review-shipping-instructions" name="shipping_instructions" rows="2" maxlength="5000" class="form-control">{{ old('shipping_instructions', $order->shippingDecisions()->latest('id')->value('instructions')) }}</textarea></div>
        </div>
    </div>
    <div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-3">
        <small class="text-muted">حفظ السعر والشحن والمواعيد وإرسال الطلب في خطوة واحدة.</small>
        <button type="submit" class="btn btn-primary px-4" @disabled(!$pricing['complete'] || $order->payment_status !== 'paid' || $order->commerce_flow_status === 'under_investigation')><i class="fi fi-rr-paper-plane" aria-hidden="true"></i> اعتماد وإرسال إلى التاجر</button>
    </div>
</form>
@endif

