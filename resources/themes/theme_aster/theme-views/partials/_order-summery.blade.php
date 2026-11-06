@php
    session()->forget(['coupon_code','coupon_discount','coupon_type','coupon_bearer','order_note']);
    use App\Utils\CartManager;
    use App\Utils\Helpers;
@endphp
<div class="col-lg-4">
    <div class="card text-dark sticky-top-80">
        <div class="card-body px-sm-4 d-flex flex-column gap-3">
            @php($systemTaxConfig = getTaxModuleSystemTypesConfig())
            @php($current_url = request()->segment(count(request()->segments())))
            @php($shippingPending = ($shippingPending ?? false) || ($current_url === 'checkout-details' && !($automatic_shipping_selected ?? false)))
            @php($shippingMethod = getWebConfig(name: 'shipping_method'))
            @php($product_price_total = 0)
            @php($totalTax = \App\Utils\CartManager::getCartListTaxAmount())
            @php($total_shipping_cost = 0)
            @php($total_discount_on_product = 0)
            @php($cart = CartManager::getCartListQuery(type: 'checked'))
            @php($shippingPending = $shippingPending && $cart->contains('product_type', 'physical'))
            @php($cartAll = CartManager::getCartListQuery())
            @php($cart_group_ids = CartManager::get_cart_group_ids())
            @php($shipping_cost = CartManager::get_shipping_cost(type: 'checked'))
            @php($get_shipping_cost_saved_for_free_delivery = CartManager::getShippingCostSavedForFreeDelivery(type: 'checked'))
            @if($shippingPending) @php($shipping_cost = 0) @php($get_shipping_cost_saved_for_free_delivery = 0) @php($totalTax['shipping_cost_tax'] = 0) @endif
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
            @include('web-views.users-profile.partials.purchasing-limit-strip')
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                <div class="opacity-75 text-capitalize">{{ translate('item_price') }}</div>
                <div class="fw-semibold">{{ webCurrencyConverter($product_price_total - $total_discount_on_product) }}</div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                <div class="opacity-75 text-capitalize">{{ translate('shipping') }}</div>
                <div class="fw-semibold" data-checkout-update="shipping">@if($shippingPending)<span class="fs-12">أكمل العنوان واختر طريقة الشحن</span>@else{{ webCurrencyConverter($total_shipping_cost) }}@endif</div>
            </div>

            <div data-checkout-update="tax" @if(array_sum($totalTax) <= 0) hidden @endif>@include('customer-checkout.tax-breakdown')</div>

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


            <hr class="m-0" />

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                <h5 data-checkout-update="total-label">
                    {{ translate('total') }}
                    @if($systemTaxConfig['SystemTaxVat']['is_active'] && $systemTaxConfig['is_included'] && (float)$totalTax['item_tax'] === 0.0)
                        <span class="fs-12 fw-semibold">({{ translate('Tax_:_Inc.') }})</span>
                    @endif
                </h5>
                <h4 class="text-primary" data-checkout-update="total">@if($shippingPending) — @else{{ webCurrencyConverter($totalAmount - $referralAmount) }}@endif</h4>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <a href="{{ route('home') }}" class="btn-link text-primary text-capitalize user-select-none fw-semibold">
                    <i class="fi fi-rr-angle-double-left fs-10"></i> {{ translate('continue_shopping') }}
                </a>

                @if (str_contains(request()->url(), 'checkout-payment'))
                    <button class="btn btn-primary text-capitalize custom-disabled" id="proceed-to-payment-action"
                            data-goto-checkout="{{ route('customer.choose-shipping-address-other') }}"
                            data-route="{{ route('checkout-payment') }}" data-type="{{ 'checkout-payment' }}"
                            {{ isset($isProductNullStatus) && $isProductNullStatus == 1 ? 'disabled' : '' }}
                            type="button">
                        {{ translate('continue_to_payment') }}
                    </button>
                @else
                    <button class="btn btn-primary text-capitalize {{ $cart->count() <= 0 ? 'custom-disabled' : '' }}"
                            id="proceed-to-next-action"
                            data-goto-checkout="{{ route('customer.choose-shipping-address-other') }}"
                            data-checkout-payment="{{ route('checkout-payment') }}"
                            {{ isset($isProductNullStatus) && $isProductNullStatus == 1 ? 'disabled' : '' }}
                            type="button">
                        {{ translate('continue_to_payment') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
