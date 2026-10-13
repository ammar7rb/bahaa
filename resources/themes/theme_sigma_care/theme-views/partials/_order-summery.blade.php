@php
    session()->forget(['coupon_code','coupon_discount','coupon_type','coupon_bearer','coupon_seller_id','order_note']);
    use App\Utils\CartManager;
    use App\Utils\Helpers;
@endphp
<div class="col-lg-4">
    <div class="card text-dark sticky-top-80">
        <div class="card-body px-sm-4 d-flex flex-column gap-3">
            @php($systemTaxConfig = getTaxModuleSystemTypesConfig())
            @php($current_url = request()->segment(count(request()->segments())))
            @php($shippingMethod = getWebConfig(name: 'shipping_method'))
            @php($product_price_total = 0)
            @php($totalTax = \App\Utils\CartManager::getCartListTaxAmount())
            @php($total_shipping_cost = 0)
            @php($total_discount_on_product = 0)
            @php($cart = CartManager::getCartListQuery(type: 'checked'))
            @php($cartAll = CartManager::getCartListQuery())
            @php($cart_group_ids = CartManager::get_cart_group_ids())
            @php($shipping_cost = CartManager::get_shipping_cost(type: 'checked'))
            @php($get_shipping_cost_saved_for_free_delivery = CartManager::getShippingCostSavedForFreeDelivery(type: 'checked'))
            @if ($cart->count() > 0)
                @foreach ($cart as $key => $cartItem)
                    @php($product_price_total += $cartItem['price'] * $cartItem['quantity'])
                    @php($total_discount_on_product += $cartItem['discount'] * $cartItem['quantity'])
                @endforeach

                @if (session()->missing('coupon_type') || session('coupon_type') != 'free_delivery')
                    @php($total_shipping_cost = $shipping_cost - $get_shipping_cost_saved_for_free_delivery)
                @else
                    @php($total_shipping_cost = $shipping_cost)
                @endif
            @endif

            @if ($cartAll->count() > 0 && $cart->count() == 0)
                <span>{{ translate('Please_checked_items_before_proceeding_to_checkout') }}</span>
            @elseif($cartAll->count() == 0)
                <span>{{ translate('empty_cart') }}</span>
            @endif

            <h4 class="text-capitalize mb-0">{{ translate('order_summary') }}</h4>
            @php($coupon_discount = 0)
            @php($coupon_dis = 0)
            @include('web-views.users-profile.partials.purchasing-limit-strip', ['limitVariant'=>'cart'])
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                <div class="opacity-75 text-capitalize">{{ translate('item_price') }}</div>
                <div class="fw-semibold">{{ webCurrencyConverter($product_price_total) }}</div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                <div class="opacity-75 text-capitalize">{{ translate('shipping') }}</div>
                <div class="fw-semibold">{{ webCurrencyConverter($total_shipping_cost) }}</div>
            </div>

            @php($coupon_discount = 0)
            @php($coupon_dis = 0)

            <?php
            $totalAmount = $product_price_total + ($totalTax['item_tax'] + $totalTax['shipping_cost_tax']) + $total_shipping_cost - $coupon_dis - $total_discount_on_product;
            $referralAmount = \App\Utils\CustomerManager::getReferralDiscountAmount(
                user: (auth('customer')->check() ? auth('customer')->user() : null),
                couponDiscount: $coupon_dis
            );
            ?>

            @if ($referralAmount > 0)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                    <div class="opacity-75 text-capitalize">{{ translate('referral_discount') }}</div>
                    <div class="fw-semibold">{{ '-' . webCurrencyConverter($referralAmount) }}</div>
                </div>
            @endif

            @if($systemTaxConfig['SystemTaxVat']['is_active'] && !$systemTaxConfig['is_included'])
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                    <div class="opacity-75 text-capitalize">{{ translate('tax') }}</div>
                    <div class="fw-semibold">{{ webCurrencyConverter(amount: ($totalTax['item_tax'] + $totalTax['shipping_cost_tax'])) }}</div>
                </div>
            @endif

            <hr class="m-0" />

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                <h5>
                    {{ translate('total') }}
                    @if($systemTaxConfig['SystemTaxVat']['is_active'] && $systemTaxConfig['is_included'])
                        <span class="fs-12 fw-semibold">({{ translate('Tax_:_Inc.') }})</span>
                    @endif
                </h5>
                <h4 class="text-primary">{{ webCurrencyConverter($totalAmount - $referralAmount) }}</h4>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                @if (str_contains(request()->url(), 'checkout-payment'))
                    <label class="custom-control custom-checkbox mb-3 d-flex user-select-none cursor-pointer">
                        <input type="checkbox" class="custom-control-input payment-input-checkbox">
                        <span class="custom-control-label">
                    <span>{{ translate('i_agree_to_Your') }}</span>
                    <span>{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'الشروط والأحكام وسياسة الخصوصية' : 'Terms and Conditions and Privacy Policy' }}</span>
                    @foreach($web_config['business_pages']->where('default_status', 1) as $businessPage)
                                @if($businessPage['slug'] == 'refund-policy')
                                    <a class="font-size-sm text--primary fw-bold d-inline" target="_blank"
                                       href="{{ route('business-page.view', ['slug' => $businessPage['slug']]) }}">
                            , {{ translate(str_replace('-', '_', $businessPage['slug'])) }}
                            </a>
                                @endif
                            @endforeach
                    </span>
                    </label>
                @endif

                <a href="{{ route('home') }}" class="btn-link text-primary text-capitalize user-select-none fw-semibold">
                    <i class="fi fi-rr-angle-double-left fs-10"></i> {{ translate('continue_shopping') }}
                </a>

                @if (str_contains(request()->url(), 'checkout-payment'))
                    <button class="btn btn-primary text-capitalize custom-disabled" id="proceed-to-payment-action"
                            data-goto-checkout="{{ route('customer.choose-shipping-address-other') }}"
                            data-route="{{ route('checkout-payment') }}" data-type="{{ 'checkout-payment' }}"
                            {{ isset($isProductNullStatus) && $isProductNullStatus == 1 ? 'disabled' : '' }}
                            type="button">
                        {{ translate('proceed_to_payment') }}
                    </button>
                @else
                    <button class="btn btn-primary text-capitalize {{ $cart->count() <= 0 ? 'custom-disabled' : '' }}"
                            id="proceed-to-next-action"
                            data-goto-checkout="{{ route('customer.choose-shipping-address-other') }}"
                            data-checkout-payment="{{ route('checkout-payment') }}"
                            {{ isset($isProductNullStatus) && $isProductNullStatus == 1 ? 'disabled' : '' }}
                            type="button">
                        {{ translate('proceed_to_next') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
