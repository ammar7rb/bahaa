@if(auth('customer')->check() && request()->is('user-profile', 'user-account', 'account-notifications', 'account-messages', 'account-tickets', 'support-ticket*'))
@php($headerCustomer = auth('customer')->user())
<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-profile-communication.css') }}?v=20261105">
<header class="container customer-profile-header" data-customer-communication data-feed-url="{{ route('customer.communication.index') }}">
    <div class="customer-profile-identity">
        <img src="{{ getStorageImages(path: $headerCustomer->image_full_url, type: 'avatar') }}" alt="" width="48" height="48">
        <div>
            @php($isArabic = getLanguageCode(country_code: getDefaultLanguage()) === 'ar')
            <div class="customer-identity-name"><strong>{{ $headerCustomer->f_name }} {{ $headerCustomer->l_name }}</strong>
            <span class="customer-status {{ $headerCustomer->is_active ? 'is-active' : 'is-suspended' }}"><span aria-hidden="true">●</span>
                {{ $headerCustomer->is_active ? ($isArabic ? 'نشط' : 'Active') : ($isArabic ? 'معلق' : 'Suspended') }}
            </span></div>
            <div class="customer-reference"><bdi dir="ltr" id="customer-account-reference">{{ $headerCustomer->administrative_reference }}</bdi><button type="button" data-copy-account aria-label="{{ $isArabic ? 'نسخ رقم الحساب' : 'Copy account number' }}" title="{{ $isArabic ? 'نسخ رقم الحساب' : 'Copy account number' }}"><i class="bi bi-copy" aria-hidden="true"></i><span>{{ $isArabic ? 'نسخ' : 'Copy' }}</span></button><span class="visually-hidden" aria-live="polite" data-copy-feedback></span></div>
        </div>
    </div>
    <nav class="customer-profile-actions" aria-label="{{ translate('messages') }} / {{ translate('notifications') }}">
        @foreach([['messages','message','الرسائل','Messages','customer.messages.index'],['notifications','bell','الإشعارات','Notifications','customer.notifications.index']] as [$type,$icon,$ar,$en,$route])
        @php($label = getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? $ar : $en)
        <details class="customer-communication-preview" data-communication-type="{{ $type }}">
            <summary aria-label="{{ $label }}" title="{{ $label }}">@include('customer-subscription.icon',['name'=>$icon])<span class="customer-communication-label">{{ $label }}</span><span data-communication-total hidden></span></summary>
            <section class="customer-preview-panel" aria-label="{{ $label }}">
                <strong>{{ $label }}</strong>
                <div data-preview-items aria-live="polite"><p>{{ translate('loading') }}…</p></div>
                <a class="customer-view-all" href="{{ route($route) }}">{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'عرض الكل' : 'View all' }}</a>
            </section>
        </details>
        @endforeach
    </nav>
</header>
@push('script')<script src="{{ asset('assets/front-end/js/customer-profile-communication.js') }}?v=20261105" defer></script>@endpush
@endif
