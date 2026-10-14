<aside class="col-lg-4 pt-4 pt-lg-2 px-max-md-0 order-summery-aside">
    @php(session()->forget(['coupon_code','coupon_discount','coupon_type','coupon_bearer','order_note']))
    <div class="__cart-total __cart-total_sticky">
        <div class="cart_total p-0">
            @php($systemTaxConfig = getTaxModuleSystemTypesConfig())
            @php($shippingMethod = getWebConfig(name: 'shipping_method'))
            @php($subTotal = 0)
            @php($totalTax = \App\Utils\CartManager::getCartListTaxAmount())
            @php($taxCollectedInSecondStage = app(\App\Services\PostPurchaseInvoiceService::class)->isEnabled())
            @if($taxCollectedInSecondStage)
                @php($totalTax = array_merge($totalTax, ['item_tax' => 0, 'shipping_cost_tax' => 0]))
            @endif
            @php($totalAmount = 0)
            @php($totalAmountAfterReferralDiscount = 0)
            @php($totalShippingCost = 0)
            @php($referralAmount = 0)
            @php($totalDiscountOnProduct = 0)
            @php($cart = \App\Utils\CartManager::getCartListQuery(type: 'checked'))
            @php($cartGroupIds = \App\Utils\CartManager::get_cart_group_ids())
            @php($getShippingCost = \App\Utils\CartManager::get_shipping_cost(type: 'checked'))
            @php($getShippingCostSavedForFreeDelivery = \App\Utils\CartManager::getShippingCostSavedForFreeDelivery(type: 'checked'))
            @if ($cart->count() > 0)
                @foreach ($cart as $key => $cartItem)
                    @php($subTotal += $cartItem['price'] * $cartItem['quantity'])
                    @php($totalDiscountOnProduct += $cartItem['discount'] * $cartItem['quantity'])
                @endforeach

                @if (session()->missing('coupon_type') || session('coupon_type') != 'free_delivery')
                    @php($totalShippingCost = $getShippingCost - $getShippingCostSavedForFreeDelivery)
                @else
                    @php($totalShippingCost = $getShippingCost)
                @endif
            @endif

            @include('web-views.users-profile.partials.purchasing-limit-strip')
            <div class="d-flex justify-content-between">
                <span class="cart_title">{{ translate('item_price') }}</span>
                <span class="cart_value">{{ webCurrencyConverter(amount: $subTotal - $totalDiscountOnProduct) }}</span>
            </div>
            @php($coupon_dis = 0)
            <div class="d-flex justify-content-between">
                <span class="cart_title">{{ translate('shipping') }}</span>
                <span class="cart_value">
                    {{ webCurrencyConverter(amount: $totalShippingCost) }}
                </span>
            </div>
            @if(!$taxCollectedInSecondStage && $systemTaxConfig['SystemTaxVat']['is_active'] && !$systemTaxConfig['is_included'])
                <div class="d-flex justify-content-between">
                    <span class="cart_title">{{ translate('tax') }}</span>
                    <span class="cart_value">
                    {{ webCurrencyConverter(amount: ($totalTax['item_tax'] + $totalTax['shipping_cost_tax'])) }}
                </span>
                </div>
            @endif

            <?php
            $coupon_dis = 0;
            if (session()->has('coupon_discount')) {
                $couponDiscount = session()->has('coupon_discount') ? session('coupon_discount') : 0;
                $coupon_dis = $couponDiscount;
            }

            $totalAmount = $subTotal + ($totalTax['item_tax'] + $totalTax['shipping_cost_tax']) + $totalShippingCost - $coupon_dis - $totalDiscountOnProduct;
            $referralAmount = \App\Utils\CustomerManager::getReferralDiscountAmount(
                user: (auth('customer')->check() ? auth('customer')->user() : null),
                couponDiscount: $coupon_dis
            );
            ?>

            @if ($referralAmount > 0)
                <div class="d-flex justify-content-between">
                    <span class="cart_title">{{ translate('referral_discount') }}</span>
                    <span class="cart_value">
                        - {{ webCurrencyConverter(amount: $referralAmount) }}
                    </span>
                </div>
            @endif

            @php($coupon_dis = 0)


            <hr class="my-3">
            <div class="d-flex justify-content-between">
                <span class="cart_title text-primary font-weight-bold">
                    {{ translate('total') }}
                    @if(!$taxCollectedInSecondStage && $systemTaxConfig['SystemTaxVat']['is_active'] && $systemTaxConfig['is_included'])
                        <span class="fs-12 font-weight--600">({{ translate('Tax_:_Inc.') }})</span>
                    @endif
                </span>
                <span class="cart_value">
                    {{ webCurrencyConverter(amount: $totalAmount - $referralAmount) }}
                </span>
            </div>
        </div>
        @php($company_reliability = getWebConfig(name: 'company_reliability'))
        @if($taxCollectedInSecondStage)
            <p class="small text-muted mt-3">{{ translate('checkout_tax_second_stage_note') }}</p>
        @endif
        @if ($company_reliability != null)
        <div class="light-box p--20 mt-20">
            <div class="">
                <h5 class="fs-14 fw-semibold text-dark mb-2">
                    {{ translate('Why_shop_with_us ?') }}
                </h5>
                <div class="footer-sliders d-flex flex-wrap gap-2">
                    @foreach ($company_reliability as $key => $value)
                        @if ($value['status'] == 1 && !empty($value['title']))
                            <div class="bg-white py-2 px-2 rounded d-flex align-items-center gap-2">
                                <img class="order-summery-footer-image" alt=""
                                     src="{{ getStorageImages(path: imagePathProcessing(imageData: $value['image'], path: 'company-reliability'), type: 'source', source: theme_asset(path: 'public/assets/front-end/img') . '/' . $value['item'] . '.png') }}">
                                <div class="deal-title">{{ translate($value['title']) }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <div class="pt-4">
            @if (str_contains(request()->url(), 'checkout-payment'))
                <label class="custom-control custom-checkbox mb-3 d-flex user-select-none cursor-pointer">
                    <input type="checkbox" class="custom-control-input payment-input-checkbox">
                    <span class="custom-control-label">
                <span>{{ translate('i_agree_to_Your') }}</span>
                <a class="font-size-sm text-primary d-inline" target="_blank"
                   href="{{ route('business-page.view', ['slug' => 'terms-and-conditions']) }}">
                    {{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'الشروط والأحكام وسياسة الخصوصية' : 'Terms and Conditions and Privacy Policy' }}
                </a>
                @foreach($web_config['business_pages']->where('default_status', 1) as $businessPage)
                            @if($businessPage['slug'] == 'refund-policy')
                                <a class="font-size-sm text-primary d-inline" target="_blank"
                                   href="{{ route('business-page.view', ['slug' => $businessPage['slug']]) }}">
                            , {{ translate(str_replace('-', '_', $businessPage['slug'])) }}
                        </a>
                            @endif
                        @endforeach
                </span>
                </label>
            @endif

            <a class="btn btn--primary btn-block proceed_to_next_button {{ $cart->count() <= 0 ? 'custom-disabled' : '' }} action-checkout-function">
                {{ translate('proceed_to_Checkout') }}
            </a>
        </div>

        <div class="d-flex justify-content-center mt-3">
            <a href="{{ route('home') }}" class="d-flex align-items-center gap-2 text-primary font-weight-bold">
                <i class="tio-back-ui fs-12"></i> {{ translate('continue_Shopping') }}
            </a>
        </div>

    </div>
</aside>

<div class="bottom-sticky3 bg-white p-3 shadow-sm w-100 d-lg-none">
    <div class="d-flex justify-content-center align-items-center fs-14 mb-2">
        <div class="product-description-label fw-semibold text-capitalize">{{ translate('total_price') }} :</div>
        &nbsp; <strong class="text-base">
            {{ webCurrencyConverter(amount: $subTotal + ($totalTax['item_tax'] + $totalTax['shipping_cost_tax']) + $totalShippingCost - $coupon_dis - $totalDiscountOnProduct) }}
        </strong>
    </div>

    @if (str_contains(request()->url(), 'checkout-payment'))
        <label class="custom-control custom-checkbox mb-3 d-flex user-select-none cursor-pointer">
            <input type="checkbox" class="custom-control-input payment-input-checkbox">
            <span class="custom-control-label">
                <span>{{ translate('i_agree_to_Your') }}</span>
                <a class="font-size-sm text-primary d-inline" target="_blank"
                   href="{{ route('business-page.view', ['slug' => 'terms-and-conditions']) }}">
                    {{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'الشروط والأحكام وسياسة الخصوصية' : 'Terms and Conditions and Privacy Policy' }}
                </a>
                @foreach($web_config['business_pages']->where('default_status', 1) as $businessPage)
                    @if($businessPage['slug'] == 'refund-policy')
                        <a class="font-size-sm text-primary d-inline" target="_blank"
                           href="{{ route('business-page.view', ['slug' => $businessPage['slug']]) }}">
                            , {{ translate(str_replace('-', '_', $businessPage['slug'])) }}
                        </a>
                    @endif
                @endforeach
                </span>
        </label>
    @endif

    <a data-route="{{ Route::currentRouteName() }}"
       class="btn btn--primary btn-block proceed_to_next_button text-capitalize {{ $cart->count() <= 0 ? 'custom-disabled' : '' }} action-checkout-function">
        {{ translate('proceed_to_checkout') }}
    </a>
</div>

@push('script')
    <script>
        "use strict";
        $(document).ready(function() {
            orderSummaryStickyFunction()
        });
    </script>
@endpush
