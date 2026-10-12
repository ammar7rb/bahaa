@php($activationSeller = auth('seller')->user())
@if($activationSeller && $activationSeller->activation_status !== 'active')
    <a href="{{ route('vendor.auth.registration.activation') }}" class="d-block text-white text-decoration-none" style="position:sticky;top:0;z-index:1040;background:#c62828;padding:12px 18px;box-shadow:0 2px 8px rgba(0,0,0,.18)" role="alert">
        <span class="d-flex flex-wrap justify-content-center align-items-center gap-2">
            <i class="fi fi-sr-comment-alt"></i>
            <strong>{{ translate('seller_activation_required') }}</strong>
            <span>{{ translate('click_here_to_contact_activation_support') }}</span>
        </span>
    </a>
@endif
