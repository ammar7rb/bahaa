@php
    $sellerProduct = $product ?? null;
    // Legacy products may still hold the old `box` value. Treat it as a package
    // so sellers only ever see the two supported sale units.
    $saleUnitType = old('sale_unit_type', data_get($sellerProduct, 'sale_unit_type', 'piece'));
    $saleUnitType = $saleUnitType === 'box' ? 'package' : $saleUnitType;
@endphp

<div class="outline-wrapper mt-3 seller-product-specification physical_product_show">
    <div class="card rest-part bg-animate">
        <div class="card-header border-0 shadow-none pb-0">
            <div>
                <h3 class="mb-1">{{ translate('seller_product_sale_unit_and_measurements') }}</h3>
                <p class="fs-12 mb-0">{{ translate('seller_product_measurements_help') }}</p>
            </div>
        </div>
        <div class="card-body">
            <div class="bg-section rounded-10 p-12 p-sm-20">
                <div class="row g-2">
                    <div class="col-md-6 col-lg-4">
                        <label class="title-color" for="production_date">{{ translate('production_date') }} <span class="input-required-icon">*</span></label>
                        <input class="form-control" type="date" name="production_date" id="production_date" required
                               value="{{ old('production_date', optional(data_get($sellerProduct, 'production_date'))->format('Y-m-d')) }}">
                        @error('production_date') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="title-color" for="expiry_date">{{ translate('expiry_date') }} <span class="input-required-icon">*</span></label>
                        <input class="form-control" type="date" name="expiry_date" id="expiry_date" required min="{{ now()->addDay()->format('Y-m-d') }}"
                               value="{{ old('expiry_date', optional(data_get($sellerProduct, 'expiry_date'))->format('Y-m-d')) }}">
                        @error('expiry_date') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="title-color" for="sale_unit_type">{{ translate('seller_product_sale_unit_type') }} <span class="input-required-icon">*</span></label>
                        <select class="form-control" name="sale_unit_type" id="sale_unit_type" required>
                            <option value="piece" {{ $saleUnitType === 'piece' ? 'selected' : '' }}>{{ translate('seller_product_sale_unit_piece') }}</option>
                            <option value="package" {{ $saleUnitType === 'package' ? 'selected' : '' }}>{{ translate('seller_product_sale_unit_package') }}</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-4" id="pieces_per_unit_wrapper" @if($saleUnitType !== 'package') style="display: none;" @endif>
                        <label class="title-color" for="pieces_per_unit">{{ translate('seller_product_pieces_per_unit') }} <span class="input-required-icon">*</span></label>
                        <input class="form-control" type="number" min="1" step="1" name="pieces_per_unit" id="pieces_per_unit" @if($saleUnitType === 'package') required @else disabled @endif
                               value="{{ old('pieces_per_unit', data_get($sellerProduct, 'pieces_per_unit')) }}"
                               placeholder="{{ translate('seller_product_pieces_per_unit') }}">
                    </div>
                </div>

                <div class="border-top mt-3 pt-3">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <label class="title-color mb-0">{{ translate('seller_product_optional_dimensions') }}</label>
                        <span class="fs-12 text-muted">{{ translate('seller_product_optional_measurements_note') }}</span>
                    </div>
                    <div class="row g-2">
                        @foreach(['length' => 'seller_product_length', 'width' => 'seller_product_width', 'height' => 'seller_product_height'] as $field => $label)
                            <div class="col-6 col-lg-2">
                                <input class="form-control" type="number" min="0" step="0.01" name="{{ $field }}"
                                       value="{{ old($field, data_get($sellerProduct, $field)) }}" placeholder="{{ translate($label) }}">
                            </div>
                        @endforeach
                        <div class="col-6 col-lg-2">
                            <select class="form-control" name="dimension_unit" aria-label="{{ translate('seller_product_dimension_unit') }}">
                                @foreach(['cm', 'm'] as $unit)
                                    <option value="{{ $unit }}" {{ old('dimension_unit', data_get($sellerProduct, 'dimension_unit', 'cm')) === $unit ? 'selected' : '' }}>{{ $unit }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <input class="form-control" type="number" min="0" step="0.01" name="weight"
                                   value="{{ old('weight', data_get($sellerProduct, 'weight')) }}" placeholder="{{ translate('seller_product_weight') }}">
                        </div>
                        <div class="col-6 col-lg-2">
                            <select class="form-control" name="weight_unit" aria-label="{{ translate('seller_product_weight_unit') }}">
                                @foreach(['kg', 'g'] as $unit)
                                    <option value="{{ $unit }}" {{ old('weight_unit', data_get($sellerProduct, 'weight_unit', 'kg')) === $unit ? 'selected' : '' }}>{{ $unit }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="border-top mt-3 pt-3">
                    <label class="title-color d-flex align-items-center gap-2" for="tags">
                        {{ translate('search_tags') }}
                        <span class="input-label-secondary cursor-pointer" data-toggle="tooltip"
                              title="{{ translate('seller_product_search_tags_help') }}">
                            <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/info-circle.svg') }}" alt="">
                        </span>
                    </label>
                    <p class="fs-12 text-muted mb-2">{{ translate('seller_product_search_tags_help') }}</p>
                    <input class="form-control" type="text" name="tags" id="tags"
                           value="{{ old('tags', data_get($sellerProduct, 'tags')?->pluck('tag')->implode(', ')) }}"
                           placeholder="{{ translate('enter_search_tags_separated_by_commas') }}">
                </div>
            </div>
        </div>
    </div>
</div>

@push('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const saleUnitType = document.getElementById('sale_unit_type');
            const piecesWrapper = document.getElementById('pieces_per_unit_wrapper');
            const piecesPerUnit = document.getElementById('pieces_per_unit');

            if (!saleUnitType || !piecesWrapper || !piecesPerUnit) return;

            const syncPiecesPerUnit = function () {
                const isPackage = saleUnitType.value === 'package';
                piecesWrapper.style.display = isPackage ? '' : 'none';
                piecesPerUnit.required = isPackage;
                piecesPerUnit.disabled = !isPackage;

                // A single item has no package quantity. Clearing it prevents
                // stale values from being submitted when a seller changes unit.
                if (!isPackage) piecesPerUnit.value = '';
            };

            saleUnitType.addEventListener('change', syncPiecesPerUnit);
            syncPiecesPerUnit();
        });
    </script>
@endpush
