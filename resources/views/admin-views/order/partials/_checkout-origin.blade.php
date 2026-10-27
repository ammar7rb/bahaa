@php($checkoutState = $checkoutState ?? app(\App\Services\CustomerPurchaseEligibilityService::class)->orderPayload($order))
@if($checkoutState['is_urgent_order'] ?? false)
<div class="d-flex flex-wrap gap-1 mt-2" dir="rtl" aria-label="حالة الطلب وقت الإنشاء">
    <span class="badge badge-soft-info"><i class="fi fi-rr-user" aria-hidden="true"></i> طلب أول من حساب جديد</span>
    @if(isset($checkoutState['origin']['account_activated']) && !$checkoutState['origin']['account_activated'])<span class="badge badge-soft-info">الحساب غير معتمد وقت الطلب</span>@endif
    @if(isset($checkoutState['origin']['has_active_package']) && !$checkoutState['origin']['has_active_package'])<span class="badge badge-soft-info">بدون باقة مفعّلة وقت الطلب</span>@endif
</div>
@endif
