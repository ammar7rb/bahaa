@extends('layouts.front-end.app')

@section('title', translate('register'))

@push('css_or_js')
    <link rel="stylesheet"
        href="{{ theme_asset(path: 'public/assets/front-end/plugin/intl-tel-input/css/intlTelInput.css') }}">
@endpush

@push('css_or_js')
    <style>
        /* Phone numbers remain LTR: keep the country selector on the left in both languages. */
        [dir="rtl"] #customer-register-form .iti input.iti__tel-input {
            padding-right: 16px !important;
            padding-left: 100px !important;
            direction: ltr !important;
            text-align: left !important;
        }

        [dir="rtl"] #customer-register-form .iti input.iti__tel-input::placeholder {
            direction: rtl;
            text-align: right;
        }

        [dir="rtl"] #customer-register-form .iti__flag-container {
            right: auto !important;
            left: 0 !important;
        }

        [dir="rtl"] #customer-register-form .iti__selected-flag,
        [dir="rtl"] #customer-register-form .iti__selected-dial-code {
            direction: ltr;
            unicode-bidi: isolate;
        }

        /* Bootstrap's validation glyph clashes with the country selector. Keep border validation only. */
        #customer-register-form .iti input.iti__tel-input:valid,
        #customer-register-form .iti input.iti__tel-input:invalid,
        #customer-register-form .iti input.iti__tel-input.is-valid,
        #customer-register-form .iti input.iti__tel-input.is-invalid {
            background-image: none !important;
        }
    </style>
@endpush

@section('content')
    <div class="container py-4 __inline-7 text-align-direction">
        <div class="login-card">
            <div class="mx-auto __max-w-760">
                <h2 class="text-center h4 mb-4 font-bold text-capitalize fs-18-mobile">{{ translate('sign_up') }}</h2>
                <form class="needs-validation_" id="customer-register-form" action="{{ route('customer.auth.sign-up') }}"
                    method="post">
                    @include('customer-account.registration-fields')
                </form>
            </div>
        </div>
    </div>
@endsection
