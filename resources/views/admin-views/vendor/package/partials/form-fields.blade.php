@php($package = $package ?? null)

<div class="p-12 p-sm-20 bg-section rounded mb-3">
    <div class="mb-3">
        <h3 class="mb-1">{{ translate('Package_Information') }}</h3>
    </div>
    <div class="row g-3">
        <div class="col-lg-4 col-md-6">
            <label class="form-label" for="name">{{ translate('Package_Name') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="name" name="name"
                   value="{{ old('name', $package?->name) }}" maxlength="191" required>
            @error('name') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label" for="package-price">
                {{ translate('Package_Price') }} ({{ getCurrencySymbol(currencyCode: getCurrencyCode(type: 'default')) }})
                <span class="text-danger">*</span>
            </label>
            <input type="number" class="form-control" id="package-price" name="package_price"
                   value="{{ old('package_price', $package ? usdToDefaultCurrency(amount: $package->package_price) : null) }}"
                   min="0.01" step="0.01" required>
            @error('package_price') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label" for="package-validity-days">{{ translate('Package_Validity_Days') }}</label>
            <input type="number" class="form-control" id="package-validity-days" name="package_validity_days"
                   value="{{ old('package_validity_days', $package?->package_validity_days) }}" min="1" max="3650"
                   placeholder="{{ translate('No_Expiry') }}">
            @error('package_validity_days') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label" for="sort-order">{{ translate('Sort_Order') }}</label>
            <input type="number" class="form-control" id="sort-order" name="sort_order"
                   value="{{ old('sort_order', $package?->sort_order ?? 0) }}" min="0">
            @error('sort_order') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label" for="status">{{ translate('Status') }}</label>
            <div class="select-wrapper">
                <select class="form-select" id="status" name="status">
                    <option value="1" {{ (string) old('status', $package ? (int) $package->status : 1) === '1' ? 'selected' : '' }}>{{ translate('Active') }}</option>
                    <option value="0" {{ (string) old('status', $package ? (int) $package->status : 1) === '0' ? 'selected' : '' }}>{{ translate('Inactive') }}</option>
                </select>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label" for="description">{{ translate('Description') }}</label>
            <textarea class="form-control" id="description" name="description" rows="3" maxlength="2000">{{ old('description', $package?->description) }}</textarea>
            @error('description') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<div class="p-12 p-sm-20 bg-section rounded mb-3">
    <div class="mb-3">
        <h3 class="mb-1">{{ translate('Product_Listing_Allowance') }}</h3>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="product-limit">{{ translate('Number_Of_Product_Listings') }} <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="product-limit" name="product_limit"
                   value="{{ old('product_limit', $package?->product_limit) }}" min="1" max="1000000" required>
            @error('product_limit') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="product-duration-days">{{ translate('Each_Product_Listing_Duration_Days') }} <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="product-duration-days" name="product_duration_days"
                   value="{{ old('product_duration_days', $package?->product_duration_days) }}" min="1" max="3650" required>
            @error('product_duration_days') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<div class="p-12 p-sm-20 bg-section rounded mb-3">
    <div class="mb-3">
        <h3 class="mb-1">{{ translate('Featured_Search_Allowance') }}</h3>
        <p class="mb-0 fs-12 text-muted">{{ translate('set_the_quota_to_zero_when_this_package_does_not_include_featured_search') }}.</p>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="search-promotion-limit">{{ translate('Featured_Search_Quota') }} <span class="text-danger">*</span></label>
            <input type="number" class="form-control promotion-limit" id="search-promotion-limit"
                   name="search_promotion_limit" value="{{ old('search_promotion_limit', $package?->search_promotion_limit ?? 0) }}"
                   min="0" max="1000000" data-duration-target="search-promotion-duration-days" required>
            @error('search_promotion_limit') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="search-promotion-duration-days">{{ translate('Each_Featured_Search_Duration_Days') }}</label>
            <input type="number" class="form-control" id="search-promotion-duration-days"
                   name="search_promotion_duration_days"
                   value="{{ old('search_promotion_duration_days', ($package?->search_promotion_limit ?? 0) > 0 ? $package?->search_promotion_duration_days : null) }}"
                   min="1" max="3650">
            @error('search_promotion_duration_days') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<div class="p-12 p-sm-20 bg-section rounded">
    <div class="mb-3">
        <h3 class="mb-1">{{ translate('Homepage_Featured_Allowance') }}</h3>
        <p class="mb-0 fs-12 text-muted">{{ translate('set_the_quota_to_zero_when_this_package_does_not_include_homepage_featured_offers') }}.</p>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="homepage-promotion-limit">{{ translate('Homepage_Featured_Quota') }} <span class="text-danger">*</span></label>
            <input type="number" class="form-control promotion-limit" id="homepage-promotion-limit"
                   name="homepage_promotion_limit" value="{{ old('homepage_promotion_limit', $package?->homepage_promotion_limit ?? 0) }}"
                   min="0" max="1000000" data-duration-target="homepage-promotion-duration-days" required>
            @error('homepage_promotion_limit') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="homepage-promotion-duration-days">{{ translate('Each_Homepage_Featured_Duration_Days') }}</label>
            <input type="number" class="form-control" id="homepage-promotion-duration-days"
                   name="homepage_promotion_duration_days"
                   value="{{ old('homepage_promotion_duration_days', ($package?->homepage_promotion_limit ?? 0) > 0 ? $package?->homepage_promotion_duration_days : null) }}"
                   min="1" max="3650">
            @error('homepage_promotion_duration_days') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

@push('script_2')
    <script>
        'use strict';
        document.querySelectorAll('.promotion-limit').forEach(function (limitInput) {
            const durationInput = document.getElementById(limitInput.dataset.durationTarget);
            const syncDuration = function () {
                const enabled = Number(limitInput.value) > 0;
                durationInput.disabled = !enabled;
                durationInput.required = enabled;
                if (!enabled) durationInput.value = '';
            };
            limitInput.addEventListener('input', syncDuration);
            syncDuration();
        });
    </script>
@endpush
