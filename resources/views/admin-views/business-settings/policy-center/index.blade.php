@extends('layouts.admin.app')

@section('content')
<div class="content container-fluid">
    <h2>{{ translate('policy_center') }}</h2>
    <p class="text-muted">{{ translate('create_a_new_version_instead_of_overwriting_an_accepted_policy') }}</p>
    <div class="card mb-3"><div class="card-body">
        <form method="post" action="{{ route('admin.business-settings.policy-center.store') }}">
            @csrf
            <div class="row"><div class="col-md-3 form-group"><label>{{ translate('policy_key') }}</label><input class="form-control" name="key" required placeholder="terms_and_conditions"></div><div class="col-md-5 form-group"><label>{{ translate('title') }}</label><input class="form-control" name="title" required></div><div class="col-md-4 form-group"><label>{{ translate('audience') }}</label><select class="form-control" name="audience"><option value="seller">{{ translate('seller') }}</option><option value="customer">{{ translate('customer') }}</option><option value="both">{{ translate('both') }}</option></select></div></div>
            <div class="row"><div class="col-md-8 form-group"><label>{{ translate('policy_content') }}</label><textarea class="form-control" rows="5" name="content" required></textarea></div><div class="col-md-4 form-group"><label>{{ translate('effective_date') }}</label><input class="form-control" type="datetime-local" name="effective_at"><div class="form-check mt-3"><input class="form-check-input" id="required-policy" type="checkbox" name="is_required" value="1" checked><label class="form-check-label" for="required-policy">{{ translate('required_for_registration') }}</label></div></div></div>
            <button class="btn btn-primary">{{ translate('create_new_version') }}</button>
        </form>
    </div></div>

    <div class="card"><div class="card-header"><h5 class="mb-0">{{ translate('policy_versions') }}</h5></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>{{ translate('title') }}</th><th>{{ translate('version') }}</th><th>{{ translate('audience') }}</th><th>{{ translate('effective_date') }}</th><th>{{ translate('required') }}</th><th>{{ translate('status') }}</th></tr></thead><tbody>@forelse($policies as $policy)<tr><td><strong>{{ $policy->title }}</strong><br><small>{{ $policy->key }}</small></td><td>v{{ $policy->version }}</td><td>{{ translate($policy->audience) }}</td><td>{{ $policy->effective_at?->format('Y-m-d H:i') ?? '—' }}</td><td>{{ $policy->is_required ? translate('yes') : translate('no') }}</td><td><form method="post" action="{{ route('admin.business-settings.policy-center.status',$policy->id) }}">@csrf<input type="hidden" name="is_active" value="{{ $policy->is_active ? 0 : 1 }}"><button class="btn btn-sm {{ $policy->is_active ? 'btn-success' : 'btn-secondary' }}">{{ $policy->is_active ? translate('active') : translate('inactive') }}</button></form></td></tr>@empty<tr><td colspan="6" class="text-center">{{ translate('no_policy_versions_found') }}</td></tr>@endforelse</tbody></table></div><div class="card-footer">{{ $policies->links() }}</div></div>

    <div class="card mt-3"><div class="card-header"><h5 class="mb-0">{{ translate('recent_acceptances') }}</h5></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>{{ translate('policy') }}</th><th>{{ translate('subject') }}</th><th>{{ translate('accepted_at') }}</th></tr></thead><tbody>@forelse($acceptances as $acceptance)<tr><td>{{ $acceptance->policyVersion?->title }} (v{{ $acceptance->policyVersion?->version }})</td><td>{{ $acceptance->subject_type === 'customer' ? 'C' : ($acceptance->subject_type === 'seller' ? 'M' : '#') }}{{ $acceptance->subject_id }}</td><td>{{ $acceptance->accepted_at?->format('Y-m-d H:i') }}</td></tr>@empty<tr><td colspan="3" class="text-center">{{ translate('no_acceptances_found') }}</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
