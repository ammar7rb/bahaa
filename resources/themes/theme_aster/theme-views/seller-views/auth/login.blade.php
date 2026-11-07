@extends('theme-views.layouts.app')
@section('title', translate('vendor_Login').' | '.$web_config['company_name'])
@push('css_or_js')
    <meta name="robots" content="noindex,nofollow">
    @include('layouts.vendor.partials._dark-mode-preference')
    <link rel="stylesheet" href="{{ dynamicAsset(path:'public/assets/back-end/css/vendor-dark-mode.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/front-end/css/vendor-registration-dark-mode.css') }}?v=20260826.2">
    <style>
        html body.sigma-care .vendor-login-page .create-an-account{background-color:#eef5ff!important}.vendor-login-page .vendor-login-card{border:1px solid #dce4f0;border-radius:12px;padding:28px;background:#fff}.vendor-login-page .vendor-login-card h1{font-size:22px;font-weight:700;margin:0 0 24px}.vendor-login-fields{max-width:600px}.vendor-login-page .form-group{margin-bottom:24px}.vendor-login-page label{margin-bottom:8px}.vendor-login-page .vendor-login-options{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:8px 0 24px;flex-wrap:wrap}.vendor-login-options label{display:flex;align-items:center;gap:8px;margin:0;min-height:44px}.vendor-login-options input{width:18px;height:18px;accent-color:#1a428a}.vendor-login-page .password-toggle{position:absolute;inset-inline-end:8px;top:50%;transform:translateY(-50%);display:grid;place-items:center;width:40px;height:40px;padding:0;border:0;background:transparent;color:#1a428a;cursor:pointer}.vendor-login-page input[name=password]{padding-inline-end:54px}.vendor-login-page :is(a,button,input):focus-visible{outline:3px solid #1a428a;outline-offset:3px}[theme=dark] .vendor-login-page .vendor-login-card{background:#152033;border-color:#33445d}[theme=dark] .vendor-login-page .vendor-login-card h1{color:#edf3ff}@media(max-width:575px){.vendor-login-page .vendor-login-card{padding:20px}.vendor-login-page .vendor-login-card h1{font-size:20px}}
    </style>
@endpush
@section('content')
<main class="main-content d-flex flex-column gap-3 py-3 mb-sm-5 seller-registration-page vendor-login-page">
    @include('layouts.vendor.partials._dark-mode-toggle')
    <section class="container">
        <div class="create-an-account p-3 p-sm-4">
            <img src="{{ asset('assets/front-end/img/media/sigma-medical-registration.webp') }}" alt="" class="create-an-account-bg-img">
            <div class="row gy-4">
                @include('theme-views.seller-views.auth.partial.header',['isVendorLogin'=>true])
                <div class="col-lg-8">
                    <div class="vendor-login-card">
                        <h1>{{ translate('login') }}</h1>
                        <form action="{{ route('vendor.auth.login') }}" method="post" id="vendor-login-form" class="vendor-login-fields">
                            @csrf
                            <div class="form-group">
                                <label for="signingVendorEmail">{{ translate('sigma_email_or_phone') }}</label>
                                <input type="text" class="form-control" name="email" id="signingVendorEmail" value="{{ old('email') }}" autocomplete="username" dir="ltr" style="text-align:right!important" placeholder="البريد الإلكتروني أو رقم الهاتف" required>
                            </div>
                            <div class="form-group">
                                <label for="signingVendorPassword">{{ translate('password') }}</label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" name="password" id="signingVendorPassword" autocomplete="current-password" minlength="8" placeholder="{{ translate('enter_password') }}" required>
                                    <button type="button" class="password-toggle" aria-label="{{ translate('show_password') }}" aria-pressed="false" aria-controls="signingVendorPassword" id="vendor-password-toggle"><i class="bi bi-eye-slash-fill" aria-hidden="true"></i></button>
                                </div>
                            </div>
                            <div class="vendor-login-options">
                                <label for="vendor-remember"><input id="vendor-remember" type="checkbox" name="remember" value="1">{{ translate('remember_me') }}</label>
                                <a class="text-primary fw-semibold" href="{{ route('vendor.auth.forgot-password.index') }}">{{ translate('forgot_password') }}</a>
                            </div>
                            <button type="submit" id="vendorLoginBtn" class="btn btn-primary">{{ translate('login') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
@push('script')
<script>
document.getElementById('vendor-password-toggle').addEventListener('click',function(){const input=document.getElementById('signingVendorPassword');const visible=input.type==='password';input.type=visible?'text':'password';this.setAttribute('aria-pressed',String(visible));this.querySelector('i').className=visible?'bi bi-eye-fill':'bi bi-eye-slash-fill';});
</script>
@endpush
