@extends('theme-views.layouts.app')

@section('title', translate('sign_up'))

@section('content')
    <main class="container py-4 py-md-5" aria-label="{{ translate('sign_up') }}">
        @include('theme-views.layouts.partials.modal._register', ['inlineRegistration' => true])
    </main>
@endsection
