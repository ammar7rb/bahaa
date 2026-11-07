@csrf
<div class="row g-3">
    <div class="col-12">
        <label for="registration-first-name">{{ translate('first_name') }} <span class="text-danger" aria-hidden="true">*</span></label>
        <input class="form-control" id="registration-first-name" name="f_name" type="text" autocomplete="given-name" maxlength="100" value="{{ old('f_name') }}" required>
    </div>
    <div class="col-12">
        <label for="registration-phone">{{ translate('phone') }} <span class="text-danger" aria-hidden="true">*</span></label>
        <input class="form-control" id="registration-phone" name="phone" type="tel" data-egypt-phone inputmode="tel" autocomplete="tel" dir="ltr" placeholder="01xxxxxxxxx" value="{{ old('phone') }}" required>
    </div>
    <div class="col-12">
        <label for="registration-password">{{ translate('password') }} <span class="text-danger" aria-hidden="true">*</span></label>
        <input class="form-control" id="registration-password" name="password" type="password" autocomplete="new-password" minlength="6" maxlength="255" placeholder="6 أحرف على الأقل" required>
    </div>
    <div class="col-12 mt-4">
        <button class="btn btn-primary btn--primary w-100" id="customer-sign-up-btn" type="submit">{{ translate('sign_up') }}</button>
    </div>
</div>
