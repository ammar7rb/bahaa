@php
    $cartItems = \App\Utils\CartManager::getCartListQuery();
    $isProductNullStatus = 0;
@endphp
<div class="container care-basket">
    @include('medical-devices.cart-reservations')
    <header class="care-basket-heading"><div><h1>سلة المشتريات</h1><p>راجع المنتجات والكميات قبل اختيار عنوان التوصيل.</p></div><a href="{{ route('home') }}">مواصلة التسوق @include('customer-subscription.icon',['name'=>'arrow'])</a></header>
    <ol class="care-checkout-steps" aria-label="خطوات الطلب"><li aria-current="step"><span>1</span> السلة</li><li><span>2</span> العنوان والشحن</li><li><span>3</span> الدفع</li></ol>
    <div class="row gy-4 care-basket-columns">
        <section class="col-lg-8" aria-label="المنتجات">
            <div class="care-basket-list cart_information">
                @if($cartItems->isNotEmpty())
                    <div class="care-basket-toolbar"><label><input type="checkbox" class="shop-head-check shop-head-check-desktop"> تحديد الكل</label><span>{{ $cartItems->count() }} منتج</span></div>
                    @foreach($cartItems as $item)
                        @php
                            $product = $item->allProducts;
                            $stock = $item->product_type === 'digital' ? PHP_INT_MAX : (int)($product?->current_stock ?? 0);
                            foreach (json_decode($product?->variation ?? '[]',true) ?: [] as $variant) {
                                if (($variant['type'] ?? '') === $item->variant) $stock = (int)$variant['qty'];
                            }
                            $available = (bool)$product?->status;
                            if ($item->seller_is === 'admin' && (checkVendorAbility(type:'inhouse',status:'temporary_close') || checkVendorAbility(type:'inhouse',status:'vacation_status'))) $available = false;
                            if ($item->seller_is === 'seller' && $product && (checkVendorAbility(type:'vendor',status:'temporary_close',vendor:$product->seller?->shop) || checkVendorAbility(type:'vendor',status:'vacation_status',vendor:$product->seller?->shop))) $available = false;
                            $inStock = $item->product_type !== 'physical' || $stock >= $item->quantity;
                            $minimum = max(1,(int)($product?->minimum_order_qty ?? 1));
                            if (!$available || !$inStock) $isProductNullStatus = 1;
                        @endphp
                        <article class="care-basket-item">
                            <input type="checkbox" class="shop-item-check shop-item-check-desktop" value="{{ $item->id }}" @checked($item->is_checked) aria-label="تحديد {{ $item->name }}">
                            <a class="care-basket-image" href="{{ $product ? route('product',$product->slug) : '#' }}" tabindex="-1" aria-hidden="true"><img src="{{ getStorageImages(path:$item->product?->thumbnail_full_url,type:'product') }}" alt="" loading="lazy" width="88" height="88"></a>
                            <div class="care-basket-product"><h2>@if($product)<a href="{{ route('product',$product->slug) }}">{{ $item->name }}</a>@else{{ $item->name }}@endif</h2>@if($item->variant)<p>{{ $item->variant }}</p>@endif<p>سعر الوحدة <bdi>{{ webCurrencyConverter($item->price-$item->discount) }}</bdi></p>@if(!$available)<span class="care-basket-warning">المنتج غير متاح حاليًا</span>@elseif(!$inStock)<span class="care-basket-warning">الكمية تتجاوز المتاح في المخزون</span>@endif</div>
                            <div class="care-basket-quantity"><label class="visually-hidden" for="cartQuantityWeb{{ $item->id }}">كمية {{ $item->name }}</label><div class="care-basket-stepper"><button type="button" class="update-cart-quantity-list-cart-data" data-cart="{{ $item->id }}" data-min-order="{{ $minimum }}" data-value="-1" data-action="{{ $item->quantity <= $minimum ? 'delete' : 'minus' }}" aria-label="تقليل كمية {{ $item->name }}" @disabled(!$available)>−</button><input type="text" inputmode="numeric" pattern="[0-9]*" class="update-cart-quantity-list-cart-data-input cartQuantity{{ $item->id }}" id="cartQuantityWeb{{ $item->id }}" name="quantity" value="{{ $item->quantity }}" data-min-order="{{ $minimum }}" data-min="{{ $minimum }}" data-cart="{{ $item->id }}" data-value="0" data-action="" data-current-stock="{{ $stock }}" @disabled(!$available)><button type="button" class="update-cart-quantity-list-cart-data" data-cart="{{ $item->id }}" data-min-order="{{ $minimum }}" data-value="1" data-action="plus" aria-label="زيادة كمية {{ $item->name }}" @disabled(!$available)>+</button></div><button type="button" class="care-basket-remove" data-care-cart-remove="{{ $item->id }}" aria-label="حذف {{ $item->name }}">@include('customer-subscription.icon',['name'=>'close']) إزالة</button></div>
                            <strong class="care-basket-line-total"><bdi>{{ webCurrencyConverter(($item->price-$item->discount)*$item->quantity) }}</bdi></strong>
                        </article>
                    @endforeach
                    @foreach($cartItems->groupBy('cart_group_id') as $groupId=>$items)
                        @php($minimumStatus=\App\Utils\OrderManager::verifyCartListMinimumOrderAmount(request(),$groupId))
                        @if($minimumStatus['minimum_order_amount'] > $minimumStatus['amount'])<p class="care-basket-warning care-basket-group-note">بعض المنتجات لم تبلغ الحد الأدنى للطلب: {{ webCurrencyConverter($minimumStatus['minimum_order_amount']) }}.</p>@endif
                    @endforeach
                @else
                    <div class="care-basket-empty">@include('customer-subscription.icon',['name'=>'orders'])<h2>سلتك فارغة</h2><p>أضف المنتجات التي تحتاجها للبدء.</p><a class="btn btn-primary" href="{{ route('home') }}">تصفح المنتجات</a></div>
                @endif
            </div>
        </section>
        @include('theme-views.partials._order-summery',['shippingPending'=>true])
    </div>
</div>
@push('script')
<script src="{{ theme_asset('assets/js/cart.js') }}"></script>
<script src="{{ asset('assets/front-end/js/care-basket.js') }}?v=20261017.1"></script>
@endpush
