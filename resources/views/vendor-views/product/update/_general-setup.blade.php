<div class="general_wrapper mt-3">
    <div class="outline-wrapper">
        <div class="card rest-part bg-animate">
            <div class="card-header border-0 shadow-none pb-0">
                <h3 class="mb-1">{{ translate('General_Setup') }}</h3>
                <p class="fs-12 mb-0">{{ translate('Here_you_can_setup_the_foundational_details_required_for_product_creation.') }}</p>
            </div>
            <div class="card-body">
                <div class="bg-section rounded-10 p-12 p-sm-20">
                    <input type="hidden" name="product_type" value="physical">
                    <input type="hidden" name="sub_sub_category_id" value="">
                    <div class="row g-2">
                        <div class="col-md-6 col-lg-4">
                            <div class="form-group mb-0">
                                <label class="title-color">{{ translate('category') }} <span class="input-required-icon">*</span></label>
                                <select class="js-select2-custom form-control action-get-request-onchange" name="category_id" id="category_id" data-url-prefix="{{ url('/vendor/products/get-categories?parent_id=') }}" data-element-id="sub-category-select" data-element-type="select" required>
                                    <option value="0" selected disabled>---{{ translate('select') }}---</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category['id'] }}" {{ $category->id == $product['category_id'] ? 'selected' : '' }}>{{ $category['defaultName'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-group mb-0">
                                <label class="title-color">{{ translate('sub_Category') }}</label>
                                <select class="js-select2-custom form-control" name="sub_category_id" id="sub-category-select" data-id="{{ $product['sub_category_id'] }}" data-url-prefix="{{ url('/vendor/products/get-categories?parent_id=') }}">
                                    <option value="">{{ translate('select_Sub_Category') }}</option>
                                </select>
                            </div>
                        </div>
                        @if($brandSetting)
                            <div class="col-md-6 col-lg-4 physical_product_show">
                                <div class="form-group mb-0">
                                    <label class="title-color">{{ translate('brand') }} <span class="text-muted">({{ translate('optional') }})</span></label>
                                    <select class="js-select2-custom form-control" name="brand_id" id="brand_id">
                                        <option value="">{{ translate('No_Brand') }}</option>
                                        @foreach($brands as $brand)
                                            <option value="{{ $brand['id'] }}" {{ $brand['id'] == $product['brand_id'] ? 'selected' : '' }}>{{ $brand['defaultName'] }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="brand_name" class="form-control mt-2" placeholder="{{ translate('enter_brand_name_if_not_listed') }}">
                                    <small class="text-muted d-block mt-1">{{ translate('choose_an_existing_brand_or_enter_a_new_name') }}</small>
                                </div>
                            </div>
                        @endif
                        <div class="col-md-6 col-lg-4">
                            <div class="form-group mb-0">
                                <label class="title-color d-flex justify-content-between gap-2"><span>{{ translate('product_SKU') }} <span class="input-required-icon">*</span></span><span class="style-one-pro cursor-pointer user-select-none text--primary action-onclick-generate-number" data-input="#generate_number">{{ translate('generate_code') }}</span></label>
                                <input type="text" id="generate_number" name="code" class="form-control" value="{{ request('product-gallery') ? ' ' : $product->code }}" placeholder="{{ translate('4FOITO') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4 physical_product_show">
                            <div class="form-group mb-0">
                                <label class="title-color">{{ translate('unit') }} <span class="input-required-icon">*</span></label>
                                <select class="js-example-basic-multiple form-control" name="unit" required>
                                    @foreach(units() as $unit)
                                        <option value="{{ $unit }}" {{ $product['unit'] == $unit ? 'selected' : '' }}>{{ $unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
