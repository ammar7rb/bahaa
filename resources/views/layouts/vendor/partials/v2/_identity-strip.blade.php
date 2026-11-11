@php($seller = auth('seller')->user())
@if($seller)
    <div class="vendor-identity-strip d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <strong>{{ trim(($seller->f_name ?? '').' '.($seller->l_name ?? '')) }}</strong>
            <div class="small text-muted">{{ translate('vendor_account_id') }}: <bdi dir="ltr">{{ $seller->administrative_reference ?? $seller->id }}</bdi></div>
        </div>
        @include('layouts.vendor.partials.v2._account-navigation')
    </div>
@endif
