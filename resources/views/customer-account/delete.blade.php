@extends('layouts.front-end.app')
@section('title', translate('delete_account'))
@section('content')
@php($deletionArabic = getLanguageCode(country_code: getDefaultLanguage()) === 'ar')
<main class="container py-5">
    <h1>{{ $deletionArabic ? 'حذف الحساب' : 'Delete account' }}</h1>
    @if($errors->any())<div role="alert" class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <p>{{ $deletionArabic ? 'هل أنت متأكد من حذف حسابك؟ بعد التأكيد سيتم إغلاق الحساب وتسجيل خروجك.' : 'Are you sure you want to delete your account? Confirming will close your account and sign you out.' }}</p>
    <form method="post" action="{{ route('customer.deletion.confirm') }}">
        @csrf
        <button class="btn btn-danger mt-3" type="submit" name="confirmation" value="1">{{ $deletionArabic ? 'نعم، حذف الحساب' : 'Yes, delete account' }}</button>
        <a class="btn btn-link mt-3" href="{{ route('user-profile') }}">{{ $deletionArabic ? 'إلغاء' : 'Cancel' }}</a>
    </form>
</main>
@endsection
