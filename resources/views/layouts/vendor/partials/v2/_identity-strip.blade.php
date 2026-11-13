@php($seller = auth('seller')->user())
@if($seller)
    @php($svRef = $seller->administrative_reference ?? $seller->id)
    <div class="vendor-identity-strip d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="vendor-identity-person">
            @include('layouts.vendor.partials.v2._avatar', ['svSeller' => $seller, 'svSize' => 'lg'])
            <div class="vendor-identity-details">
                <div class="vendor-identity-name"><strong>{{ trim(($seller->f_name ?? '').' '.($seller->l_name ?? '')) }}</strong></div>
                <div class="vendor-reference-row">
                    <span class="small text-muted">{{ translate('vendor_account_id') }}:</span>
                    <bdi dir="ltr">{{ $svRef }}</bdi>
                    <button type="button" class="vendor-reference-copy" data-sv-copy="{{ $svRef }}" aria-label="{{ translate('copy_account_id') }}" title="{{ translate('copy_account_id') }}">
                        <svg class="sv-ico-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <svg class="sv-ico-done" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </button>
                </div>
            </div>
        </div>
        @include('layouts.vendor.partials.v2._account-navigation')
    </div>
@endif
