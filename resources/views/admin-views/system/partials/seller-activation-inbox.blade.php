<section class="admin-activation" aria-labelledby="activation-heading">
    <div class="admin-activation-heading"><div><h2 id="activation-heading">{{ translate('account_activation_center') }}</h2><p>{{ translate('workspace_activation_help') }}</p></div>@if($data['activation_enabled'])<a class="btn btn-outline-primary" href="{{ route('admin.activation-center.index') }}">{{ translate('view_all') }} <span>{{ $data['pending_activation_count'] }}</span></a>@endif</div>
    @if($data['activation_enabled'])
    <div class="admin-activation-shortcuts"><a href="{{ route('admin.activation-center.index', ['tab' => 'customers']) }}">{{ translate('customers') }}</a><a href="{{ route('admin.activation-center.index', ['tab' => 'sellers']) }}">{{ translate('vendors') }}</a></div>
    @forelse($data['recent_activation_cases'] as $case)
        @php
            $subject = $case->subject_type === 'seller' ? $case->seller : $case->customer;
        @endphp
        <a class="admin-activation-row" href="{{ route('admin.activation-center.show', $case) }}">
            <span class="admin-account-icon"><i class="fi fi-rr-{{ $case->subject_type === 'seller' ? 'shop' : 'user' }}" aria-hidden="true"></i></span>
            <span class="admin-account-identity"><strong>{{ trim(($subject?->f_name ?? '').' '.($subject?->l_name ?? '')) ?: $subject?->email }}</strong><span>{{ translate($case->subject_type === 'seller' ? 'vendor' : 'customer') }} · {{ $case->subject_type === 'seller' ? 'M' : 'C' }}{{ $case->subject_id }} · <bdi>{{ $subject?->phone ?: $subject?->email }}</bdi></span></span>
            <span class="admin-account-status">{{ translate($case->status) }}</span><time>{{ $case->updated_at?->diffForHumans() }}</time><span class="admin-review-action">{{ translate('review') }} <i class="fi fi-rr-angle-small-left" aria-hidden="true"></i></span>
        </a>
    @empty
        <p class="admin-empty">{{ translate('workspace_activation_empty') }}</p>
    @endforelse
    @else
        <p class="admin-empty">{{ translate('workspace_activation_disabled') }}</p>
    @endif
</section>
