@php
    $profileState = app(\App\Services\CustomerPurchaseEligibilityService::class)->status($customer);
    $profileSubscription = $profileState['subscription'];
    $profileAllowance = $profileSubscription['allowance'] ?? null;
@endphp
<section class="card customer-profile-overview" aria-label="ملف العميل">
    <header class="customer-profile-identity">
        <img src="{{ getStorageImages(path: $customer->image_full_url, type: 'backend-profile') }}" alt="{{ translate('image') }}" width="64" height="64" data-customer-live-image>
        <div class="customer-profile-name">
            <h2 data-customer-live-name>{{ trim($customer->f_name.' '.$customer->l_name) ?: $customer->administrative_reference }}</h2>
            <div class="customer-profile-reference"><bdi>{{ $customer->administrative_reference }}</bdi><span>{{ translate('Join Date') }}: <time>{{ $customer->created_at?->format('Y/m/d') }}</time></span></div>
            <div class="customer-profile-contact"><a @if($customer->phone) href="tel:{{ $customer->phone }}" @endif data-customer-live-phone><bdi>{{ $customer->phone ?: '—' }}</bdi></a><a @if($customer->email) href="mailto:{{ $customer->email }}" @endif data-customer-live-email><bdi>{{ $customer->email ?: '—' }}</bdi></a></div>
        </div>
        <div class="customer-profile-actions d-print-none">
            @if(\App\Utils\Helpers::module_permission_check('customer_password_reset'))<button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#resetCustomerPasswordModal" aria-label="{{ translate('reset_password') }}" title="{{ translate('reset_password') }}"><i class="fi fi-rr-key" aria-hidden="true"></i></button>@endif
            <a class="btn btn-outline-primary btn-sm" href="#profileUpdateOffcanvas" data-bs-toggle="offcanvas" aria-label="{{ translate('edit') }}" title="{{ translate('edit') }}"><i class="fi fi-rr-pencil" aria-hidden="true"></i></a>
            <a class="btn btn-outline-primary btn-sm" href="#customerNotify" data-bs-toggle="collapse" aria-controls="customerNotify" aria-expanded="false" aria-label="{{ translate('Notify_Customer') }}" title="{{ translate('Notify_Customer') }}"><i class="fi fi-rr-bell" aria-hidden="true"></i></a>
        </div>
    </header>
    <div class="customer-profile-eligibility">
        <h3>أهلية شراء العميل</h3>
        <dl class="customer-profile-statuses">
            <div><dt>مسار التسجيل</dt><dd><span data-customer-live-journey>{{ $profileState['onboarding_journey'] === 'checkout_first' ? 'دفع مباشر' : 'عادي' }}</span></dd></div>
            <div><dt>التجميد الإداري</dt><dd><span class="profile-badge {{ $profileState['administratively_frozen'] ? 'is-danger' : 'is-neutral' }}">{{ $profileState['administratively_frozen'] ? 'موقوف' : 'لا يوجد' }}</span></dd></div>
            <div><dt>الاشتراك الشهري</dt><dd><span class="profile-badge" data-customer-live-state data-active="{{ $profileSubscription['active'] ? 'true' : 'false' }}">{{ $profileSubscription['active'] ? 'نشط' : 'غير نشط' }}</span></dd></div>
        </dl>
        <div class="customer-profile-package"><div><span class="profile-label">الباقة الحالية</span><strong data-customer-live-package>{{ $profileSubscription['package_name'] ?: 'لا توجد باقة حالية' }}</strong></div><div><span class="profile-label">انتهاء الاشتراك</span><time data-customer-live-expiry>{{ $profileSubscription['expires_at'] ? \Carbon\Carbon::parse($profileSubscription['expires_at'])->format('Y/m/d H:i') : '—' }}</time></div></div>
        <div class="customer-profile-allowance" data-profile-allowance>
            <div class="customer-profile-allowance-title"><strong>الحد الشرائي</strong><small>حد للمشتريات مستقل عن أموال المحفظة</small></div>
            @foreach(['granted'=>'الممنوح خلال الاشتراك','committed'=>'المستخدم / المحجوز','available'=>'المتبقي'] as $allowanceKey=>$allowanceLabel)
                <div><span class="profile-label">{{ $allowanceLabel }}</span><strong><bdi data-customer-live-{{ $allowanceKey }}>{{ $profileAllowance !== null ? number_format((float) $profileAllowance[$allowanceKey.'_limit'], 2) : '—' }}</bdi> <small>ج.م</small></strong></div>
            @endforeach
        </div>
    </div>
    @include('admin-views.customer.profile-history')
    <div id="customerNotify" class="collapse customer-profile-notify d-print-none">
        <form action="{{ route('admin.customer.notify') }}" method="POST">@csrf
            <input type="hidden" name="customer_ids[]" value="{{ $customer->id }}">
            <div class="row g-3"><div class="col-md-5"><label class="form-label" for="profile-notification-title">{{ translate('notification_title') }}</label><input id="profile-notification-title" name="title" class="form-control" maxlength="120" required></div><div class="col-md-7"><label class="form-label" for="profile-notification-message">{{ translate('message') }}</label><textarea id="profile-notification-message" name="message" class="form-control" rows="2" maxlength="1000" required></textarea></div></div>
            <button type="submit" class="btn btn-primary btn-sm mt-3"><i class="fi fi-rr-paper-plane" aria-hidden="true"></i> {{ translate('send_notification') }}</button>
        </form>
    </div>
</section>
