<div class="general_wrapper mt-3">
    <div class="outline-wrapper">
        <div class="card rest-part bg-animate">
            @if(getActiveAIProviderConfigCache())
            <div class="card-header d-flex justify-content-between align-items-center border-0 shadow-none pb-0 pc-header-ai-btn">
                @if(getActiveAIProviderConfigCache())
                    <button type="button" class="btn bg-white text-primary bg-transparent shadow-none border-0 opacity-1 generate_btn_wrapper p-0 general_setup_auto_fill" id="general_setup_auto_fill" data-route="{{ route('vendor.product.general-setup-auto-fill') }}" data-lang="en">
                        <img width="18" height="18" src="{{ dynamicAsset(path: 'public/assets//back-end/img/ai/blink-right-small.svg') }}" alt="">
                        <span class="ai-text-animation d-none" role="status">{{ translate('Just_a_second') }}</span>
                        <span class="btn-text">{{ translate('Generate') }}</span>
                    </button>
                @endif
            </div>
            @endif
            <div class="card-body">
                <div class="bg-section rounded-10 p-12 p-sm-20">
                    <input type="hidden" name="product_type" value="physical">
                    <input type="hidden" name="sub_category_id" value="">
                    <input type="hidden" name="sub_sub_category_id" value="">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="title-color">{{ translate('category') }}</label>
                                <select class="js-select2-custom form-control" name="category_id" id="category_id">
                                    <option value="">{{ translate('select_category') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category['id'] }}" {{ old('category_id') == $category['id'] ? 'selected' : '' }}>{{ $category['defaultName'] }}</option>
                                    @endforeach</select>
                            </div>
                        </div>
                        @if($brandSetting)
                            <div class="col-md-6 physical_product_show">
                                <div class="form-group mb-0">
                                    <label class="title-color">{{ translate('brand') }}</label>
                                    <select class="js-select2-custom form-control" name="brand_id" id="brand_id">
                                        <option value="">{{ translate('brand') }}</option>
                                        @foreach ($brands as $brand)
                                            <option value="{{ $brand['id'] }}">{{ $brand['defaultName'] }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="brand_name" class="form-control mt-2" placeholder="{{ translate('enter_brand_name_if_not_listed') }}">
                                    <small class="text-muted d-block mt-1">{{ translate('choose_an_existing_brand_or_enter_a_new_name') }}</small>
                                </div>
                            </div>
                        @endif
                        <input type="hidden" id="generate_number" name="code" value="{{ old('code', \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(12))) }}">
                        <input type="hidden" name="unit" value="pc">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
