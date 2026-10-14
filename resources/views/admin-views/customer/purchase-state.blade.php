@php($customerPurchaseState = app(\App\Services\CustomerPurchaseEligibilityService::class)->status($customer))
<div class="card p-3 mb-3" dir="rtl">
    <h3 class="fs-16 mb-3">أهلية شراء العميل</h3>
    <dl class="row mb-0">
        <dt class="col-sm-3">مسار التسجيل</dt><dd class="col-sm-9">{{ ['checkout_first'=>'التسجيل لإتمام الشراء — عميل جديد','standard'=>'التسجيل من الملف الشخصي','legacy'=>'حساب سابق'][$customerPurchaseState['onboarding_journey']] ?? $customerPurchaseState['onboarding_journey'] }}</dd>
        <dt class="col-sm-3">تفعيل الحساب</dt><dd class="col-sm-9">{{ $customerPurchaseState['account_activated'] ? 'مفعّل' : 'بانتظار التفعيل' }}</dd>
        <dt class="col-sm-3">التجميد الإداري</dt><dd class="col-sm-9">{{ $customerPurchaseState['administratively_frozen'] ? 'موقوف' : 'لا يوجد' }}</dd>
        <dt class="col-sm-3">الاشتراك الشهري</dt><dd class="col-sm-9"><span data-customer-live-state>{{ $customerPurchaseState['subscription']['active'] ? 'نشط' : 'غير نشط' }}</span> — <span data-customer-live-expiry>{{ $customerPurchaseState['subscription']['expires_at'] ? \Carbon\Carbon::parse($customerPurchaseState['subscription']['expires_at'])->format('Y/m/d H:i') : '—' }}</span></dd>
        @if($customerPurchaseState['subscription']['allowance'])
            <dt class="col-sm-3">الباقة</dt><dd class="col-sm-9" data-customer-live-package>{{ $customerPurchaseState['subscription']['package_name'] ?: 'يلزم اختيار باقة جديدة' }}</dd>
            <dt class="col-sm-3">الحد الائتماني</dt><dd class="col-sm-9">إجمالي الممنوح خلال الاشتراك <span data-customer-live-granted>{{ $customerPurchaseState['subscription']['allowance']['granted_limit'] }}</span> ج.م · المستخدم أو المحجوز <span data-customer-live-committed>{{ $customerPurchaseState['subscription']['allowance']['committed_limit'] }}</span> ج.م · المتبقي <span data-customer-live-available>{{ $customerPurchaseState['subscription']['allowance']['available_limit'] }}</span> ج.م</dd>
        @endif
        @isset($order)
            @php($customerOrderState = app(\App\Services\CustomerPurchaseEligibilityService::class)->orderPayload($order))
            <dt class="col-sm-3">دفع الطلب</dt><dd class="col-sm-9">{{ $order->payment_status === 'paid' ? 'معتمد' : ($order->activation_status === 'offline_payment_rejected' ? 'إثبات مرفوض — يمكن إعادة تقديمه' : (in_array($order->payment_method,['cash_on_delivery','cash'],true) ? 'عند الاستلام' : 'قيد التحقق')) }}</dd>
            <dt class="col-sm-3">حالة طلب العميل</dt><dd class="col-sm-9">{{ $customerOrderState['message'] }}</dd>
        @endisset
    </dl>
</div>
