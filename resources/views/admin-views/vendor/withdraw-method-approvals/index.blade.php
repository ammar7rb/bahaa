@extends('layouts.admin.app')

@section('title', translate('withdrawal_method_approvals'))

@section('content')
<div class="content container-fluid">
    <div class="mb-3"><h2 class="h1 mb-0">{{ translate('withdrawal_method_approvals') }}</h2><p class="text-muted">{{ translate('withdrawal_method_approval_note') }}</p></div>
    <div class="card">
        <div class="card-body">
            <form method="get" class="row g-2 mb-3">
                <div class="col-md-5"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="{{ translate('search_seller_or_method') }}"></div>
                <div class="col-md-3"><select class="form-select" name="status"><option value="">{{ translate('all_statuses') }}</option>@foreach(['pending','approved','rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ translate($status) }}</option>@endforeach</select></div>
                <div class="col-md-2"><button class="btn btn--primary w-100">{{ translate('filter') }}</button></div>
            </form>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>{{ translate('seller') }}</th><th>{{ translate('method') }}</th><th>{{ translate('status') }}</th><th>{{ translate('submitted_at') }}</th><th>{{ translate('decision') }}</th></tr></thead><tbody>
            @forelse($methods as $method)
                <tr><td>{{ $method->seller?->email ?? '#' . $method->user_id }}</td><td>{{ $method->method_name ?: $method->withdraw_method?->method_name }}<br><small class="text-muted">{{ $method->method_type }}</small></td><td><span class="badge {{ $method->approval_status === 'approved' ? 'bg-success' : ($method->approval_status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ translate($method->approval_status) }}</span>@if($method->approval_reason)<br><small>{{ $method->approval_reason }}</small>@endif</td><td>{{ optional($method->submitted_at)->format('Y-m-d H:i') }}</td><td>@if($method->approval_status === 'pending')<div class="d-flex gap-2"><form method="post" action="{{ route('admin.vendors.withdraw-method-approvals.decision', $method->id) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">{{ translate('approve') }}</button></form><form method="post" action="{{ route('admin.vendors.withdraw-method-approvals.decision', $method->id) }}" class="d-flex gap-1">@csrf<input type="hidden" name="decision" value="reject"><input class="form-control form-control-sm" name="reason" required placeholder="{{ translate('reason') }}"><button class="btn btn-sm btn-danger">{{ translate('reject') }}</button></form></div>@else—@endif</td></tr>
            @empty <tr><td colspan="5" class="text-center text-muted">{{ translate('no_withdrawal_methods_found') }}</td></tr>@endforelse
            </tbody></table></div><div class="d-flex justify-content-end">{{ $methods->links() }}</div>
        </div>
    </div>
</div>
@endsection
