@extends('layouts.admin.app')

@section('title', translate('account_activation_center'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-3">
            <h2 class="h1 mb-1 text-capitalize">{{ translate('account_activation_center') }}</h2>
            <p class="mb-0 text-muted">{{ translate('activation_center_help_text') }}</p>
            <a class="btn btn-outline-primary mt-3" href="{{ route('admin.vendor-support.index') }}">{{ translate('vendor_support_conversations') }}</a>
        </div>

        @php
            $tabs = [
            'pending' => 'pending_activations',
            'customers' => 'customer_activations',
            'sellers' => 'seller_activations',
            'needs-information' => 'needs_information',
            'completed' => 'completed_activations',
            'rejected' => 'rejected_activations',
            ];
        @endphp
        <div class="card mb-3">
            <div class="card-body p-2">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($tabs as $key => $label)
                        <a class="btn {{ $tab === $key ? 'btn-primary' : 'btn-outline-primary' }}"
                           href="{{ route('admin.activation-center.index', ['tab' => $key]) }}">
                            {{ translate($label) }}
                            <span class="badge {{ $tab === $key ? 'badge-light' : 'badge-soft-primary' }} ms-1">{{ $counts[$key] ?? 0 }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="col-lg-5">
                        <label class="form-label">{{ translate('search') }}</label>
                        <input class="form-control" name="q" value="{{ request('q') }}"
                               placeholder="{{ translate('search_by_name_email_phone_or_cv_identifier') }}">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">{{ translate('assigned_employee') }}</label>
                        <select class="form-control" name="assigned_admin_id">
                            <option value="">{{ translate('all') }}</option>
                            @foreach($admins as $admin)
                                <option value="{{ $admin->id }}" @selected((string) request('assigned_admin_id') === (string) $admin->id)>{{ $admin->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">{{ translate('status') }}</label>
                        <select class="form-control" name="status">
                            <option value="">{{ translate('all') }}</option>
                            @foreach(['pending', 'in_review', 'needs_information', 'approved', 'rejected'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ translate($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit">{{ translate('filter') }}</button>
                        <a class="btn btn-outline-secondary" href="{{ route('admin.activation-center.index', ['tab' => $tab]) }}">{{ translate('reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>{{ translate('account') }}</th>
                        <th>{{ translate('account_type') }}</th>
                        <th>{{ translate('status') }}</th>
                        <th>{{ translate('assigned_employee') }}</th>
                        <th>{{ translate('last_update') }}</th>
                        <th class="text-center">{{ translate('action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($cases as $case)
                        @php
                            $subject = $case->subjectModel();
                            $prefix = $case->subject_type === 'seller' ? 'M' : 'C';
                        @endphp
                        <tr>
                            <td>{{ $cases->firstItem() + $loop->index }}</td>
                            <td>
                                <strong>{{ $prefix }}{{ $case->subject_id }}</strong>
                                <div>{{ trim(($subject?->f_name ?? '').' '.($subject?->l_name ?? '')) ?: ($subject?->name ?? translate('unknown')) }}</div>
                                <small class="text-muted">{{ $subject?->email ?? '-' }} &middot; {{ $subject?->phone ?? '-' }}</small>
                            </td>
                            <td><span class="badge badge-soft-info">{{ translate($case->subject_type) }}</span></td>
                            <td>
                                @php
                                    $statusClass = match($case->status) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'needs_information' => 'warning',
                                        default => 'info',
                                    };
                                @endphp
                                <span class="badge badge-soft-{{ $statusClass }}">{{ translate($case->status) }}</span>
                            </td>
                            <td>{{ $case->assignedAdmin?->name ?? translate('unassigned') }}</td>
                            <td>{{ $case->updated_at?->format('d M Y, h:i A') }}</td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center flex-wrap gap-1">
                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.activation-center.show', $case) }}">
                                        <i class="fi fi-rr-eye"></i> {{ translate('open_case') }}
                                    </a>
                                    @if($case->subject_type === 'seller' && $subject)
                                        <a class="btn btn-outline-secondary btn-sm" target="_blank" href="{{ route('admin.vendors.view', $subject->id) }}">
                                            <i class="fi fi-rr-store-alt"></i> {{ translate('seller_administrative_profile') }}
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">{{ translate('no_activation_cases_found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($cases->hasPages())
                <div class="card-footer d-flex justify-content-end">{{ $cases->links() }}</div>
            @endif
        </div>
    </div>
@endsection
