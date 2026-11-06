@extends('layouts.front-end.app')
@section('title','تم تفعيل الحساب')
@push('css_or_js')
<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-support-chat.css') }}?v=20261016.5">
@endpush
@section('content')
<main class="container support-chat-page" dir="rtl">
    @include('customer-support.activation-result',['redirectUrl'=>route('home')])
</main>
@endsection
@push('script')
<script>window.setTimeout(function(){window.location.replace(@json(route('home')));},2200);</script>
@endpush
