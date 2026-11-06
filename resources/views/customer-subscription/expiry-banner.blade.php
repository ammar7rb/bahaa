@if(!request()->is('vendor', 'vendor/*', 'seller', 'seller/*') && !request()->routeIs('vendor.*', 'seller.*'))
@if(auth('customer')->check())
    @php
        $subscriptionCustomer = auth('customer')->user();
        $monthlyState = app(\App\Services\CustomerMonthlySubscriptionService::class)->status((int)$subscriptionCustomer->id);
        $creditExhausted = $monthlyState['active'] && isset($monthlyState['allowance']['available_limit'])
            && (float) $monthlyState['allowance']['available_limit'] <= 0;
    @endphp
    @if(app(\App\Services\CustomerPurchaseEligibilityService::class)->initialCheckoutGate($subscriptionCustomer)['payment_approved'] && ($monthlyState['status'] === 'expired' || $creditExhausted) && !request()->routeIs('account-order-details', 'account-oder', 'customer.subscription.index', 'customer.tracking.index'))
        <style>
            #customer-subscription-alert{background:#fff0f0;color:#842029;border-color:#f2b8bd}
            #customer-subscription-alert .btn-danger{background:#a51d2d!important;border-color:#a51d2d!important;color:#fff!important;min-height:44px;display:inline-flex;align-items:center;justify-content:center;white-space:normal}
            #customer-subscription-alert .btn-danger:hover{background:#842029!important;border-color:#842029!important}
            #customer-subscription-alert .btn-danger:focus-visible{outline:2px solid #842029;outline-offset:3px}
        </style>
        <div id="customer-subscription-notice" class="container my-3" dir="rtl">
            <div id="customer-subscription-alert" class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0" role="status">
                <div>
                    <strong class="d-block">{{ $creditExhausted ? 'نفد الحد الائتماني المتاح' : 'انتهى اشتراكك الشهري' }}</strong>
                    <span class="small">{{ $creditExhausted ? 'جدّد الحد الائتماني أو اختر باقة أعلى لإتمام شراء جديد.' : 'جدّد الاشتراك الشهري للمتابعة إلى الشراء.' }}</span>
                </div>
                <a class="btn btn-danger" href="{{ route('customer.subscription.index') }}">{{ $creditExhausted ? 'إدارة الباقة والحد الائتماني' : 'اختيار باقة شهرية جديدة' }}</a>
            </div>
        </div>
    @endif
@endif

@endif
