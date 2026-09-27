@php use App\Enums\DemoConstant; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ session('direction') ?? 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="_token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="nofollow, noindex ">
    <title>{{ translate($role) }} | {{ translate('login') }}</title>
    <link rel="shortcut icon"
        href="{{ getStorageImages(path: getWebConfig(name: 'company_fav_icon'), type: 'backend-logo') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap">

    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/backend/libs/bootstrap/bootstrap.min.css') }}">
    @if ((session('direction') ?? 'ltr') === 'rtl')
        <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/backend/libs/bootstrap/bootstrap.rtl.css') }}">
    @endif
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/backend/webfonts/uicons-regular-rounded.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/backend/webfonts/uicons-solid-rounded.css') }}">

    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/backend/admin/css/style.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/backend/admin/css/style-extended.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/backend/admin/css/custom.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/admin-dark-mode.css') }}?v=20260910.4">
    <script>
        (function () {
            var storageKey = 'ba_admin_theme', storedTheme = null;
            try { storedTheme = localStorage.getItem(storageKey); } catch (error) {}
            var theme = storedTheme === 'dark' ? 'dark' : 'light';
            function applyTheme(nextTheme) { var resolvedTheme = nextTheme === 'dark' ? 'dark' : 'light'; document.documentElement.setAttribute('data-ba-admin-theme', resolvedTheme); if (document.body) document.body.setAttribute('data-bs-theme', resolvedTheme); document.querySelectorAll('[data-admin-theme-icon]').forEach(function (icon) { icon.innerHTML = resolvedTheme === 'dark' ? '&#9728;' : '&#9790;'; }); }
            window.baToggleAdminTheme = function () { var nextTheme = document.documentElement.getAttribute('data-ba-admin-theme') === 'dark' ? 'light' : 'dark'; try { localStorage.setItem(storageKey, nextTheme); } catch (error) {} applyTheme(nextTheme); };
            applyTheme(theme); document.addEventListener('DOMContentLoaded', function () { applyTheme(theme); });
        })();
    </script>

    @if ($web_config['primary_color'])
        <style>
            :root {
                --bs-primary: {!! $web_config['primary_color'] !!};
            }
        </style>
    @endif

    {!! ToastMagic::styles() !!}
</head>
<body>
    <button class="admin-auth-theme-toggle" type="button" onclick="window.baToggleAdminTheme(); return false;" aria-label="{{ translate('dark_mode') }}"><span data-admin-theme-icon aria-hidden="true">&#9790;</span></button>
    <main id="content" role="main" class="main">
        <div class="auth-wrapper">
            <div class="auth-wrapper-left"
                style="background: url('{{ dynamicAsset('public/assets/back-end/img/login-bg.webp') }}') no-repeat center center / cover">
                <div class="auth-left-cont user-select-none">
                    @php($eCommerceLogo = getWebConfig(name: 'company_web_logo'))
                    <a class="d-inline-flex mb-5" href="{{ route('home') }}">
                        <img class="ba-platform-logo" width="310" src="{{ getStorageImages(path: $eCommerceLogo, type: 'backend-logo') }}"
                            alt="Logo">
                    </a>
                    <h2 class="title h1">
                        {{ translate('Make Your Business') }}
                        <span class="fw-bold text-primary d-block text-capitalize">
                            {{ translate('Profitable...') }}
                        </span>
                    </h2>
                </div>
            </div>
            <div class="auth-wrapper-right">
                @if (SOFTWARE_VERSION)
                    <label class="badge badge-success text-bg-success float-end __inline-2 user-select-none">
                        {{ translate('software_version') }} : {{ SOFTWARE_VERSION }}
                    </label>
                @endif
                <div class="auth-wrapper-form">
                    <form action="{{ route('login') }}" method="post" id="admin-login-form"
                        class="admin-login-form-form">
                        @csrf
                        <div>
                            <div class="mb-5 user-select-none">
                                <h1 class="display-4">{{ translate('sign_in') }}</h1>
                                <h1 class="h3 text-body mb-4">
                                    {{ translate('welcome_back_to') }} {{ translate($role) }}
                                    {{ translate('Login') }}
                                </h1>
                            </div>
                        </div>

                        <input type="hidden" class="form-control mb-3" name="role" id="role"
                            value="{{ $role }}">

                        <div class="js-form-message form-group">
                            <label class="form-label user-select-none" for="signingAdminEmail">
                                {{ translate('your_email') }}
                            </label>

                            <input type="email" class="form-control form-control-lg" name="email"
                                id="signingAdminEmail" tabindex="1" placeholder="email@address.com"
                                value="{{ old('email') }}"
                                aria-label="email@address.com" required data-msg="Please enter a valid email address.">
                        </div>
                        <div class="js-form-message form-group">
                            <label class="form-label user-select-none" for="signingAdminPassword" tabindex="0">
                                <span class="d-flex justify-content-between align-items-center">
                                    {{ translate('password') }}
                                </span>
                            </label>

                            <div class="input-group">
                                <input type="password" class="js-toggle-password form-control form-control-lg"
                                    name="password" id="signingAdminPassword"
                                    placeholder="{{ translate('Minimum_8_characters_required') }}"
                                    aria-label="{{ translate('Minimum_8_characters_required') }}" required
                                    data-msg="{{ translate('your_password_is_invalid.') }} {{ translate('please_try_again.') }}">
                                <div id="changePassTarget" class="input-group-append changePassTarget">
                                    <a class="text-body-light" href="javascript:">
                                        <i id="changePassIcon" class="fi fi-sr-eye-crossed"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-2">
                            <div class="form-check d-flex gap-2">
                                <input type="checkbox" class="custom-control-input form-check-input checkbox--input"
                                    id="termsCheckbox" name="remember">
                                <label class="custom-control-label text-muted user-select-none" for="termsCheckbox">
                                    {{ translate('remember_me') }}
                                </label>
                            </div>
                        </div>


                        <button type="submit" id="admin-login-btn" class="btn btn-lg btn-block btn-primary mt-2">
                            {{ translate('sign_in') }}
                        </button>
                    </form>

                    @if (env('APP_MODE') == 'demo')
                        <div class="card-footer py-3">
                            <div class="row align-items-center">
                                <div class="col-10">
                                    <div class="align-items-baseline d-flex flex-column gap-1">
                                        <span id="admin-email" data-email="{{ DemoConstant::ADMIN['email'] }}">
                                            {{ translate('email') }} : {{ DemoConstant::ADMIN['email'] }}
                                        </span>
                                        <span id="admin-password" data-password="{{ DemoConstant::ADMIN['password'] }}">
                                            {{ translate('password') }} : {{ DemoConstant::ADMIN['password'] }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-2">
                                    <button class="btn btn-primary icon-btn" id="copyLoginInfo">
                                        <i class="fi fi-rr-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>

    <span id="message-copied_success" data-text="{{ translate('copied_successfully') }}"></span>

    <script src="{{ dynamicAsset(path: 'public/assets/new/back-end/libs/jquery/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/backend/libs/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/new/back-end/js/script.js') }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/new/back-end/js/script-extended.js') }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/backend/admin/js/auth.js') }}"></script>

    {!! ToastMagic::scripts() !!}

    @if ($errors->any())
        <script>
            "use strict";
            @foreach ($errors->all() as $error)
                toastMagic.error('{{ $error }}');
            @endforeach
        </script>
    @endif


</body>

</html>
