<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ session('direction') ?? "ltr" }}">

    <head>
        @include('layouts.partials._unified-print-styles')
    <meta charset="utf-8">
    <meta name="_token" content="{{ csrf_token() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="robots" content="nofollow, noindex">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover">

    <title>@yield('title')</title>
    <link rel="shortcut icon"
          href="{{ getStorageImages(path: getWebConfig(name: 'company_fav_icon'), type: 'backend-logo') }}">

    @include("layouts.admin.partials._style-partials")

    {!! ToastMagic::styles() !!}

    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/admin-v2.css') }}?v=20260910.7">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/admin-dark-mode.css') }}?v=20260910.4">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/admin-workspace.css') }}?v=20261010.4">
    <script>
        (function () {
            var storageKey = 'ba_admin_theme';
            var storedTheme = null;
            try { storedTheme = localStorage.getItem(storageKey); } catch (error) {}
            var theme = storedTheme === 'dark' ? 'dark' : 'light';

            window.baApplyAdminTheme = function (nextTheme) {
                var resolvedTheme = nextTheme === 'dark' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-ba-admin-theme', resolvedTheme);
                if (document.body) {
                    document.body.setAttribute('data-bs-theme', resolvedTheme);
                }
                document.querySelectorAll('[data-admin-theme-icon]').forEach(function (icon) {
                    icon.innerHTML = resolvedTheme === 'dark' ? '&#9728;' : '&#9790;';
                });
                document.querySelectorAll('[data-admin-theme-toggle]').forEach(function (button) {
                    button.setAttribute('aria-pressed', resolvedTheme === 'dark' ? 'true' : 'false');
                    button.setAttribute('aria-label', resolvedTheme === 'dark' ? '{{ translate('light_mode') }}' : '{{ translate('dark_mode') }}');
                    button.setAttribute('title', resolvedTheme === 'dark' ? '{{ translate('light_mode') }}' : '{{ translate('dark_mode') }}');
                });
            };

            window.baToggleAdminTheme = function () {
                var nextTheme = document.documentElement.getAttribute('data-ba-admin-theme') === 'dark' ? 'light' : 'dark';
                try { localStorage.setItem(storageKey, nextTheme); } catch (error) {}
                window.baApplyAdminTheme(nextTheme);
            };

            window.baApplyAdminTheme(theme);
            document.addEventListener('DOMContentLoaded', function () { window.baApplyAdminTheme(theme); });
        })();
    </script>

    {{-- Printed admin pages always use a neutral paper palette. This is deliberately
         independent from the saved light/dark preference so reports remain readable. --}}
    <style id="ba-admin-print-styles">
        @media print {
            @page { size: auto; margin: 12mm; }
            html, body { background: #fff !important; color: #111827 !important; color-scheme: light !important; }
            .v2-header, .v2-sidebar, .v2-rail, .v2-ctxpanel, .v2-global-search,
            .loader-container, #loading, .toast-container, .swal2-container,
            .modal, .offcanvas, [data-print-hidden] { display: none !important; }
            .v2-main, .content, .container-fluid { width: 100% !important; margin: 0 !important; padding: 0 !important; background: #fff !important; color: #111827 !important; }
            .v2-main *, .content * { color: #111827 !important; text-shadow: none !important; box-shadow: none !important; }
            .card, .card-header, .card-body, .table-responsive, .table,
            .table > :not(caption) > * > * { background: #fff !important; border-color: #cbd5e1 !important; }
            .btn, .action-btn, .btn-group, .pagination, .dropdown-menu { display: none !important; }
            a[href]::after { content: none !important; }
            .badge { border: 1px solid #64748b !important; }
            img { max-width: 100% !important; page-break-inside: avoid; }
        }
    </style>

    @stack('css_or_js')
</head>

<body data-bs-theme="light" class="{{ env('APP_MODE') == 'demo' ? 'demo' : '' }} v2-active">
<script type="text/javascript">
    localStorage.getItem('aside-mini') === 'true' ? document.body.classList.add('aside-mini') : document.body.classList.remove('aside-mini');
</script>

<div class="row">
    <div class="col-12 position-fixed loader-container mt-10rem">
        <div id="loading" class="d--none">
            <div id="loader"></div>
        </div>
    </div>
</div>

@include('layouts.admin.partials.v2._body')

<audio id="myAudio">
    <source src="{{ dynamicAsset(path: 'public/assets/backend/sound/notification.mp3') }}" type="audio/mpeg">
</audio>

<span class="d-none" id="text-validate-translate"
        data-required="{{ translate('this_field_is_required') }}"
        data-file-size-larger="{{ translate('file_size_is_larger') }}"
        data-max-limit-crossed="{{ translate('max_limit_crossed') }}"
        data-something-went-wrong="{{ translate('something_went_wrong!') }}"
        data-passwords-do-not-match="{{ translate('passwords_do_not_match') }}"
        data-valid-email="{{ translate('please_enter_a_valid_email') }}"
        data-password-validation="{{ translate('password_must_be_8+_chars_with_upper,_lower,_number_&_symbol') }}"
        data-file-type-not-allowed="{{ translate('Invalid_file_type_selected') }}"
    ></span>

@include('layouts.admin.partials._translator-for-js')
@include("layouts.admin.partials._translated-message-container")
@include("layouts.admin.partials._routes-list-container")
@include("layouts.admin.partials._script-partials")

<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/admin-v2.js') }}?v=20261010.4"></script>
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/v2-pjax.js') }}"></script>
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/full-page-print.js') }}?v={{ filemtime(public_path('assets/back-end/js/full-page-print.js')) }}"></script>

@stack('script')
<script src="{{ dynamicAsset(path: 'public/assets/front-end/js/egypt-phone-validation.js') }}" defer></script>


</body>

</html>
