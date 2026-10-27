@php($detailId = 'pr-'.$review['type'].'-'.$review['id'])
<dialog class="pr-drawer" id="{{ $detailId }}" aria-labelledby="{{ $detailId }}-title">
    <header class="pr-drawer-heading"><div><h2 id="{{ $detailId }}-title">{{ __('payments.details') }}</h2><bdi>{{ $review['request_number'] }}</bdi></div><button type="button" class="btn btn-outline-secondary" data-pr-close>{{ __('payments.close') }}</button></header>
    <div class="pr-drawer-body">
        <section><h3>{{ __('payments.account') }}</h3><p>{{ trim(($review['actor']?->f_name ?? '').' '.($review['actor']?->l_name ?? '')) }} · {{ __('payments.'.$review['audience']) }} #{{ $review['actor']?->id }}<br><bdi>{{ $review['actor']?->phone }}</bdi><br>{{ $review['actor']?->email }}</p>
        @if($review['actor'] && \App\Utils\Helpers::module_permission_check('people'))<a href="{{ route($review['audience'] === 'vendor' ? 'admin.vendors.view' : 'admin.customer.view', $review['actor']->id) }}">{{ __('payments.profile') }}</a>@endif</section>
        <dl class="pr-fields">
            <div><dt>{{ __('payments.request_type') }}</dt><dd>{{ __('payments.'.$review['kind']) }} · {{ $review['label'] }}</dd></div>
            @if(!empty($review['destination']))<div><dt>{{ __('payments.destination') }}</dt><dd>{{ $review['destination'] }}</dd></div>@endif
            @if(!empty($review['purpose']))<div><dt>{{ __('payments.purpose') }}</dt><dd>{{ __('payments.intent_'.$review['purpose']) }}</dd></div>@endif
            <div><dt>{{ __('payments.amount') }}</dt><dd><bdi>{{ in_array($review['type'], ['customer_subscription','customer_legacy_receipt','vendor_extra_ad'], true) ? number_format($review['amount'],2).' '.$review['currency'] : setCurrencySymbol(amount: usdToDefaultCurrency(amount: $review['amount'])) }}</bdi></dd></div>
            @if(isset($review['submitted_amount']))<div><dt>{{ __('payments.submitted_amount') }}</dt><dd><bdi>{{ $review['submitted_amount'] }} {{ $review['currency'] }}</bdi></dd></div>@endif
            <div><dt>{{ __('payments.method') }}</dt><dd>{{ translatePaymentText($review['method']) }}</dd></div>
            <div><dt>{{ __('payments.reference') }}</dt><dd class="pr-reference-details"><bdi>{{ $review['reference'] ?: '—' }}</bdi>@if(!empty($review['reference']))<button type="button" class="pr-copy-button" data-pr-copy="{{ $review['reference'] }}" data-copied-label="{{ __('payments.copied') }}" data-copy-failed-label="{{ __('payments.copy_failed') }}" aria-label="{{ __('payments.copy') }} {{ __('payments.reference') }}" title="{{ __('payments.copy') }}"><i class="fi fi-rr-copy" aria-hidden="true"></i></button>@endif</dd></div>
            <div><dt>{{ __('payments.date') }}</dt><dd><bdi>{{ $review['created_at'] }}</bdi></dd></div>
            <div><dt>{{ __('payments.status') }}</dt><dd>{{ __('payments.'.$review['status']) }}</dd></div>
            @foreach(($review['offline'] ?? []) as $key=>$value)
                @if(!in_array($key,['method_id','method_name','private_retry_proof','retry_key','retry_history','payment_proof','payment_screenshot','submitted_at'],true) && !is_array($value) && !empty($value))<div><dt>{{ translatePaymentText($key) }}</dt><dd>{{ formatOrderPaymentInfoValue($value) }}</dd></div>@endif
                @if($key === 'method_information' && is_array($value))@foreach($value as $field=>$text)<div><dt>{{ translatePaymentText($field) }}</dt><dd>{{ formatOrderPaymentInfoValue($text) }}</dd></div>@endforeach
@endif
            @endforeach
            @if(!empty($review['note']))<div><dt>{{ __('payments.note') }}</dt><dd>{{ $review['note'] }}</dd></div>@endif
            @if(!empty($review['review_note']))<div><dt>{{ __('payments.review_result') }}</dt><dd>{{ $review['review_note'] }}</dd></div>@endif
            @if(!empty($review['reviewer']))<div><dt>{{ __('payments.reviewer') }}</dt><dd>{{ $review['reviewer'] }}</dd></div>@endif
            @if(!empty($review['reviewed_at']))<div><dt>{{ __('payments.reviewed_at') }}</dt><dd><bdi>{{ $review['reviewed_at'] }}</bdi></dd></div>@endif
        </dl>
        <p class="pr-copy-status" data-pr-copy-status role="status" aria-live="polite" hidden></p>
        @if($review['type'] === 'customer_initial_order')<p class="alert alert-info">{{ __('payments.confirmed') }}</p>@endif
        @if(!empty($review['customer_state']['message']))<p>{{ $review['customer_state']['message'] }}</p>@endif
        @if(isset($review['customer_state'])) @include('admin-views.order.partials._checkout-origin', ['checkoutState' => $review['customer_state']]) @endif
        @if(!empty($review['related_url']))<p><a href="{{ $review['related_url'] }}">{{ __('payments.related_order') }}</a></p>@endif
        <section class="pr-proofs"><h3>{{ __('payments.proof') }}</h3>
            @forelse($review['proof_urls'] as $proofUrl)
            <div class="pr-proof"><p data-pr-proof-loading role="status">{{ __('payments.loading') }}</p><img data-pr-proof-src="{{ $proofUrl }}" alt="{{ __('payments.proof') }}" hidden><p data-pr-proof-error hidden role="alert">{{ __('payments.proof_error') }}</p><a href="{{ $proofUrl }}" target="_blank" rel="noopener">{{ __('payments.proof_open') }} {{ $loop->iteration }}</a></div>
            @empty<p>{{ __('payments.no_proof') }}</p>@endforelse
        </section>
        @if($review['customer_state']['can_request_package'] ?? false)
            @if($review['customer_state']['package_request_sent'] ?? false)<p>{{ __('payments.package_sent') }}</p>
            @else<form method="post" action="{{ route('admin.payment-reviews.request-package',$review['id']) }}" data-pr-decision>@csrf<button class="btn btn--primary">{{ __('payments.package_request') }}</button></form>@endif
        @endif
        @if($review['type'] === 'vendor_balance_deposit' && $review['status'] === 'approved' && !($review['read_only'] ?? false))
            <details class="pr-decisions"><summary>{{ __('payments.reverse') }}</summary><form method="post" action="{{ route('admin.vendors.balance-deposits.reverse',$review['id']) }}" data-pr-decision>@csrf<label for="{{ $detailId }}-reverse">{{ __('payments.reverse_reason') }}</label><textarea id="{{ $detailId }}-reverse" name="reason" class="form-control" maxlength="1000" required rows="3"></textarea><button class="btn btn-outline-danger">{{ __('payments.reverse') }}</button></form></details>
        @endif
        @if($review['status'] === 'pending' && !($review['read_only'] ?? false))
            @php($extra = $review['type'] === 'vendor_extra_ad')
            <section class="pr-decisions"><h3>{{ __('payments.actions') }}</h3>
            <form method="post" action="{{ $extra ? route('admin.vendors.extra-ads.approve',$review['id']) : route('admin.payment-reviews.approve',[$review['type'],$review['id']]) }}" data-pr-decision>
                @csrf
                @if($review['type'] !== 'customer_balance_deposit')<label for="{{ $detailId }}-reference">{{ __('payments.transfer_reference') }}</label><input id="{{ $detailId }}-reference" name="payment_reference" maxlength="191" class="form-control" @required($extra)>@endif
                <label for="{{ $detailId }}-note">{{ __('payments.note') }}</label><textarea id="{{ $detailId }}-note" name="review_note" maxlength="1000" class="form-control" rows="2"></textarea>
                <button class="btn btn--primary" data-loading-label="{{ __('payments.loading') }}">{{ __('payments.'.($review['kind'] === 'balance_deposit' ? 'approve_deposit' : 'approve')) }}</button>
            </form>
            <form method="post" action="{{ $extra ? route('admin.vendors.extra-ads.reject',$review['id']) : route('admin.payment-reviews.reject',[$review['type'],$review['id']]) }}" data-pr-decision>
                @csrf<label for="{{ $detailId }}-reason">{{ __('payments.reason') }}</label><textarea id="{{ $detailId }}-reason" class="form-control" name="review_note" required minlength="{{ $extra ? 3 : 1 }}" maxlength="1000" rows="3"></textarea><button class="btn btn-outline-danger" data-loading-label="{{ __('payments.loading') }}">{{ __('payments.reject') }}</button>
            </form></section>
        @endif
    </div>
</dialog>
