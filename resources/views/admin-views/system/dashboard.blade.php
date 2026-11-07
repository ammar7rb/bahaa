@extends('layouts.admin.app')
@section('title', translate('dashboard'))
@section('content')
<div class="admin-dashboard"><div class="admin-page-heading"><h1>{{ translate('dashboard') }}</h1></div>
@if($summaryError ?? false)
<div class="alert alert-warning" role="alert">{{ translate('workspace_search_error') }} <a href="{{ route('admin.dashboard.index') }}">{{ translate('phase5_retry') }}</a></div>
@endif
<div class="admin-summary-grid">
@foreach($cards as [$key, $label, $icon, $value, $url, $definition])
@if($value !== null)
<a href="{{ $url }}" class="admin-summary-card" data-summary-kind="{{ $key }}"><span class="admin-summary-icon"><i class="fi fi-rr-{{ $icon }}" aria-hidden="true"></i></span><span class="admin-summary-copy"><span>{{ translate($label) }}</span><strong>{{ is_numeric($value) ? number_format($value) : $value }}</strong><small>{{ translate($definition) }}</small></span></a>
@else
<div class="admin-summary-card is-restricted"><span class="admin-summary-icon"><i class="fi fi-rr-lock" aria-hidden="true"></i></span><span class="admin-summary-copy"><span>{{ translate($label) }}</span><small>{{ translate('workspace_access_restricted') }}</small></span></div>
@endif
@endforeach
</div></div>
@endsection
