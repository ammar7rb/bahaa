@extends('theme-views.layouts.app')

@section('title', translate('vendor_Apply').' | '.$web_config['company_name'].' '.translate('ecommerce'))

@push('css_or_js')
    @include('layouts.vendor.partials._dark-mode-preference')
    <link rel="stylesheet" href="{{ theme_asset(path: 'assets/plugins/daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/vendor-dark-mode.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/front-end/css/vendor-registration-dark-mode.css') }}?v=20260826.2">
@endpush

@section('content')
    <main class="main-content d-flex flex-column gap-3 py-3 mb-sm-5 seller-registration-page">
        @include('layouts.vendor.partials._dark-mode-toggle')
        <form id="seller-registration" action="{{route('vendor.auth.registration.add') }}" method="POST" class="form-advance-validation form-advance-file-validation"
              enctype="multipart/form-data">
            @csrf
            <div class="first-el">
                <section>
                    <div class="container">
                        <div class="create-an-account p-3 p-sm-4">
                            <img src="{{ dynamicAsset(path: 'public/assets/front-end/img/media/form-bg.png') }}" alt=""
                                 class="create-an-account-bg-img">
                            <div class="row">
                                @include('theme-views.seller-views.auth.partial.header')
                                <div class="col-lg-8">
                                    <div class="bg-white p-3 p-sm-4 rounded">
                                        <h4 class="mb-4">{{ translate('Create_an_Account') }}</h4>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="form-group mb-4">
                                                    <label for="email">
                                                        {{ translate('email') }}
                                                        <span class="text-danger">*</span>
                                                        <span class="text-danger fs-12 mail-error"></span>
                                                    </label>
                                                    <input class="form-control" type="email" id="email" name="email"
                                                           placeholder="{{ translate('Ex: example@gmail.com') }}"
                                                           required>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-group mb-4">
                                                    <label for="tel">
                                                        {{ translate('phone') }}
                                                        <span class="text-danger">*</span>
                                                        <span class="text-danger fs-12 phone-error"></span>
                                                    </label>
                                                    <div>
                                                        <div class="input-group">
                                                            <span class="input-group-text" dir="ltr">+20</span>
                                                            <input id="vendor-registration-phone"
                                                                class="form-control form-control-user" type="tel"
                                                                name="phone_local" inputmode="numeric" autocomplete="tel-national"
                                                                maxlength="11" placeholder="01xxxxxxxxx"
                                                                data-egypt-phone
                                                                aria-label="{{ translate('enter_phone_number') }}" required>
                                                            <input id="vendor-registration-phone-full" type="hidden" name="phone">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-group mb-4">
                                                    <label for="password">
                                                        {{ translate('password') }}
                                                        <span class="text-danger">*</span>
                                                        <span class="text-danger fs-12 password-error"></span>
                                                    </label>
                                                    <div class="input-inner-end-ele">
                                                        <input class="form-control password-check" type="password"
                                                               id="password" name="password"
                                                               value="{{old('password') }}"
                                                               placeholder="{{ translate('enter_password') }}" required>
                                                        <i class="bi bi-eye-slash-fill togglePassword"></i>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-group mb-4">
                                                    <label for="confirm_password">
                                                        {{ translate('confirm_password') }}
                                                        <span class="text-danger">*</span>
                                                        <span class="text-danger fs-12 confirm_password-error"></span>
                                                    </label>
                                                    <div class="input-inner-end-ele">
                                                        <input class="form-control" type="password"
                                                               id="confirm_password" name="confirm_password"
                                                               placeholder="{{ translate('confirm_password') }}"
                                                               required>
                                                        <i class="bi bi-eye-slash-fill togglePassword"></i>
                                                    </div>
                                                    <small class="text-danger confirm-password-error"></small>
                                                </div>
                                            </div>
                                            @if($requiredSellerPolicies->isNotEmpty())
                                                <div class="col-12">
                                                    <div class="mb-4 border rounded p-3">
                                                        @foreach($requiredSellerPolicies as $policy)
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input" type="checkbox"
                                                                    id="seller-policy-{{ $policy->id }}"
                                                                    name="policy_version_ids[]" value="{{ $policy->id }}" required>
                                                                <label class="form-check-label" for="seller-policy-{{ $policy->id }}">
                                                                    {{ translate('i_agree_with_the') }} {{ $policy->title }} (v{{ $policy->version }})
                                                                </label>
                                                                <details class="mt-1 text-muted fs-12">
                                                                    <summary>{{ $policy->title }}</summary>
                                                                    <div class="mt-1">{!! nl2br(e($policy->content)) !!}</div>
                                                                </details>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="col-12">
                                                <div class="d-flex justify-content-end">
                                                    <button type="button" class="btn btn-primary proceed-to-next-btn">
                                                        {{ translate('Proceed_to_next') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                @include('theme-views.seller-views.auth.partial.why-with-us')
                @include('theme-views.seller-views.auth.partial.business-process')
                @include('theme-views.seller-views.auth.partial.download-app')
                @include('theme-views.seller-views.auth.partial.faq')
            </div>

            {{-- Keep seller and shop details inside the registration form. --}}
            @include('theme-views.seller-views.auth.partial.vendor-information-form')

        </form>

        <div class="modal fade registration-success-modal" tabindex="-1" aria-labelledby="toggle-modal"
             aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg">
                    <div class="modal-header border-0 pb-0 d-flex justify-content-end">
                        <button type="button" class="btn-close border-0" data-dismiss="modal" aria-label="Close"><i
                                class="tio-clear"></i></button>
                    </div>
                    <div class="modal-body px-4 px-sm-5 pt-0">
                        <div class="d-flex flex-column align-items-center text-center gap-2 mb-2">
                            <img src="{{ theme_asset('assets/img/congratulations.png') }}" width="70" class="mb-3 mb-20"
                                 alt="">
                            <h5 class="modal-title">{{ translate('congratulations') }}</h5>
                            <div class="text-center">
                                {{ translate('your_registration_is_successful') . '. ' . translate('Please_verify_your_phone') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <span id="get-confirm-and-cancel-button-text" data-sure="{{ translate('are_you_sure').'?'}}"
              data-message="{{ translate('want_to_apply_as_a_vendor').'?'}}"
              data-confirm="{{ translate('yes') }}" data-cancel="{{ translate('no') }}"></span>
        <span id="registration-availability-endpoint" data-url="{{ route('vendor.auth.registration.check-availability') }}"></span>
        <span id="proceed-to-next-validation-message"
              data-mail-error="{{ translate('please_enter_your_email').'.'}}"
              data-phone-error="{{ translate('please_enter_your_phone_number').'.'}}"
              data-invalid-phone="{{ translate('please_ensure_your_phone_number_is_valid_and_does_not_exceed_20_characters').'.'}}"
              data-valid-mail="{{ translate('please_enter_a_valid_email_address').'.'}}"
              data-enter-password="{{ translate('please_enter_your_password').'.'}}"
              data-enter-confirm-password="{{ translate('please_enter_your_confirm_password').'.'}}"
              data-password-not-match="{{ translate('passwords_do_not_match').'.'}}"
              data-availability-error="{{ translate('something_went_wrong!') }}"
        >

    </span>
    </main>
@endsection
@push('script')
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/vendor-dark-mode.js') }}"></script>
    <script>
        $('#vendor-apply-submit').on('click', function(e) {
            e.preventDefault();
            submitRegistration();
        });
    </script>

    <script src="{{ theme_asset('assets/js/vendor-registration.js') }}?v={{ filemtime(base_path('resources/themes/theme_aster/public/assets/js/vendor-registration.js')) }}"></script>
    <script src="{{theme_asset('assets/js/password-strength.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/min/moment.min.js"></script>
    <script src="{{theme_asset('assets/plugins/daterangepicker/daterangepicker.min.js') }}"></script>
    <script src="{{ theme_asset('assets/js/file-upload/pdf.min.js') }}"></script>
    <script src="{{ theme_asset('assets/js/file-upload/pdf-worker.min.js') }}"></script>
    <script src="{{ theme_asset('assets/js/file-upload/multiple-document-upload.js') }}"></script>
    <script>
        $(document).ready(function () {
            $(".js-daterangepicker_single-date-with-placeholder").daterangepicker({
                singleDatePicker: true,
                autoUpdateInput: false,
                locale: {
                    cancelLabel: 'Clear'
                }
            });
            $(".js-daterangepicker_single-date-with-placeholder").on('apply.daterangepicker', function (ev, picker) {
                $(this).val(picker.startDate.format('MM/DD/YYYY'));
            });

            $(".js-daterangepicker_single-date-with-placeholder").on('cancel.daterangepicker', function (ev, picker) {
                $(this).val('');
            });

            function checkPasswordMatch() {
                const password = $('#password').val();
                const confirmPassword = $('#confirm_password').val();

                if (confirmPassword.length > 0 && password !== confirmPassword) {
                    $('.confirm-password-error').text('Password and confirm password does not match.');
                } else {
                    $('.confirm-password-error').text('');
                }

                $('.password-error').text('');
                $('.confirm_password-error').text('');
            }
            $('#user_password, #confirm_password').on('keyup change', function () {
                checkPasswordMatch();
            });
        });

    </script>
@endpush
