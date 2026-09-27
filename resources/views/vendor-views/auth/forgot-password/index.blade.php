<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="_token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ translate('forgot_password') }}</title>
    <link rel="shortcut icon"
        href="{{ getStorageImages(path: getWebConfig(name: 'company_fav_icon'), type: 'backend-logo') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/backend/libs/fonts/inter/inter.css') }}">

    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/google-fonts.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/vendor.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/vendor/icon-set/style.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/theme.minc619.css?v=1.0') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/style.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/vendor-dark-mode.css') }}">
    @include('layouts.vendor.partials._dark-mode-preference')
    <link rel="stylesheet"
        href="{{ dynamicAsset(path: 'public/assets/back-end/plugins/intl-tel-input/css/intlTelInput.css') }}">
    <style>.default-captcha-container,.dynamic-default-and-recaptcha-section,.generate-firebase-auth-recaptcha{display:none!important}</style>

    @if ($web_config['primary_color'])
        <style>
            :root {
                --bs-primary: {!! $web_config['primary_color'] !!};
            }
        </style>
    @endif


    {!! ToastMagic::styles() !!}
</head>

@php($recaptcha = getWebConfig(name: 'recaptcha'))

<body>
    @include('layouts.vendor.partials._dark-mode-toggle')
    <main id="content" role="main" class="main">
        <div class="auth-wrapper">
            <div class="auth-wrapper-left"
                style="background: url('{{ dynamicAsset(path: 'public/assets/back-end/img/login-bg.webp') }}') no-repeat center center / cover">
                <div class="auth-left-cont">
                    @php($eCommerceLogo = getWebConfig(name: 'company_web_logo'))
                    <a class="d-inline-flex mb-5" href="{{ route('home') }}">
                        <img width="310" src="{{ getStorageImages(path: $eCommerceLogo, type: 'backend-logo') }}"
                            alt="Logo">
                    </a>
                    <h2 class="title">{{ translate('Make Your Business') }} <span
                            class="font-weight-bold c1 d-block text-capitalize">{{ translate('Profitable...') }}</span>
                    </h2>
                </div>
            </div>
            <div class="auth-wrapper-right">
                <div class="auth-wrapper-form">
                    <div>
                        <div class="d-block d-lg-none">
                            <a class="d-inline-flex mb-3" href="{{ route('home') }}">
                                <img width="100"
                                    src="{{ getStorageImages(path: $eCommerceLogo, type: 'backend-logo') }}"
                                    alt="Logo">
                            </a>
                        </div>

                        <div class="mb-5">
                            <h1 class="display-4">{{ translate('forgot_password') . '?' }}</h1>
                            <h1 class="h4 text-gray-900 mb-4">
                                {{ translate('password_reset_support_title') }}
                            </h1>
                        </div>

                        <p class="font-size-md text-muted mb-4">
                            {{ translate('password_reset_support_message') }}
                        </p>
                        <div class="alert alert-info mb-4" role="alert">
                            {{ translate('password_reset_support_identity_notice') }}
                        </div>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="{{ route('vendor.auth.login') }}" class="btn btn-lg btn-secondary flex-grow-1">
                                {{ translate('back_to_login') }}
                            </a>
                            <a href="{{ route('contacts', ['subject' => translate('password_reset_support_subject')]) }}"
                               class="btn btn-lg btn--primary flex-grow-1">
                                {{ translate('contact_support') }}
                            </a>
                        </div>
                    </div>

                    @if (env('APP_MODE') == 'demo')
                        <div class="card-footer">
                            <div class="row">
                                <div class="col-10">
                                    <span id="admin-email"
                                        data-email="{{ \App\Enums\DemoConstant::VENDOR['email'] }}">{{ translate('email') }}
                                        : {{ \App\Enums\DemoConstant::VENDOR['email'] }}</span><br>
                                    <span id="admin-password"
                                        data-password="{{ \App\Enums\DemoConstant::VENDOR['password'] }}">{{ translate('password') }}
                                        : {{ \App\Enums\DemoConstant::VENDOR['password'] }}</span>
                                </div>
                                <div class="col-2">
                                    <button class="btn btn--primary" id="copyLoginInfo"><i class="tio-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade password-reset-successfully-modal" tabindex="-1" aria-labelledby="toggle-modal"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header border-0 pb-0 d-flex justify-content-end">
                    <button type="button" class="btn-close border-0" data-dismiss="modal" aria-label="Close"><i
                            class="tio-clear"></i></button>
                </div>
                <div class="modal-body px-4 px-sm-5 pt-0">
                    <div class="d-flex flex-column align-items-center text-center gap-2 mb-2">
                        <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/password-reset.png') }}"
                            width="70" class="mb-3 mb-20" alt="">
                        <h5 class="modal-title">{{ translate('password_reset_successfully') }}</h5>
                        <div class="text-center">
                            {{ translate('a_password_reset_mail_has_sent_to_your_email') . '. ' . translate('please_check_your_email') . '.' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <span class="system-default-country-code" data-value="{{ getWebConfig(name: 'country_code') ?? 'us' }}"></span>
    <span id="message-please-check-recaptcha" data-text="{{ translate('please_check_the_recaptcha') }}"></span>
    <span id="message-copied_success" data-text="{{ translate('copied_successfully') }}"></span>
    <span id="route-get-session-recaptcha-code" data-route="{{ route('get-session-recaptcha-code') }}"
        data-mode="{{ env('APP_MODE') }}"></span>
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/vendor.min.js') }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/theme.min.js') }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/backend/vendor/js/auth.js') }}"></script>

    {!! ToastMagic::scripts() !!}

    @if ($errors->any())
        <script>
            'use strict';
            @foreach ($errors->all() as $error)
                toastMagic.error('{{ $error }}');
            @endforeach
        </script>
    @endif

    {{-- CAPTCHA was removed from all authentication channels. --}}

    <script src="{{ dynamicAsset(path: 'public/assets/backend/libs/intl-tel-input/js/intlTelInput.js') }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/backend/libs/intl-tel-input/js/utils.js') }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/backend/libs/intl-tel-input/js/intlTelInout-validation.js') }}"></script>

    @php($fcmCredentials = getWebConfig('fcm_credentials'))
    <span id="Firebase_Configuration_Config" data-api-key="{{ $fcmCredentials['apiKey'] ?? '' }}"
          data-auth-domain="{{ $fcmCredentials['authDomain'] ?? '' }}"
          data-project-id="{{ $fcmCredentials['projectId'] ?? '' }}"
          data-storage-bucket="{{ $fcmCredentials['storageBucket'] ?? '' }}"
          data-messaging-sender-id="{{ $fcmCredentials['messagingSenderId'] ?? '' }}"
          data-app-id="{{ $fcmCredentials['appId'] ?? '' }}"
          data-measurement-id="{{ $fcmCredentials['measurementId'] ?? '' }}"
          data-csrf-token="{{ csrf_token() }}"
          data-route="{{ route('system.subscribeToTopic') }}"
          data-recaptcha-store="{{ route('g-recaptcha-response-store') }}"
          data-favicon="{{ $web_config['fav_icon']['path'] }}"
          data-firebase-service-worker-file="{{ dynamicAsset(path: 'firebase-messaging-sw.js') }}"
          data-firebase-service-worker-scope="{{ dynamicAsset(path: 'firebase-cloud-messaging-push-scope') }}"
    >
    </span>

    <script src="{{ dynamicAsset(path: 'public/assets/backend/libs/firebase/firebase.min.js') }}"></script>
    <script src="{{ 'https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js' }}"></script>
    <script src="{{ 'https://www.gstatic.com/firebasejs/8.3.2/firebase-auth.js' }}"></script>
    <script src="{{ 'https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js' }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/backend/libs/firebase/firebase-init.js') }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/backend/libs/firebase/firebase-auth.js') }}"></script>

    <script>
        try {
            // List of topics to subscribe to
            const topics = {!! json_encode(getFCMTopicListToSubscribe()) !!};
            subscribeToNotificationTopics(topics);
        } catch (e) {
            console.warn(e);
        }
    </script>


    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/vendor-dark-mode.js') }}"></script>
</body>

</html>
