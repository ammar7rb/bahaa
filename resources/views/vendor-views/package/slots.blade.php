@extends('layouts.vendor.app')
@section('title', translate('phase5_spaces'))
@section('content')
<div class="content container-fluid pt-3"><a class="btn btn--primary" href="{{ route('vendor.products.add') }}"><i class="fi fi-rr-plus" aria-hidden="true"></i> إضافة منتج</a></div>
@include('vendor-views.package.partials.slots-content')
@endsection
@push('script')
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/vendor-extra-ads.js') }}" defer></script>
<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/vendor-advertising-workspace.js') }}?v={{ filemtime(public_path('assets/back-end/js/vendor-advertising-workspace.js')) }}" defer></script>
@endpush
