@if(auth('customer')->check())
    @php($monthlyState = app(\App\Services\CustomerMonthlySubscriptionService::class)->status((int)auth('customer')->id()))
    @if($monthlyState['status'] === 'expired')
        <div class="container my-3" dir="rtl"><div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0" role="status">
            <span>انتهى اشتراكك الشهري. جدّد الاشتراك للمتابعة إلى الشراء.</span><a class="btn btn-sm btn-outline-dark" href="{{ route('customer.subscription.index') }}">تجديد الاشتراك</a>
        </div></div>
    @endif
@endif
