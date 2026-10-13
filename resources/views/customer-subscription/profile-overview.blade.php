@php($monthly = app(\App\Services\CustomerMonthlySubscriptionService::class)->status((int) auth('customer')->id()))
<section class="profile-overview" aria-label="ملخص حسابك">
    <a class="profile-subscription {{ $monthly['active'] ? 'profile-subscription-paid' : '' }}" href="{{ route('customer.subscription.index') }}">
        <span class="profile-overview-icon" aria-hidden="true">@include('customer-subscription.icon',['name'=>$monthly['active'] ? 'check' : 'calendar'])</span>
        <span class="profile-subscription-copy"><strong>{{ $monthly['active'] ? $monthly['package_name'] : 'الباقة الشهرية' }}</strong><span>{{ $monthly['active'] ? 'متبقي '.$monthly['remaining_days'].' يومًا · تنتهي '.\Carbon\Carbon::parse($monthly['expires_at'])->format('Y/m/d') : ($monthly['status'] === 'expired' ? 'انتهت الباقة' : 'لم تبدأ باقتك بعد') }}</span><small>{{ $monthly['active'] ? 'إدارة الباقة' : 'اختيار الباقة والدفع' }} <span aria-hidden="true">←</span></small></span>
        <span class="profile-subscription-status">{{ $monthly['active'] ? 'مدفوع' : ($monthly['status'] === 'expired' ? 'منتهية' : 'غير فعالة') }}</span>
    </a>
    <div class="profile-account-metrics">
        <a href="{{ route('customer.subscription.index') }}" class="profile-subscription profile-account-metric"><span class="profile-overview-icon" aria-hidden="true">@include('customer-subscription.icon',['name'=>'calendar'])</span><span class="profile-subscription-copy"><strong>اسم الباقة</strong><span class="profile-metric-value" dir="auto">{{ $monthly['active'] ? $monthly['package_name'] : 'لا توجد باقة فعالة' }}</span><small>عرض الباقات <span aria-hidden="true">←</span></small></span></a>
        <a href="{{ route('customer.subscription.index') }}#credit-increase" class="profile-subscription profile-account-metric"><span class="profile-overview-icon" aria-hidden="true">@include('customer-subscription.icon',['name'=>'card'])</span><span class="profile-subscription-copy"><strong>الحد الائتماني المتبقي</strong><span class="profile-metric-value" dir="auto">{{ number_format($monthly['allowance']['available_limit'] ?? 0,2) }} ج.م</span><small>زيادة الحد أو الترقية <span aria-hidden="true">←</span></small></span></a>
        <a href="{{ route('wallet') }}#purchase-balance" class="profile-subscription profile-account-metric"><span class="profile-overview-icon" aria-hidden="true">@include('customer-subscription.icon',['name'=>'wallet'])</span><span class="profile-subscription-copy"><strong>رصيد المحفظة</strong><span class="profile-metric-value" dir="auto">{{ webCurrencyConverter($totalWalletBalance ?? auth('customer')->user()->wallet_balance ?? 0) }}</span><small>عرض المحفظة <span aria-hidden="true">←</span></small></span></a>
    </div>
</section>
@if($monthly['allowance'])
<section class="profile-credit-details" aria-labelledby="profile-credit-title"><h3 id="profile-credit-title">الحد الائتماني</h3><a href="{{ route('customer.subscription.index') }}#credit-increase">{{ $monthly['active'] ? 'زيادة الحد أو ترقية الباقة' : 'اختيار باقة' }} <span aria-hidden="true">←</span></a></section>
@endif
