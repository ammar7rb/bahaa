@php($monthly = app(\App\Services\CustomerMonthlySubscriptionService::class)->status((int) auth('customer')->id()))
<section class="profile-overview" aria-label="ملخص حسابك">
    <a class="profile-subscription" href="{{ route('customer.subscription.index') }}">
        <span class="profile-overview-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-12 5 2 2 5-5"/></svg></span>
        <span class="profile-subscription-copy"><strong>الاشتراك الشهري</strong><span>{{ $monthly['active'] ? 'متبقي '.$monthly['remaining_days'].' يومًا' : ($monthly['status'] === 'expired' ? 'انتهى الاشتراك' : 'لم يبدأ اشتراكك بعد') }}</span><small>{{ $monthly['active'] ? 'إدارة اشتراكك' : ($monthly['status'] === 'expired' ? 'تجديد الاشتراك' : 'عرض الاشتراك والدفع') }} <span aria-hidden="true">←</span></small></span>
        <span class="profile-subscription-status">{{ $monthly['active'] ? 'فعال' : ($monthly['status'] === 'expired' ? 'منتهي' : 'غير فعال') }}</span>
    </a>
    <div class="profile-account-metrics">
        <a href="{{ route('account-oder') }}" class="profile-account-metric"><span class="profile-metric-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h8M8 17h4"/></svg>{{ translate('Orders') }}</span><strong>{{ $total_order }}</strong></a>
        <div class="profile-account-metric"><span class="profile-metric-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 5c-3-3-7-1-8 1-1-2-5-4-8-1-5 5 8 15 8 15S25 10 20 5Z"/></svg>{{ translate('wishlist') }}</span><strong>{{ $wishlists }}</strong></div>
        <a href="{{ route('wallet') }}#purchase-balance" class="profile-account-metric"><span class="profile-metric-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 9h18m-5 5h3"/></svg>{{ translate('purchase_balance_title') }}</span><strong dir="auto">{{ webCurrencyConverter($totalWalletBalance ?? 0) }}</strong></a>
    </div>
</section>
