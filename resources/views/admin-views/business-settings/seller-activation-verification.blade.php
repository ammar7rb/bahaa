@extends('layouts.admin.app')

@section('content')
<div class="content container-fluid">
    <h2>{{ translate('seller_activation_configuration') }}</h2>
    <p class="text-muted">{{ translate('changes_apply_to_new_registrations') }}</p>
    <form method="post" action="{{ route('admin.business-settings.seller-activation-verification.update') }}">
        @csrf
        <div class="card"><div class="card-body">
            <div class="form-group"><label>{{ translate('activation_method') }}</label><select class="form-control" name="mode"><option value="none" @selected($mode==='none')>{{ translate('no_additional_verification') }}</option><option value="otp_only" @selected($mode==='otp_only')>{{ translate('otp_only') }}</option><option value="support_ticket_only" @selected($mode==='support_ticket_only')>{{ translate('support_ticket_only') }}</option><option value="otp_and_support_ticket" @selected($mode==='otp_and_support_ticket')>{{ translate('otp_and_support_ticket') }}</option></select></div>
            <div class="row"><div class="col-md-4 form-group"><label>{{ translate('otp_ttl_minutes') }}</label><input class="form-control" type="number" min="1" max="60" name="otp_ttl_minutes" value="{{ old('otp_ttl_minutes', $otpSettings['ttl_minutes']) }}"></div><div class="col-md-4 form-group"><label>{{ translate('otp_attempts') }}</label><input class="form-control" type="number" min="1" max="20" name="otp_max_attempts" value="{{ old('otp_max_attempts', $otpSettings['max_attempts']) }}"></div><div class="col-md-4 form-group"><label>{{ translate('otp_resend_seconds') }}</label><input class="form-control" type="number" min="10" max="3600" name="otp_resend_seconds" value="{{ old('otp_resend_seconds', $otpSettings['resend_seconds']) }}"></div></div>
            <div class="form-group"><label>{{ translate('automatic_activation_ticket_message') }}</label><textarea class="form-control" name="automatic_message" rows="3" maxlength="1000">{{ old('automatic_message', $automaticMessage) }}</textarea></div>
            <button class="btn btn-primary">{{ translate('save_settings') }}</button>
        </div></div>
    </form>
</div>
@endsection
