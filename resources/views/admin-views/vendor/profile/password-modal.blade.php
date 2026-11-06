<div class="modal fade" id="resetVendorPasswordModal" tabindex="-1"
     aria-labelledby="resetVendorPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('admin.vendors.reset-password', $seller->id) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="resetVendorPasswordModalLabel">{{ translate('reset_password') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small">
                    {{ translate('admin_password_reset_notice') }}
                </div>
                <div class="mb-3">
                    <label class="form-label" for="vendor-reset-password">{{ translate('new_password') }}</label>
                    <input class="form-control" id="vendor-reset-password" name="password" type="password"
                           minlength="8" required autocomplete="new-password">
                </div>
                <div>
                    <label class="form-label" for="vendor-reset-password-confirmation">{{ translate('confirm_password') }}</label>
                    <input class="form-control" id="vendor-reset-password-confirmation" name="password_confirmation"
                           type="password" minlength="8" required autocomplete="new-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ translate('reset_password') }}</button>
            </div>
        </form>
    </div>
</div>
