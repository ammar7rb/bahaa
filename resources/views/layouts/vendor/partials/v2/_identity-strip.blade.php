@php($stripSeller = auth('seller')->user())
<section class="vendor-identity-strip" aria-label="{{ translate('vendor_account') }}">
    <div class="vendor-identity-person">
        <form method="post" action="{{ route('vendor.account.image') }}" enctype="multipart/form-data" class="vendor-avatar-form">@csrf<label class="vendor-avatar-edit" for="vendor-avatar-input" title="{{ translate('sigma_change_image') }}"><img src="{{ getStorageImages(path: $stripSeller->image_full_url, type: 'backend-profile') }}" alt="{{ translate('sigma_change_image') }}" width="52" height="52"><span aria-hidden="true">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'edit'])</span></label><input class="vendor-avatar-input" id="vendor-avatar-input" type="file" name="image" accept="image/jpeg,image/png,image/webp" aria-label="{{ translate('sigma_change_image') }}"><button type="submit" class="vendor-avatar-submit">{{ translate('save') }}</button></form>
        <div class="vendor-identity-details">
            <div class="vendor-identity-name"><strong>{{ trim($stripSeller->f_name.' '.$stripSeller->l_name) }}</strong><span class="sigma-account-status {{ $stripSeller->status === 'approved' ? 'is-active' : '' }}">{{ translate('sigma_account_status_'.$stripSeller->status) }}</span></div>
            <div class="vendor-reference-row"><bdi dir="ltr" data-vendor-reference>{{ $stripSeller->administrative_reference ?? $stripSeller->id }}</bdi><button type="button" class="vendor-reference-copy" data-copy-vendor-reference aria-label="{{ translate('sigma_copy_account_id') }}" title="{{ translate('sigma_copy_account_id') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg></button><span class="vendor-copy-status" data-vendor-copy-status role="status" aria-live="polite"></span></div>
        </div>
    </div>
    @include('layouts.vendor.partials.v2._account-navigation')
</section>
@if(session('vendor_image_saved'))<div class="alert alert-success" role="status">{{ translate('profile_updated_successfully') }}</div>@endif
@if($errors->has('image'))<div class="alert alert-danger" role="alert">{{ $errors->first('image') }}</div>@endif
@push('script')<script src="{{ dynamicAsset(path: 'public/assets/back-end/js/vendor-reference-copy.js') }}?v={{ filemtime(public_path('assets/back-end/js/vendor-reference-copy.js')) }}" defer></script>@endpush
