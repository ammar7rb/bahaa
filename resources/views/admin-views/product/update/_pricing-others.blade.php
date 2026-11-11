<div class="price_wrapper mt-3">
    <div class="outline-wrapper">
        <div class="card rest-part bg-animate">
            <div class="card-header d-flex justify-content-between align-items-center border-0 pb-0 pc-header-ai-btn">
                 <div>
                    <h2 class="mb-1">{{ translate('Pricing_&_Others') }}</h2>
                    <p class="fs-12 mb-0">
                        {{ translate('Here_you_can_setup_the_price_and_other_information_for_the_product.') }}
                    </p>
                </div>

                @if(getActiveAIProviderConfigCache())
                <button type="button"
                    class="btn bg-white text-primary bg-transparent shadow-none border-0 opacity-1 generate_btn_wrapper p-0 price_others_auto_fill"
                    id="price_others_auto_fill"
                        data-route="{{ route('admin.product.price-others-auto-fill') }}"  data-lang="en">
                    <div class="btn-svg-wrapper">
                        <img width="18" height="18" class=""
                            src="{{ dynamicAsset(path: 'public/assets//back-end/img/ai/blink-right-small.svg') }}" alt="">
                    </div>
                    <span class="ai-text-animation d-none" role="status">
                        {{ translate('Just_a_second') }}
                    </span>
                    <span class="btn-text">{{ translate('Generate') }}</span>
                </button>
                @endif
            </div>
            <div class="card-body">
                <div class="bg-section rounded-10 p-12 p-sm-20">
                    <div class="row gy-4 align-items-end">
                        @unless($product->added_by === 'seller')
                        <div class="col-md-6 col-lg-4">
                            <div class="form-group">
                                <label class="form-label" for="production_date">
                                    {{ translate('production_date') }}
                                    <span class="input-required-icon">*</span>
                                </label>
                                <input type="date" name="production_date" id="production_date"
                                       value="{{ old('production_date', $product->production_date?->format('Y-m-d')) }}"
                                       class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-group">
                                <label class="form-label" for="expiry_date">
                                    {{ translate('expiry_date') }}
                                    <span class="input-required-icon">*</span>
                                </label>
                                <input type="date" name="expiry_date" id="expiry_date"
                                       min="{{ now()->addDay()->toDateString() }}"
                                       value="{{ old('expiry_date', $product->expiry_date?->format('Y-m-d')) }}"
                                       class="form-control" required>
                            </div>
                        </div>

                        @endunless
                        <div class="col-md-6 col-lg-4">
                            <div class="form-group">
                                <label class="form-label">
                                    {{ translate('Unit_Price') }}
                                    <span class="input-required-icon">*</span>
                                    ({{ getCurrencySymbol(currencyCode: getCurrencyCode()) }})
                                    <span class="tooltip-icon cursor-pointer" data-bs-toggle="tooltip"
                                          aria-label="{{ translate('set_the_selling_price_for_each_unit_of_this_product._This_Unit_Price_section_would_not_be_applied_if_you_set_a_variation_wise_price') }}"
                                          data-bs-title="{{ translate('set_the_selling_price_for_each_unit_of_this_product._This_Unit_Price_section_would_not_be_applied_if_you_set_a_variation_wise_price') }}"
                                    >
                                        <i class="fi fi-sr-info"></i>
                                    </span>
                                </label>

                                <input type="number" min="0" step="0.01"
                                       placeholder="{{ translate('unit_price') }}" name="unit_price" id="unit_price"
                                       data-required-msg="{{ translate('unit_price_is_required') }}"
                                       value="{{ old('unit_price', catalogUnitPrice($product->unit_price)) }}" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4" id="minimum_order_qty">
                            <div class="form-group">
                                <label class="form-label" for="minimum_order_qty">
                                    {{ translate('Minimum_Order_Qty') }}
                                    ({{ getCurrencySymbol(currencyCode: getCurrencyCode()) }})
                                    <span class="input-required-icon">*</span>
                                    <span class="tooltip-icon cursor-pointer" data-bs-toggle="tooltip"
                                          aria-label="{{ translate('set_the_minimum_order_quantity_that_customers_must_choose._Otherwise,_the_checkout_process_would_not_start') }}."
                                          data-bs-title="{{ translate('set_the_minimum_order_quantity_that_customers_must_choose._Otherwise,_the_checkout_process_would_not_start') }}."
                                    >
                                        <i class="fi fi-sr-info"></i>
                                    </span>
                                </label>
                                <input type="number" min="1" value="{{ $product->minimum_order_qty }}" step="1"
                                       placeholder="{{ translate('minimum_order_quantity') }}" name="minimum_order_qty"
                                       data-required-msg="{{ translate('minimum_order_quantity_is_required') }}"
                                       id="minimum_order_qty" class="form-control only-number-input" required>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4 show-for-physical-product" id="quantity">
                            <div class="form-group">
                                <label class="form-label" for="current_stock">
                                    {{ translate('Current_Stock_Qty') }}
                                    <span class="input-required-icon">*</span>
                                    <span class="tooltip-icon cursor-pointer" data-bs-toggle="tooltip"
                                          aria-label="{{ translate('add_the_Stock_Quantity_of_this_product_that_will_be_visible_to_customers') }}."
                                          data-bs-title="{{ translate('add_the_Stock_Quantity_of_this_product_that_will_be_visible_to_customers') }}."
                                    >
                                        <i class="fi fi-sr-info"></i>
                                    </span>
                                </label>

                                <input type="number" min="0" value="{{ $product->current_stock }}" step="1"
                                       placeholder="{{ translate('quantity') }}" name="current_stock" id="current_stock"
                                       data-required-msg="{{ translate('current_stock_is_required') }}"
                                       class="form-control only-number-input" required>
                            </div>
                        </div>
                        <input type="hidden" name="discount" id="discount" value="0">
                        <input type="hidden" name="discount_type" id="discount_type" value="flat">

                        @if ($productWiseTax)
                            <div class="col-md-6 col-lg-4">
                                <div class="form-group">
                                    <label class="form-label" for="">
                                        {{ translate('Select_Vat/Tax_Rate') }}
                                        <span class="input-required-icon">*</span>
                                    </label>

                                    <select class="custom-select multiple-select2 multiple-select-tax-input" name="tax_ids[]" multiple="multiple"
                                            data-placeholder="{{ translate('Type_&_Select_Vat/Tax_Rate') }}">
                                        <option value="all">{{ translate('All') }}</option>
                                        @foreach ($taxVats as $taxVat)
                                            <option value="{{ $taxVat->id }}" {{ in_array($taxVat->id, $taxVatIds) ? 'selected' : '' }}>
                                                {{ $taxVat->name }} ({{ $taxVat->tax_rate }}%)
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif

                        {{-- Shipping is managed by the order workflow; retain the stored product rates. --}}
                        @foreach(['shipping_cost', 'same_day_shipping_cost', 'next_day_shipping_cost', 'normal_shipping_cost'] as $retainedShippingField)
                            <input type="hidden" name="{{ $retainedShippingField }}" value="{{ usdToDefaultCurrency($product->{$retainedShippingField} ?? 0) }}">
                        @endforeach
                        <input type="hidden" name="multiply_qty" value="{{ $product->multiply_qty ? 'on' : 'off' }}">

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
