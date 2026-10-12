@php($monthly = app(\App\Services\CustomerMonthlySubscriptionService::class)->status((int) auth('customer')->id()))
<a class="monthly-entry" href="{{ route('customer.subscription.index') }}">
    <span class="monthly-entry-icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-13 5 3 3 5-5"/></svg></span>
    <span><strong>الاشتراك الشهري</strong><small>{{ $monthly['active'] ? 'اشتراك فعال · متبقي '.$monthly['remaining_days'].' يومًا' : ($monthly['status'] === 'expired' ? 'انتهى الاشتراك · جدّد للمتابعة' : 'إدارة الاشتراك والدفع') }}</small></span>
    <span aria-hidden="true">←</span>
</a>
