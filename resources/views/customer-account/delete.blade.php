@extends(in_array(theme_root_path(), ['theme_aster', 'theme_sigma_care'], true) ? 'theme-views.layouts.app' : 'layouts.front-end.app')
@section('title', translate('delete_account'))
@section('content')
@php($stage = $stage ?? 1)
<main class="container py-5">
    <h1>{{ translate('delete_account') }}</h1>
    @if($errors->any())<div role="alert" class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route(match($stage) { 2 => 'customer.deletion.reason', 3 => 'customer.deletion.finish', default => 'customer.deletion.begin' }) }}">
        @csrf
        @if($stage > 1)<input type="hidden" name="flow_token" value="{{ $token }}">@endif
        @if($stage === 2)
            <label for="deletion-reason">سبب حذف الحساب (150 حرفًا على الأقل)</label>
            <textarea class="form-control mb-3" id="deletion-reason" name="reason" minlength="150" maxlength="5000" required>{{ old('reason') }}</textarea>
        @else
            <label for="deletion-password">{{ translate('current_password') }}</label>
            <input class="form-control mb-3" id="deletion-password" name="password" type="password" autocomplete="current-password" maxlength="128" required>
        @endif
        @if($stage === 3)
            <label class="d-block mb-3"><input type="checkbox" name="confirmation" value="1" required> أؤكد إغلاق حسابي وتسجيل الخروج.</label>
        @endif
        <button class="btn {{ $stage === 3 ? 'btn-danger' : 'btn-primary' }}" type="submit">{{ $stage === 3 ? translate('delete_account') : translate('continue') }}</button>
        <a class="btn btn-link" href="{{ route('customer.settings') }}">{{ translate('cancel') }}</a>
    </form>
</main>
@endsection
