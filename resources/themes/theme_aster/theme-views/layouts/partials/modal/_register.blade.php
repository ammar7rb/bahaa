@php($inlineRegistration = $inlineRegistration ?? false)
@if($inlineRegistration)
<div class="card mx-auto" style="max-width: 54rem">
@else
<div class="modal fade max-z-index-for-auth-modal" id="registerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
@endif
            <div @class(['card-body p-4 p-lg-5' => $inlineRegistration, 'modal-body px-4 px-lg-5' => !$inlineRegistration])>
                <div class="mb-4 text-center">
                    <img width="200" alt="" class="dark-support ba-platform-logo"
                         src="{{ getStorageImages(path: $web_config['web_logo'], type: 'logo') }}">
                </div>
                <div class="mb-4">
                    <h2 class="mb-2">{{ translate('sign_up') }}</h2>
                    <p class="text-muted">
                        {{ translate('Want To') }}
                        <span class="text-primary link-hover-base fw-bold" data-bs-toggle="modal" data-bs-target="#loginModal">
                            {{ translate('Login') }}
                        </span> {{ translate('Your_Account') }} ?
                    </p>
                </div>
                <form action="{{ route('customer.auth.sign-up') }}" method="POST" id="customer-form"
                      enctype="multipart/form-data">
                    @include('customer-account.registration-fields')
                </form>

                @if ($web_config['social_login_text'])
                    <p class="text-center text-muted">{{ translate('or_continue_with') }}</p>
                @endif

                <div class="d-flex justify-content-center gap-3 align-items-center flex-wrap pb-3">
                    @if (isset($web_config['customer_login_options']['social_login']) &&
                            $web_config['customer_login_options']['social_login']
                    )
                        @foreach ($web_config['customer_social_login_options'] as $socialLoginServiceKey => $socialLoginService)
                            @if ($socialLoginService && $socialLoginServiceKey != 'apple')
                                <a href="{{ route('customer.auth.service-login', $socialLoginServiceKey) }}">
                                    <img width="35"
                                         src="{{ theme_asset('assets/img/svg/' . $socialLoginServiceKey . '.svg') }}"
                                         alt="" class="dark-support"/>
                                </a>
                            @endif
                        @endforeach
                    @endif
                </div>
            </div>
@unless($inlineRegistration)
        </div>
    </div>
@endunless
</div>

@push('script')
    <script>
        "use strict";
        $('#customer-form').submit(function (event) {
            event.preventDefault();
            let formData = $(this).serialize();

            $.ajax({
                type: 'POST',
                url: $(this).attr('action'),
                data: formData,
                beforeSend: function () {
                    $("#loading").addClass("d-grid");
                },
                success: function (response) {
                    if (response.errors) {
                        for (let index = 0; index < response.errors.length; index++) {
                            toastr.error(response.errors[index].message);
                        }
                    } else if (response.error) {
                        toastr.error(response.error);
                    } else if (response.status === 1) {
                        toastr.success(response.message);
                    }

                    if (response?.redirect_url) {
                        window.location.href = response.redirect_url;
                    }
                },
                error: function (xhr) {
                    $("#loading").removeClass("d-grid");

                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        toastr.error(xhr.responseJSON.error, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        $.each(xhr.responseJSON.errors, function (key, value) {
                            toastr.error(value, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        });
                    } else {
                        toastr.error('An unexpected error occurred.', {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    }
                },
                complete: function () {
                    $("#loading").removeClass("d-grid");
                }
            });
        });
    </script>
@endpush
