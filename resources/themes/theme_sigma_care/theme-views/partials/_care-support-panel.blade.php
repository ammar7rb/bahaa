@php($supportArabic = getLanguageCode(country_code:getDefaultLanguage()) === 'ar')
<div class="offcanvas offcanvas-end care-support-panel" tabindex="-1" id="careSupportPanel" aria-labelledby="care-support-title">
    <div class="offcanvas-header">
        <div class="care-support-heading">@include('theme-views.partials._care-icon',['icon'=>'support_icon.svg'])<h2 id="care-support-title">{{ $supportArabic ? 'تواصل معنا' : 'Contact us' }}</h2></div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ translate('close') }}"></button>
    </div>
    <div class="offcanvas-body">
        <p class="care-support-intro">{{ $supportArabic ? 'اكتب تفاصيل المشكلة التي تواجهك، ويمكنك متابعة رد الإدارة من حسابك.' : 'Describe the issue you are experiencing and follow the team’s replies from your account.' }}</p>
        @if(auth('customer')->check())
            <form id="care-support-form" action="{{ route('ticket-submit') }}" method="post">
                @csrf
                <input type="hidden" name="care_support_drawer" value="1">
                <div class="care-support-field"><label for="care-ticket-description">{{ $supportArabic ? 'تفاصيل المشكلة' : 'Issue details' }}</label><textarea class="form-control" id="care-ticket-description" name="ticket_description" rows="7" maxlength="5000" required></textarea></div>
                <div class="care-support-status" role="status" aria-live="polite" hidden></div>
                <button class="btn btn-primary care-support-submit" type="submit" data-idle-label="{{ $supportArabic ? 'إرسال المشكلة' : 'Send issue' }}" data-loading-label="{{ $supportArabic ? 'جارٍ الإرسال…' : 'Sending…' }}">{{ $supportArabic ? 'إرسال المشكلة' : 'Send issue' }}</button>
                <a class="care-support-history" href="{{ route('account-tickets') }}">{{ $supportArabic ? 'متابعة رسائلك السابقة' : 'View previous messages' }}</a>
            </form>
            <div class="care-support-success" hidden tabindex="-1">
                @include('theme-views.partials._care-icon',['icon'=>'order_placed_icon_timeline.svg'])
                <h3>{{ $supportArabic ? 'تم إرسال مشكلتك' : 'Your issue has been sent' }}</h3>
                <p>{{ $supportArabic ? 'وصلت رسالتك للإدارة، ويمكنك متابعة الرد من حسابك.' : 'The team has received your message. You can follow replies from your account.' }}</p>
                <a class="btn btn-primary" data-care-ticket-link>{{ $supportArabic ? 'متابعة الرد' : 'View replies' }}</a>
                <button type="button" class="care-support-history" data-care-new-ticket>{{ $supportArabic ? 'إرسال مشكلة أخرى' : 'Send another issue' }}</button>
            </div>
        @else
            <div class="care-support-signin">
                <h3>{{ $supportArabic ? 'خلينا نقدر نرد عليك' : 'Let us get back to you' }}</h3>
                <p>{{ $supportArabic ? 'سجّل الدخول لإرسال مشكلتك ومتابعة رد الإدارة.' : 'Sign in to send your issue and follow the team’s replies.' }}</p>
                <a class="btn btn-primary" href="{{ route('customer.auth.login') }}">{{ translate('sign_in') }}</a>
            </div>
        @endif
    </div>
    <div class="care-support-footnote">{{ $supportArabic ? 'تواصل مباشر مع إدارة سيجما' : 'Direct contact with the SIGMA team' }}</div>
</div>
