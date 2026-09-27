@if(($data['pending_activation_count'] ?? 0) > 0)
<div class="card mb-3 border-warning remove-card-shadow">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><h4 class="mb-1">{{ translate('account_activation_center') }}</h4><small class="text-muted">{{ translate('seller_activation_inbox_help') }}</small></div>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.activation-center.index') }}">{{ translate('view_all') }} ({{ $data['pending_activation_count'] }})</a>
    </div>
    <div class="list-group list-group-flush">
        @foreach($data['recent_activation_cases'] as $case)
            @php($subject = $case->subject_type === \App\Models\AccountActivationCase::SUBJECT_SELLER ? $case->seller : $case->customer)
            <a class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between align-items-center gap-2" href="{{ route('admin.activation-center.show', $case) }}">
                <span><strong>{{ $case->subject_type === 'seller' ? 'V' : 'C' }}{{ $case->subject_id }}</strong> · {{ $subject?->email }}<small class="d-block text-muted">{{ $case->updated_at?->diffForHumans() }}</small></span>
                <span class="badge badge-soft-warning">{{ translate($case->status) }}</span>
            </a>
        @endforeach
    </div>
</div>
@endif
