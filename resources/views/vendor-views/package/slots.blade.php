@extends('layouts.vendor.app')
@section('title', translate('phase5_spaces'))
@section('content')
@include('vendor-views.package.partials.slots-content')
@endsection
@push('script')
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/vendor-extra-ads.js') }}" defer></script>
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/vendor-advertising-workspace.js') }}?v={{ filemtime(public_path('assets/back-end/js/vendor-advertising-workspace.js')) }}" defer></script>
@endpush
