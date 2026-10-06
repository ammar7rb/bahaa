@extends('layouts.vendor.app')
@section('title', translate('vendor_account'))
@section('content')
<div class="content container-fluid vendor-account">
    <div class="vendor-page-heading"><h1>{{ translate('vendor_account') }}</h1>
        <nav class="vendor-account-links" aria-label="{{ translate('vendor_account') }}">
            <a href="{{ route('vendor.messages') }}">{{ translate('administration_messages') }}</a>
            <a href="{{ route('vendor.notifications') }}">{{ translate('notifications') }}</a>
        </nav>
    </div>
    @if(session('account_saved'))<div class="alert alert-success" role="status">{{ translate('profile_updated_successfully') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
    <form method="post" action="{{ route('vendor.account.update') }}" enctype="multipart/form-data" class="card">@csrf
        <div class="card-body">
            <div class="vendor-account-avatar">
                <img src="{{ getStorageImages(path: $data['image'], type: 'backend-profile') }}" alt="{{ translate('profile_picture') }}" width="80" height="80">
                <div><label for="account-image">{{ translate('profile_picture') }}</label><input class="form-control" type="file" id="account-image" name="image" accept="image/jpeg,image/png,image/webp"><small>{{ translate('vendor_document_limit') }}</small></div>
            </div>
            <div class="row">
                @foreach(['f_name' => 'first_Name', 'l_name' => 'last_Name', 'store_name' => 'store_name', 'email' => 'email'] as $key => $label)
                <div class="col-md-6 form-group"><label for="account-{{ $key }}">{{ translate($label) }}</label><input class="form-control" id="account-{{ $key }}" name="{{ $key }}" type="{{ $key === 'email' ? 'email' : 'text' }}" value="{{ old($key, $data[$key]) }}" required maxlength="191"></div>
                @endforeach
                <div class="col-md-6 form-group"><label for="account-id">{{ translate('vendor_account_id') }}</label><input class="form-control" id="account-id" value="{{ $data['account_id'] }}" readonly dir="ltr"></div>
            </div>
            @foreach(['phone_numbers' => 'vendor_phone_numbers', 'store_addresses' => 'vendor_store_addresses'] as $key => $label)
                <fieldset class="vendor-account-group"><legend>{{ translate($label) }}</legend>
                    <p class="text-muted">{{ translate('vendor_primary_contact_hint') }}</p>
                    <div data-repeat-list="{{ $key }}">
                        @foreach(old($key, $data[$key]) as $value)
                        <div class="vendor-repeat-row"><label class="sr-only">{{ translate($label) }} {{ $loop->iteration }}</label><input class="form-control" aria-label="{{ translate($label) }} {{ $loop->iteration }}" name="{{ $key }}[]" value="{{ $value }}" type="{{ $key === 'phone_numbers' ? 'tel' : 'text' }}" maxlength="{{ $key === 'phone_numbers' ? 20 : 1000 }}" required><button type="button" class="btn btn-outline-danger" data-repeat-remove>{{ translate('delete') }}</button></div>
                        @endforeach
                    </div>
                    <template data-repeat-template="{{ $key }}"><div class="vendor-repeat-row"><input class="form-control" aria-label="{{ translate($label) }}" name="{{ $key }}[]" type="{{ $key === 'phone_numbers' ? 'tel' : 'text' }}" maxlength="{{ $key === 'phone_numbers' ? 20 : 1000 }}" required><button type="button" class="btn btn-outline-danger" data-repeat-remove>{{ translate('delete') }}</button></div></template>
                    <button type="button" class="btn btn-outline-primary" data-repeat-add="{{ $key }}">{{ translate('add') }}</button>
                </fieldset>
            @endforeach
            <div class="row">
            @foreach(['commercial' => 'commercial_registration', 'tax' => 'tax_card'] as $kind => $key)
                <fieldset class="col-md-6 vendor-account-group"><legend>{{ translate($key) }}</legend>
                    <label for="account-{{ $key }}">{{ translate('vendor_document_manual') }}</label><textarea id="account-{{ $key }}" class="form-control mb-3" name="{{ $key }}" rows="3" maxlength="5000">{{ old($key, $data[$key]) }}</textarea>
                    <label for="account-{{ $kind }}-document">{{ translate('vendor_document_upload') }}</label><input id="account-{{ $kind }}-document" class="form-control" type="file" name="{{ $kind }}_document" accept="image/jpeg,image/png,image/webp">
                    <small class="d-block">{{ translate('vendor_document_limit') }}</small>
                    @if($data[$kind.'_document_uploaded'])<a href="{{ route('vendor.account.document', $kind) }}" target="_blank" rel="noopener">{{ translate('view_uploaded_document') }}</a>@endif
                </fieldset>
            @endforeach
            </div>
            <button class="btn btn--primary" type="submit">{{ translate('save_Changes') }}</button>
        </div>
    </form>
    <section class="card mt-4"><div class="card-body"><h2>{{ translate('change_Password') }}</h2>
        <form method="post" action="{{ route('vendor.account.password') }}">@csrf
            <div class="row">
            @foreach(['current_password', 'password', 'password_confirmation'] as $key)
                <div class="col-md-4 form-group"><label for="account-{{ $key }}">{{ translate($key === 'password' ? 'new_password' : $key) }}</label><input id="account-{{ $key }}" class="form-control" type="password" name="{{ $key }}" autocomplete="{{ $key === 'current_password' ? 'current-password' : 'new-password' }}" required></div>
            @endforeach
            </div><p class="text-muted">{{ translate('vendor_password_hint') }}</p>
            <button type="submit" class="btn btn--primary">{{ translate('change_Password') }}</button>
        </form>
    </div></section>
</div>
@endsection
