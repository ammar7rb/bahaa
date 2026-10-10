@extends('theme-views.layouts.app')
@section('title', translate('OTP_verification').' | '.$web_config['company_name'])
@section('content')
<main class="main-content"><section class="container"><div class="row justify-content-center"><div class="col-md-7 col-lg-5"><div class="card"><div class="card-body">
    <h1 class="h3 mb-3">{{ translate('OTP_verification') }}</h1>
    <p>{{ translate('we_have_sent_a_verification_code_to') }} <bdi>******{{ substr((string) ($phone ?? ''), -4) }}</bdi></p>
    <form action="{{ route('customer.auth.login.verify-account.submit') }}" method="POST">
        @csrf
        <input type="hidden" name="identity" value="{{ base64_encode((string) ($phone ?? '')) }}">
        <label for="care-verification-code" class="form-label">{{ translate('verification_code') }}</label>
        <input class="form-control mb-4" id="care-verification-code" name="token" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required dir="ltr">
        <button class="btn btn-primary w-100" type="submit">{{ translate('verify') }}</button>
    </form>
</div></div></div></div></section></main>
@endsection
