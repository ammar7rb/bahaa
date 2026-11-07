@extends(in_array(theme_root_path(), ['theme_aster', 'theme_sigma_care'], true) ? 'theme-views.layouts.app' : 'layouts.front-end.app')
@section('title', translate('settings'))
@section('content')
<main class="container py-4 customer-settings">
    <div class="row g-4">
        @includeFirst(['theme-views.partials._profile-aside', 'web-views.partials._profile-aside'])
        <section class="col-lg-9 customer-settings-content">
            <header class="customer-settings-heading"><h1>{{ translate('settings') }}</h1><p>إدارة أمان حسابك وتفضيلاتك</p></header>
            @if(session('settings_status'))<p class="alert alert-success" role="status">{{ session('settings_status') }}</p>@endif
            @if($errors->any())<p class="alert alert-danger" role="alert">{{ $errors->first() }}</p>@endif
            <section class="card card-body mb-4">
                <h2 class="h4">{{ translate('change_password') }}</h2>
                <form method="post" action="{{ route('customer.settings.password') }}">
                    @csrf
                    @foreach(['current_password' => 'current_password', 'password' => 'new_password', 'password_confirmation' => 'confirm_password'] as $field => $label)
                        <label for="settings-{{ $field }}">{{ translate($label) }} *</label>
                        <input class="form-control mb-3" id="settings-{{ $field }}" name="{{ $field }}" type="password" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" maxlength="255" required>
                        @if($field === 'current_password')<a class="settings-forgot" href="{{ route('customer.password-recovery.form') }}" data-settings-dialog="recovery-dialog">هل نسيت كلمة المرور؟</a>@endif
                    @endforeach
                    <button class="btn btn-primary" type="submit">{{ translate('save') }}</button>
                </form>
            </section>
            <section class="card card-body mb-4">
                <h2 class="h4">{{ translate('language') }}</h2>
                <ul class="list-unstyled mb-0">
                    @foreach($web_config['language'] as $language)
                        @if($language['status'] == 1)
                            <li class="change-language" data-action="{{ route('change-language') }}" data-language-code="{{ $language['code'] }}">
                                <button class="btn btn-link" type="button" lang="{{ $language['code'] }}" @if($language['code'] === getLanguageCode(country_code:getDefaultLanguage())) aria-current="true" @endif>{{ $language['name'] }}</button>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </section>
<section class="customer-settings-actions"><a class="btn btn-outline-primary" href="{{ route('customer.auth.logout') }}">{{ translate('logout') }}</a></section>
            <a class="btn btn-outline-danger" href="{{ route('customer.deletion.show') }}" data-settings-dialog="deletion-dialog">{{ translate('delete_account') }}</a>
        </section>
    </div>
</main>
<dialog id="recovery-dialog" class="settings-dialog" aria-labelledby="recovery-title">
    <div class="settings-dialog-head"><h2 id="recovery-title">استعادة كلمة المرور</h2><button type="button" class="settings-dialog-close" data-dialog-close aria-label="إغلاق">&times;</button></div>
    <p>أدخل رقم الهاتف أو البريد المسجل، وسيتواصل معك الدعم للتحقق من الحساب.</p>
    <form method="post" action="{{ route('customer.password-recovery.request') }}">@csrf
        <label for="settings-recovery-identity">رقم الهاتف أو البريد الإلكتروني</label>
        <input class="form-control" id="settings-recovery-identity" name="identity" value="{{ old('identity') }}" autocomplete="username" maxlength="191" required>
        <div class="settings-dialog-actions"><button class="btn btn-primary" type="submit">إرسال طلب الاستعادة</button><button class="btn btn-outline-secondary" type="button" data-dialog-close>إلغاء</button></div>
    </form>
</dialog>
<dialog id="deletion-dialog" class="settings-dialog settings-dialog-frame" aria-label="حذف الحساب">
    <button type="button" class="settings-dialog-close" data-dialog-close aria-label="إغلاق">&times;</button>
    <iframe title="خطوات حذف الحساب" data-deletion-frame></iframe>
</dialog>
@endsection
@push('script')<script src="{{ asset('assets/front-end/js/customer-settings-dialogs.js') }}?v=20261108" defer></script>@endpush
