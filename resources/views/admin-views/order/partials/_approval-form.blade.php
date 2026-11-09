@php
    $pricing = app(\App\Services\OrderVendorPricingService::class)->summary($order, null, true);
    $approvedPricing = \App\Models\OrderVendorPricing::where('order_id', $order->id)->first();
    $reviewReleased = $order->admin_order_review_status === 'approved';
    $saleAmount = (float) $pricing['merchandise_sale'];
    $initialNet = (float) ($approvedPricing?->procurement_amount ?? $pricing['procurement_amount']);
    $initialShipping = $order->shipping_seller_entitlement ?? $order->seller_shipping_allocation ?? $order->shipping_seller_cost ?? 0;
    $deliveryDate = ($order->expected_delivery_date ?: $order->shipping_eta_to) ? \Carbon\Carbon::parse($order->expected_delivery_date ?: $order->shipping_eta_to)->format('Y-m-d') : '';
    $paymentDate = optional($order->sales_settlement_due_at ?: $order->shipping_settlement_due_at)->format('Y-m-d');
    $customLines = $errors->any() && old('items') !== null;
    $eligibility = app(\App\Services\VendorFulfillmentEligibilityService::class);
    $slotStates = $eligibility->available() ? collect($pricing['lines'])->map(fn ($line) => (bool)$eligibility->slotFor((int)$order->seller_id, (int)$line['product_id']))->unique() : collect();
    $mixed = $slotStates->count() > 1;
@endphp
@push('css_or_js')
<link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/order-vendor-review.css') }}">
@endpush
@if($reviewReleased)
    <div class="alert alert-success"><i class="fi fi-rr-check-circle" aria-hidden="true"></i> تم اعتماد الطلب وإرساله إلى التاجر.</div>
@else
<form id="unified-order-review" method="post" action="{{ route('admin.orders.admin-gate.approve-and-release', $order->id) }}" dir="rtl" class="card vendor-review mb-3">
    @csrf
    <input type="hidden" name="review_version" value="{{ app(\App\Services\AdminOrderGateService::class)->reviewVersion($order) }}">
    <div class="card-header vendor-review__header"><span class="vendor-review__icon"><i class="fi fi-rr-truck-side" aria-hidden="true"></i></span><div><h3 class="mb-1">الشحن ومستحقات التاجر</h3><p class="text-muted mb-0">حدد المبلغ والمواعيد، ثم أرسل الطلب.</p></div></div>
    <div class="card-body">
        @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="vendor-review__reference"><span>قيمة طلب العميل <strong>{{ webCurrencyConverter($order->order_amount) }}</strong></span><span>شحن العميل <strong>{{ webCurrencyConverter($order->shipping_cost) }}</strong></span></div>
        <div class="row g-3">
            <div class="col-md-6"><label for="vendor-order-amount"><i class="fi fi-rr-wallet" aria-hidden="true"></i> مستحق المنتجات</label><div class="vendor-review__money"><input id="vendor-order-amount" name="vendor_order_amount" class="form-control" type="number" min="0" max="999999999999" step="any" required value="{{ old('vendor_order_amount', $initialNet) }}" @readonly($approvedPricing !== null)><span>جنيه</span></div></div>
            <div class="col-md-6"><label for="review-shipping-entitlement"><i class="fi fi-rr-truck-side" aria-hidden="true"></i> مستحق الشحن</label><div class="vendor-review__money"><input id="review-shipping-entitlement" name="shipping_seller_entitlement" class="form-control" type="number" min="0" max="{{ $order->shipping_cost }}" step="0.01" required value="{{ old('shipping_seller_entitlement', $initialShipping) }}"><span>جنيه</span></div></div>
            <div class="col-md-6"><label for="review-delivery-date"><i class="fi fi-rr-calendar" aria-hidden="true"></i> موعد التسليم</label><input id="review-delivery-date" name="shipping_expected_delivery_date" class="form-control" type="date" required value="{{ old('shipping_expected_delivery_date', $deliveryDate) }}"></div>
            <div class="col-md-6"><label for="review-shipping-due"><i class="fi fi-rr-calendar-check" aria-hidden="true"></i> موعد سداد التاجر</label><input id="review-shipping-due" name="shipping_settlement_due_at" class="form-control" type="date" required value="{{ old('shipping_settlement_due_at', $paymentDate) }}"></div>
        </div>
        <div class="vendor-review__total mt-4"><div><span class="d-block">إجمالي مستحق التاجر</span><small class="text-muted">المنتجات + الشحن</small></div><strong><output data-vendor-total data-sale="{{ $saleAmount }}">{{ webCurrencyConverter($initialNet + (float)$initialShipping) }}</output> <small>جنيه</small></strong></div>
        <details class="vendor-review__details mt-3" @if(old('vendor_pricing_mode') === 'percent' || old('shipping_instructions')) open @endif>
            <summary><i class="fi fi-rr-settings-sliders" aria-hidden="true"></i> خيارات إضافية <span class="text-muted">· نسبة الشركة وتعليمات التاجر</span></summary>
            <div class="row g-3 pt-3">
                <div class="col-md-6"><label for="vendor-pricing-mode">طريقة الحساب</label><select id="vendor-pricing-mode" name="vendor_pricing_mode" class="form-control"><option value="amount" @selected(old('vendor_pricing_mode','amount') === 'amount')>المبلغ المحدد أعلاه</option><option value="percent" @selected(old('vendor_pricing_mode') === 'percent')>حساب المستحق من نسبة الشركة</option></select></div>
                <div class="col-md-6"><label for="company-commission">نسبة الشركة (%)</label><input id="company-commission" name="company_commission_percent" class="form-control" type="number" min="0" max="100" step="any" value="{{ old('company_commission_percent', $saleAmount > 0 ? max(0,min(100,round((1-$initialNet/$saleAmount)*100,10))) : 0) }}" @readonly($approvedPricing !== null)></div>
                <div class="col-12"><label for="review-shipping-instructions">تعليمات للتاجر <span class="text-muted">(اختياري)</span></label><textarea id="review-shipping-instructions" name="shipping_instructions" rows="2" maxlength="5000" class="form-control" placeholder="أي تفاصيل تساعد التاجر على التنفيذ">{{ old('shipping_instructions', $order->shippingDecisions()->latest('id')->value('instructions')) }}</textarea></div>
            </div>
        </details>
        @if($mixed)
        <details class="vendor-review__details mt-3" open><summary><i class="fi fi-rr-boxes" aria-hidden="true"></i> توزيع الطلب المختلط</summary><p class="small text-muted mt-2">حدد نصيب المنتجات داخل المساحات الإعلانية؛ الباقي يخص المنتجات خارجها.</p><div class="row g-3"><div class="col-md-6"><label for="accessible-procurement">مستحق المنتجات داخل المساحات</label><input id="accessible-procurement" class="form-control" type="number" min="0" step="any" name="accessible_procurement_amount" value="{{ old('accessible_procurement_amount') }}" required></div><div class="col-md-6"><label for="accessible-shipping">مستحق شحنها</label><input id="accessible-shipping" class="form-control" type="number" min="0" step="any" name="accessible_shipping_amount" value="{{ old('accessible_shipping_amount', 0) }}" required></div></div></details>
        @endif
        <details class="vendor-review__details mt-3" data-line-settings @if($customLines) open @endif>
            <summary><i class="fi fi-rr-box" aria-hidden="true"></i> تفاصيل المنتجات <span class="text-muted">· {{ count($pricing['lines']) }} منتج</span></summary>
            <label class="mt-3"><input type="checkbox" data-custom-lines @checked($customLines)> تخصيص الشحن والمواعيد لكل منتج</label>
            <p class="small text-muted">تطبق المواعيد أعلاه على جميع المنتجات، ويُسند الشحن لأول منتج. فعّل التخصيص إذا احتجت توزيعًا مختلفًا.</p>
            <noscript><p class="text-warning">راجع المواعيد والشحن لكل منتج هنا قبل الإرسال.</p></noscript>
            @foreach($pricing['lines'] as $line)
                @php($key = $line['line_id'])
                <div class="vendor-review__line" data-review-line>
                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-2"><strong>{{ $line['name'] }}</strong><small class="text-muted">الكمية {{ $line['qty'] }} · السعر الأصلي {{ $line['vendor_unit_price'] === null ? 'غير محفوظ' : webCurrencyConverter($line['vendor_unit_price']) }}</small></div>
                    <input type="hidden" name="items[{{ $key }}][vendor_unit_price]" value="{{ $line['vendor_unit_price'] }}">
                    <div class="row g-2">
                        <div class="col-md-6"><label>شحن العميل</label><input aria-label="شحن العميل للمنتج {{ $line['name'] }}" class="form-control" type="number" step="any" min="0" name="items[{{ $key }}][shipping_customer_cost]" data-line-field="customer-shipping" data-default="{{ $loop->first ? $order->shipping_cost : 0 }}" value="{{ old('items.'.$key.'.shipping_customer_cost', $loop->first ? $order->shipping_cost : 0) }}" required></div>
                        <div class="col-md-6"><label>مستحق الشحن</label><input aria-label="مستحق شحن المنتج {{ $line['name'] }}" class="form-control" type="number" step="any" min="0" name="items[{{ $key }}][shipping_vendor_cost]" data-line-field="vendor-shipping" value="{{ old('items.'.$key.'.shipping_vendor_cost', $loop->first ? $initialShipping : 0) }}" required></div>
                        <div class="col-md-6"><label>التسليم</label><input aria-label="موعد تسليم المنتج {{ $line['name'] }}" class="form-control" type="date" name="items[{{ $key }}][expected_delivery_at]" data-line-field="delivery" value="{{ old('items.'.$key.'.expected_delivery_at', $deliveryDate) }}" required></div>
                        <div class="col-md-6"><label>السداد</label><input aria-label="موعد سداد المنتج {{ $line['name'] }}" class="form-control" type="date" name="items[{{ $key }}][vendor_payment_due_at]" data-line-field="payment" value="{{ old('items.'.$key.'.vendor_payment_due_at', $paymentDate) }}" required></div>
                    </div>
                </div>
            @endforeach
        </details>
    </div>
    <div class="card-footer vendor-review__footer"><span class="small text-muted"><i class="fi fi-rr-lock" aria-hidden="true"></i> مبلغ العميل محفوظ كما هو</span><button type="submit" class="btn btn-primary px-4" @disabled(!$pricing['complete'] || $order->payment_status !== 'paid' || $order->commerce_flow_status === 'under_investigation')><i class="fi fi-rr-paper-plane" aria-hidden="true"></i> اعتماد وإرسال للتاجر</button></div>
</form>
@endif
<details class="vendor-review__details mb-3"><summary><i class="fi fi-rr-document" aria-hidden="true"></i> مراجعة الأسعار الأصلية ومبلغ العميل</summary><div class="pt-3">@include('admin-views.order.admin-gate.vendor-pricing', ['unifiedReview' => true])</div></details>
@push('script')
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/admin/order-vendor-review.js') }}" defer></script>
@endpush
