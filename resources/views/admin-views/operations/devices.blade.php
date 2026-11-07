@extends('layouts.admin.app')
@section('title', __('operations.medical_devices'))
@section('content')
<div class="content container-fluid operations-workspace">
@include('admin-views.operations._feedback')
<div class="operations-toolbar"><div><h1 class="h3">{{ __('operations.medical_devices') }}</h1><p class="mb-0 operations-muted">{{ __('operations.company_hint') }}</p></div><a class="btn btn-primary" href="{{ route('admin.operations.medical-devices.create') }}">{{ __('operations.create_device') }}</a></div>
<nav class="nav nav-tabs mb-4"><a class="nav-link active" href="{{ route('admin.operations.medical-devices.index') }}">{{ __('operations.medical_devices') }}</a>@if(\App\Utils\Helpers::module_permission_check('people'))<a class="nav-link" href="{{ route('admin.operations.medical-devices.inquiries') }}">{{ __('operations.inquiries') }}</a>@endif<a class="nav-link" href="{{ route('medical-devices.index') }}">{{ __('operations.overview') }}</a></nav>
<div class="card"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>{{ __('operations.device') }}</th><th>{{ __('operations.status') }}</th><th>{{ __('operations.order') }}</th><th>{{ __('operations.edit') }}</th></tr></thead><tbody>
@forelse($devices as $device)<tr><td><div class="d-flex align-items-center gap-3"><img class="operations-thumb" src="{{ $device->image_url }}" alt=""><strong>{{ $device->name }}</strong></div></td><td>{{ __('operations.'.($device->is_published ? 'published_label' : 'hidden')) }}</td><td>{{ $device->sort_order }}</td><td><div class="d-flex gap-2"><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.operations.medical-devices.edit', $device) }}">{{ __('operations.edit') }}</a><form method="post" action="{{ route('admin.operations.medical-devices.delete', $device) }}" data-operations-form data-confirm="{{ __('operations.confirm_remove') }}">@csrf @method('delete')<button type="submit" class="btn btn-outline-danger btn-sm">{{ __('operations.remove') }}</button></form></div></td></tr>
@empty<tr><td colspan="4" class="text-center py-5">{{ __('operations.empty_devices') }}</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $devices->links() }}</div>
</div>@endsection
