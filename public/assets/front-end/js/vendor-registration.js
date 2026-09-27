'use strict';
$(document).ready(function () {
    const egyptianPhoneInput = $('#vendor-registration-phone');
    const egyptianPhoneFullInput = $('#vendor-registration-phone-full');

    function normalizeEgyptianPhone(value) {
        const digits = String(value || '')
            .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)))
            .replace(/\D/g, '');
        let localNumber = digits;

        if (localNumber.indexOf('0020') === 0) {
            localNumber = localNumber.slice(4);
        } else if (localNumber.indexOf('20') === 0) {
            localNumber = localNumber.slice(2);
        }
        if (localNumber.indexOf('0') === 0) {
            if (localNumber.length !== 11) {
                return '';
            }
            localNumber = localNumber.slice(1);
        }

        return /^1[0125]\d{8}$/.test(localNumber) ? `+20${localNumber}` : '';
    }

    function syncEgyptianPhone() {
        const normalizedPhone = normalizeEgyptianPhone(egyptianPhoneInput.val());
        egyptianPhoneFullInput.val(normalizedPhone);
        return normalizedPhone;
    }
    window.vendorRegistrationSyncPhone = syncEgyptianPhone;
    syncEgyptianPhone();

    egyptianPhoneInput.on('input', function () {
        $(this).val($(this).val().replace(/[^0-9٠-٩]/g, '').slice(0, 11));
        syncEgyptianPhone();
    });

    $('.proceed-to-next-btn').click(function () {
        const registrationForm = document.getElementById('seller-registration');
        const firstStepRequiredInputs = registrationForm
            ? Array.from(registrationForm.querySelectorAll('.first-el [required]'))
            : [];
        const invalidFirstStepInput = firstStepRequiredInputs.find((input) => !input.checkValidity());
        if (invalidFirstStepInput) {
            invalidFirstStepInput.reportValidity();
            return;
        }
        let email = ($('#email').val() || '').trim();
        let phone = syncEgyptianPhone();
        let password = $('#password').val();
        let confirmPassword = $('#confirm_password').val();
        let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        let getErrorMessages = $('#proceed-to-next-validation-message');
        if (email === '') {
            $('.mail-error').html(getErrorMessages.data('mail-error'));
            return;
        } else {
            $('.mail-error').html('');
        }
        if (!emailPattern.test(email)) {
            $('.mail-error').html(getErrorMessages.data('valid-mail'));
            return;
        } else {
            $('.mail-error').html('');
        }
        if (phone === '') {
            $('.phone-error').html(getErrorMessages.data('invalid-phone') || getErrorMessages.data('phone-error'));
            return;
        } else {
            $('.phone-error').html('');
        }
        if (password === '') {
            $('.password-error').html(getErrorMessages.data('enter-password'));
            return;
        } else {
            $('.password-error').html('');
        }
        if (confirmPassword === '') {
            $('.confirm-password-error').html(getErrorMessages.data('enter-confirm-password'));
            return;
        } else {
            $('.confirm-password-error').html('');
        }
        if (password.trim() !== confirmPassword.trim()) {
            $('.confirm-password-error').html(getErrorMessages.data('password-not-match'));
            return;
        } else {
            $('.confirm-password-error').html('');
        }
        const availabilityUrl = $('#registration-availability-endpoint').data('url');
        const proceedButton = $(this);
        if (!availabilityUrl) {
            $('.first-el').fadeOut(200, function () {
                $('.second-el').removeClass('d--none').hide().fadeIn(250);
                window.scrollTo({top: 0, behavior: 'smooth'});
            });
            return;
        }
        proceedButton.prop('disabled', true);
        $.get(availabilityUrl, {email: email, phone: phone})
            .done(function (data) {
                const errors = data.errors || {};
                $('.mail-error').html(errors.email || '');
                $('.phone-error').html(errors.phone || '');
                if (data.available === false) {
                    return;
                }
                $('.first-el').fadeOut(200, function () {
                    $('.second-el').removeClass('d--none').hide().fadeIn(250);
                    window.scrollTo({top: 0, behavior: 'smooth'});
                });
            })
            .fail(function () {
                toastr.error(getErrorMessages.data('availability-error'));
            })
            .always(function () {
                proceedButton.prop('disabled', false);
            });
    });
});
$('.back-to-main-page').on('click', function () {
    $('.second-el').fadeOut(200, function () {
        $('.second-el').addClass('d--none');
        $('.first-el').fadeIn(250);
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
});

function submitRegistration() {
    if (typeof window.vendorRegistrationSyncPhone === 'function') {
        window.vendorRegistrationSyncPhone();
    }
    const registrationForm = document.getElementById('seller-registration');
    if (registrationForm && !registrationForm.checkValidity()) {
        const invalidInput = registrationForm.querySelector(':invalid');
        if (invalidInput && invalidInput.closest('.first-el')) {
            $('.second-el').addClass('d--none').hide();
            $('.first-el').show();
        } else {
            $('.first-el').hide();
            $('.second-el').removeClass('d--none').show();
        }
        invalidInput?.reportValidity();
        return;
    }

    if (!$('#vendor-registration-phone-full').val()) {
        $('.phone-error').html($('#proceed-to-next-validation-message').data('invalid-phone') || $('#proceed-to-next-validation-message').data('phone-error'));
        $('.first-el').fadeIn(0);
        $('.second-el').fadeOut(0);
        return;
    }
    let getText = $('#get-confirm-and-cancel-button-text');
    const getFormId = 'seller-registration';
    Swal.fire({
        title: getText.data('sure'),
        text: getText.data('message'),
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: getText.data('cancel'),
        confirmButtonText: getText.data('confirm'),
        reverseButtons: true
    }).then((result) => {
        if (result.value) {

            let formData = new FormData(document.getElementById(getFormId));
            $.ajaxSetup({
                headers: {
                    'X-XSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: $('#' + getFormId).attr('action'),
                data: formData,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $("#loading").addClass("d-grid");
                },
                success: function (data) {
                    if (data.errors) {
                        for (let index = 0; index < data.errors.length; index++) {
                            toastr.error(data.errors[index].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    } else if (data.error) {
                        toastr.error(data.error, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    } else {
                        if (data.status) {
                            $('.registration-success-modal').modal('show');
                            setTimeout(function () {
                                location.href = data.redirectRoute;
                            }, 2000);
                        }
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON?.errors;
                        if (errors) {
                            Object.keys(errors).forEach(function (key) {
                                toastr.error(errors[key][0]);
                            });
                        } else if (xhr.responseJSON?.error) {
                            toastr.error(xhr.responseJSON.error);
                        } else if (xhr.responseJSON?.message) {
                            toastr.error(xhr.responseJSON.message);
                        }
                    } else {
                        const errorMessage = xhr.responseJSON?.error || xhr.responseJSON?.message || 'Something went wrong!';
                        toastr.error(errorMessage);
                    }
                }
                , complete: function () {
                    $("#loading").removeClass("d-grid");
                },
            })
        }
    })
}
$('#terms-checkbox').on('click', function () {
    if ($(this).is(':checked')) {
        $('#vendor-apply-submit').removeAttr('disabled');
    } else {
        $('#vendor-apply-submit').attr('disabled', 'disabled');
    }
});
