@if(auth('customer')->check())
@php($profileSubscription = app(\App\Services\CustomerMonthlySubscriptionService::class)->status((int)auth('customer')->id()))
<section class="container py-4" dir="rtl" aria-label="اختصارات الملف الشخصي">
    <div class="customer-profile-shortcuts">
        <a href="{{ route('customer.subscription.index') }}">@include('customer-subscription.icon',['name'=>$profileSubscription['active'] ? 'check' : 'calendar'])<strong>الباقة الشهرية</strong><span class="{{ $profileSubscription['active'] ? 'profile-paid' : '' }}">{{ $profileSubscription['active'] ? 'مدفوع · '.$profileSubscription['package_name'] : 'اختيار باقة' }}</span>@if($profileSubscription['active'])<small>متبقي {{ $profileSubscription['remaining_days'] }} يومًا</small>@endif</a>
        <a href="{{ route('account-oder') }}">@include('customer-subscription.icon',['name'=>'orders'])<strong>طلبات</strong><span>متابعة طلباتك</span></a>
        <a href="{{ route('wishlists') }}">@include('customer-subscription.icon',['name'=>'heart'])<strong>المفضلة</strong><span>المنتجات المحفوظة</span></a>
        <a href="{{ route('wallet') }}">@include('customer-subscription.icon',['name'=>'wallet'])<strong>رصيد المحفظة</strong><span>{{ webCurrencyConverter(auth('customer')->user()->wallet_balance) }}</span></a>
    </div>
    <h2 class="h5 mt-4">الحد الائتماني</h2>
    @if($profileSubscription['allowance'])<p>الممنوح {{ number_format($profileSubscription['allowance']['granted_limit'],2) }} ج.م · المستخدم أو المحجوز {{ number_format($profileSubscription['allowance']['committed_limit'],2) }} ج.م · المتبقي {{ number_format($profileSubscription['allowance']['available_limit'],2) }} ج.م</p>@endif
    <a class="btn btn-outline-primary" href="{{ route('customer.subscription.index') }}">زيادة الحد أو ترقية الباقة</a>
</section>
<style>.customer-profile-shortcuts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.customer-profile-shortcuts a{display:flex;flex-direction:column;gap:8px;padding:20px;border:1px solid #dce4f0;border-radius:14px;background:#fff;color:#172942;text-decoration:none;min-height:140px}.customer-profile-shortcuts strong{font-size:17px}.customer-profile-shortcuts span{font-size:14px}.customer-profile-shortcuts .profile-paid{color:#14613d;font-weight:700}.customer-profile-shortcuts a:focus-visible{outline:3px solid #1a428a;outline-offset:3px}@media(max-width:600px){.customer-profile-shortcuts{grid-template-columns:repeat(2,minmax(0,1fr))}}</style>
@endif
