@extends('layouts.front-end.app')

@section('title', translate('forgot_Password'))
@section('content')
    <div class="container py-4 py-lg-5 my-4 rtl">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10 text-start">
                <h2 class="h3 mb-4">{{ translate('forgot_your_password') }}?</h2>
                <p class="font-size-md">{{ translate('password_reset_support_message') }}</p>
                <div class="alert alert-info mt-4" role="alert">
                    {{ translate('password_reset_support_identity_notice') }}
                </div>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a class="btn btn-outline-primary" href="{{ route('customer.auth.login') }}">{{ translate('back_to_login') }}</a>
                    <a class="btn btn--primary" href="{{ route('contacts', ['subject' => translate('password_reset_support_subject')]) }}">{{ translate('contact_support') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection
