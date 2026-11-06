@extends('theme-views.layouts.app')

@section('title', translate('Forgot_Password') . ' | ' . $web_config['company_name'] . ' ' . translate('ecommerce'))
@section('content')
    <main class="main-content d-flex flex-column gap-3 py-3 mb-sm-5">
        <div class="container">
            <div class="card">
                <div class="card-body py-5 px-lg-5">
                    <div class="row align-items-center pb-5">
                        <div class="col-lg-6 mb-5 mb-lg-0">
                            <h2 class="text-center mb-5 text-capitalize">
                                {{ translate('forget_password') }}
                            </h2>
                            <div class="d-flex justify-content-center">
                                <img width="283" class="dark-support" src="{{ theme_asset('assets/img/otp.png') }}"
                                    alt="{{ translate('image') }}">
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="d-flex justify-content-center mb-3">
                                <img width="50" class="dark-support" src="{{ theme_asset('assets/img/otp-lock.png') }}"
                                    alt="">
                            </div>
                            <p class="text-muted mx-w mx-auto text-center mb-4 width--18-75rem">
                                {{ translate('password_reset_support_message') }}
                            </p>
                            <div class="alert alert-info mx-auto mb-4 width--18-75rem" role="alert">
                                {{ translate('password_reset_support_identity_notice') }}
                            </div>
                            <div class="d-flex justify-content-center flex-wrap gap-3">
                                <a class="btn btn-outline-primary" href="{{ route('customer.auth.login') }}">
                                    {{ translate('back_to_login') }}
                                </a>
                                <a class="btn btn-primary px-sm-5"
                                   href="{{ route('contacts', ['subject' => translate('password_reset_support_subject')]) }}">
                                    {{ translate('contact_support') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
