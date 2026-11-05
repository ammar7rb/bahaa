@extends(in_array(theme_root_path(), ['theme_aster', 'theme_sigma_care'], true) ? 'theme-views.layouts.app' : 'layouts.front-end.app')
@section('title', translate('settings'))
@section('content')
<main class="container py-5">
    <div class="row g-4">
        @includeFirst(['theme-views.partials._profile-aside', 'web-views.partials._profile-aside'])
        <section class="col-lg-9">
            <h1>{{ translate('settings') }}</h1>
            @if(session('settings_status'))<p class="alert alert-success" role="status">{{ session('settings_status') }}</p>@endif
            @if($errors->any())<p class="alert alert-danger" role="alert">{{ $errors->first() }}</p>@endif
            <section class="card card-body mb-4">
                <h2 class="h4">{{ translate('change_password') }}</h2>
                <form method="post" action="{{ route('customer.settings.password') }}">
                    @csrf
                    @foreach(['current_password' => 'current_password', 'password' => 'new_password', 'password_confirmation' => 'confirm_password'] as $field => $label)
                        <label for="settings-{{ $field }}">{{ translate($label) }} *</label>
                        <input class="form-control mb-3" id="settings-{{ $field }}" name="{{ $field }}" type="password" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" maxlength="255" required>
                    @endforeach
                    <button class="btn btn-primary" type="submit">{{ translate('save') }}</button>
                    <a class="btn btn-link" href="{{ route('customer.auth.recover-password') }}">{{ translate('forgot_password') }}</a>
                </form>
            </section>
            <section class="card card-body mb-4">
                <h2 class="h4">{{ translate('language') }}</h2>
                <ul class="list-unstyled mb-0">
                    @foreach($web_config['language'] as $language)
                        @if($language['status'] == 1)
                            <li class="change-language" data-action="{{ route('change-language') }}" data-language-code="{{ $language['code'] }}">
                                <button class="btn btn-link" type="button" lang="{{ $language['code'] }}" @if($language['code'] === getLanguageCode(country_code:getDefaultLanguage())) aria-current="true" @endif>{{ $language['name'] }}</button>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </section>
<p><a class="btn btn-outline-primary" href="{{ route('customer.auth.logout') }}">{{ translate('logout') }}</a></p>
            <a class="btn btn-outline-danger" href="{{ route('customer.deletion.show') }}">{{ translate('delete_account') }}</a>
        </section>
    </div>
</main>
@endsection
