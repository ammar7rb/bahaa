@once
@push('css_or_js')
<link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/admin-eligibility-progress.css') }}?v=20261024.1">
@endpush
@endonce
@php
    $customerPurchaseState = app(\App\Services\CustomerPurchaseEligibilityService::class)->status($customer);
    $customerOrderState = isset($order) ? app(\App\Services\CustomerPurchaseEligibilityService::class)->orderPayload($order) : null;
    $accountClear = !$customerPurchaseState['administratively_frozen'];
    $eligibilitySteps = [
        ['label'=>'تفعيل الحساب','icon'=>'user-check','done'=>$customerPurchaseState['account_activated'],'blocked'=>false,'status'=>$customerPurchaseState['account_activated'] ? 'مفعّل' : 'بانتظار التفعيل'],
        ['label'=>'حالة الحساب','icon'=>'shield-check','done'=>$accountClear,'blocked'=>!$accountClear,'status'=>$accountClear ? 'الحساب سليم' : 'موقوف إداريًا'],
        ['label'=>'الاشتراك الشهري','icon'=>'calendar','done'=>$customerPurchaseState['subscription']['active'],'blocked'=>false,'status'=>$customerPurchaseState['subscription']['active'] ? 'نشط' : 'بانتظار الاشتراك'],
    ];
    if (isset($order)) {
        $review = $customerOrderState['payment_review_status'] ?? ($order->payment_status === 'paid' ? 'approved' : 'pending');
        $cashOnDelivery = in_array($order->payment_method, ['cash_on_delivery','cash'], true);
        $paymentDone = $review === 'approved';
        $ready = $accountClear && !$order->is_pause && $order->commerce_flow_status !== 'under_investigation'
            && (!$order->customer_checkout_operation_id || !empty($customerOrderState['eligible_at'])) && ($paymentDone || $cashOnDelivery);
        array_unshift($eligibilitySteps, ['label'=>'دفع الطلب','icon'=>'credit-card','done'=>$paymentDone,'blocked'=>$review === 'rejected','status'=>$paymentDone ? 'معتمد' : ($review === 'rejected' ? 'الإثبات مرفوض' : ($cashOnDelivery ? 'عند الاستلام' : 'قيد التحقق'))]);
        $eligibilitySteps[] = ['label'=>'جاهزية العميل','icon'=>'box-open','done'=>$ready,'blocked'=>!$accountClear || $order->is_pause || $order->commerce_flow_status === 'under_investigation','status'=>$ready ? 'مستوفٍ لشروط التنفيذ' : 'بانتظار استيفاء الشروط'];
    }
    $completedSteps = count(array_filter($eligibilitySteps, fn ($step) => $step['done']));
    $currentStep = array_key_first(array_filter($eligibilitySteps, fn ($step) => !$step['done']));
@endphp
<section class="admin-eligibility-progress mb-3" dir="rtl" aria-labelledby="eligibility-progress-title">
    @if(isset($order)) @include('admin-views.order.partials._checkout-origin', ['checkoutState' => $customerOrderState]) @endif
    <header class="eligibility-progress-heading"><div><h3 id="eligibility-progress-title">أهلية شراء العميل</h3><p>{{ ['checkout_first'=>'التسجيل لإتمام الشراء — عميل جديد','standard'=>'التسجيل من الملف الشخصي','legacy'=>'حساب سابق'][$customerPurchaseState['onboarding_journey']] ?? $customerPurchaseState['onboarding_journey'] }}</p></div><span>{{ $completedSteps }} من {{ count($eligibilitySteps) }} مكتملة</span></header>
    <ol class="eligibility-progress-steps" style="--eligibility-step-count:{{ count($eligibilitySteps) }}" aria-label="مراحل أهلية العميل">
        @foreach($eligibilitySteps as $index => $step)
        <li class="eligibility-step {{ $step['done'] ? 'is-complete' : ($step['blocked'] ? 'is-blocked' : 'is-pending') }} {{ $index > 0 && $step['done'] && $eligibilitySteps[$index-1]['done'] ? 'is-linked' : '' }}" @if($currentStep === $index) aria-current="step" @endif>
            <span class="eligibility-step-icon" aria-hidden="true"><i class="fi fi-rr-{{ $step['icon'] }}"></i>@if($step['done'])<span class="eligibility-step-check"><i class="fi fi-rr-check"></i></span>@endif</span>
            <strong>{{ $step['label'] }}</strong><span class="eligibility-step-status">{{ $step['status'] }}</span>
        </li>
        @endforeach
    </ol>
    <div class="eligibility-progress-details">
        <span>الباقة: <strong data-customer-live-package>{{ $customerPurchaseState['subscription']['package_name'] ?: 'لا توجد باقة حالية' }}</strong></span>
        <span>الانتهاء: <time data-customer-live-expiry>{{ $customerPurchaseState['subscription']['expires_at'] ? \Carbon\Carbon::parse($customerPurchaseState['subscription']['expires_at'])->format('Y/m/d H:i') : '—' }}</time></span>
        @if($customerPurchaseState['subscription']['allowance'])
        <span>الحد الممنوح: <b data-customer-live-granted>{{ $customerPurchaseState['subscription']['allowance']['granted_limit'] }}</b> ج.م</span><span>المستخدم أو المحجوز: <b data-customer-live-committed>{{ $customerPurchaseState['subscription']['allowance']['committed_limit'] }}</b> ج.م</span><span>المتبقي: <b data-customer-live-available>{{ $customerPurchaseState['subscription']['allowance']['available_limit'] }}</b> ج.م</span>
        @endif
    </div>
    @if($customerOrderState && $customerOrderState['message'])<p class="eligibility-progress-note" role="status">{{ $customerOrderState['message'] }}</p>@endif
</section>
