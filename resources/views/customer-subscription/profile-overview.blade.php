@php($monthly = app(\App\Services\CustomerMonthlySubscriptionService::class)->status((int) auth('customer')->id()))
<section class="profile-overview" aria-label="ملخص حسابك">
    <a class="profile-subscription {{ $monthly['active'] ? 'profile-subscription-paid' : '' }}" href="{{ route('customer.subscription.index') }}">
        <span class="profile-overview-icon" aria-hidden="true">@include('customer-subscription.icon',['name'=>$monthly['active'] ? 'check' : 'calendar'])</span>
        <span class="profile-subscription-copy"><strong>{{ $monthly['active'] ? $monthly['package_name'] : 'الباقة الشهرية' }}</strong><span>{{ $monthly['active'] ? 'متبقي '.$monthly['remaining_days'].' يومًا · تنتهي '.\Carbon\Carbon::parse($monthly['expires_at'])->format('Y/m/d') : ($monthly['status'] === 'expired' ? 'انتهت الباقة' : 'لم تبدأ باقتك بعد') }}</span><small>{{ $monthly['active'] ? 'إدارة الباقة' : 'اختيار الباقة والدفع' }} <span aria-hidden="true">←</span></small></span>
        <span class="profile-subscription-status">{{ $monthly['active'] ? 'مدفوع' : ($monthly['status'] === 'expired' ? 'منتهية' : 'غير فعالة') }}</span>
    </a>
    <div class="profile-account-metrics">
        <a href="{{ route('account-oder') }}" class="profile-account-metric"><span class="profile-metric-label">@include('customer-subscription.icon',['name'=>'orders']) طلبات</span><strong>{{ $total_order }}</strong></a>
        <a href="{{ route('wishlists') }}" class="profile-account-metric"><span class="profile-metric-label">@include('customer-subscription.icon',['name'=>'heart']) المفضلة</span><strong>{{ $wishlists }}</strong></a>
        <a href="{{ route('wallet') }}#purchase-balance" class="profile-account-metric"><span class="profile-metric-label">@include('customer-subscription.icon',['name'=>'wallet']) رصيد المحفظة</span><strong dir="auto">{{ webCurrencyConverter($totalWalletBalance ?? auth('customer')->user()->wallet_balance ?? 0) }}</strong></a>
    </div>
</section>
@if($monthly['allowance'])
<section class="profile-credit-details" aria-labelledby="profile-credit-title"><div><h3 id="profile-credit-title">الحد الائتماني</h3><p>الممنوح {{ number_format($monthly['allowance']['granted_limit'],2) }} ج.م · المستخدم أو المحجوز {{ number_format($monthly['allowance']['committed_limit'],2) }} ج.م · المتبقي {{ number_format($monthly['allowance']['available_limit'],2) }} ج.م</p></div><a href="{{ route('customer.subscription.index') }}#credit-increase">{{ $monthly['active'] ? 'زيادة الحد أو ترقية الباقة' : 'اختيار باقة' }} <span aria-hidden="true">←</span></a></section>
@endif
