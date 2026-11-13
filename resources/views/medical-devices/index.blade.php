@extends(theme_base_path() === 'default' ? 'layouts.front-end.app' : 'theme-views.layouts.app')
@section('title', __('operations.medical_devices'))
@include('medical-devices._styles')
@section('content')
<main class="container medical-device-catalog"><h1>{{ __('operations.medical_devices') }}</h1><p class="mb-4">{{ __('operations.company_hint') }}</p>
@include('medical-devices._grid')</main>@endsection
