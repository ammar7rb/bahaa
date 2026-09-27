@php($editingProduct = $product ?? null)
<div class="col-md-6 col-lg-4">
    <div class="form-group"><label class="form-label">{{ translate('production_date') }} <span class="input-required-icon">*</span></label><input type="date" name="production_date" class="form-control" value="{{ old('production_date', $editingProduct?->production_date?->format('Y-m-d')) }}" required></div>
</div>
<div class="col-md-6 col-lg-4">
    <div class="form-group"><label class="form-label">{{ translate('expiry_date') }} <span class="input-required-icon">*</span></label><input type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date', $editingProduct?->expiry_date?->format('Y-m-d')) }}" required></div>
</div>
<div class="col-12 show-for-physical-product"><div class="alert alert-soft-info mb-2">{{ translate('product_measurements_are_optional_and_blank_values_are_saved_as_empty') }}</div></div>
@foreach(['pieces_per_unit', 'length', 'width', 'height', 'weight'] as $optionalField)
    <div class="col-md-6 col-lg-4 show-for-physical-product"><div class="form-group"><label class="form-label">{{ translate($optionalField) }} <span class="text-muted">({{ translate('optional') }})</span></label><input type="number" min="0.001" step="0.001" name="{{ $optionalField }}" class="form-control" value="{{ old($optionalField, $editingProduct?->{$optionalField}) }}"></div></div>
@endforeach
<div class="col-md-6 col-lg-4 show-for-physical-product"><div class="form-group"><label class="form-label">{{ translate('dimension_unit') }}</label><select name="dimension_unit" class="form-select"><option value="">—</option><option value="cm" @selected($editingProduct?->dimension_unit === 'cm')>cm</option><option value="m" @selected($editingProduct?->dimension_unit === 'm')>m</option></select></div></div>
<div class="col-md-6 col-lg-4 show-for-physical-product"><div class="form-group"><label class="form-label">{{ translate('weight_unit') }}</label><select name="weight_unit" class="form-select"><option value="">—</option><option value="g" @selected($editingProduct?->weight_unit === 'g')>g</option><option value="kg" @selected($editingProduct?->weight_unit === 'kg')>kg</option></select></div></div>
