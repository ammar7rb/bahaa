@php
    $cart = \App\Utils\CartManager::getCartListQuery();
    $cartSubtotal = $cart->sum(fn ($item) => ($item['price'] - $item['discount']) * (int) $item['quantity']);
    $cartArabic = getLanguageCode(country_code: getDefaultLanguage()) === 'ar';
@endphp
<style>
    .care-cart-trigger { border: 0; background: transparent; color: #172942; display: flex; flex-direction: column; align-items: center; gap: 3px; position: relative; min-width: 44px; min-height: 44px; padding: 0; font: inherit; }
    .care-cart-trigger > .bi { font-size: 24px; }
    .care-cart-trigger-label { font-size: 11px; }
    .sigma-care .care-cart-menu { width: 390px; max-width: calc(100vw - 32px); padding: 0; border: 1px solid #dce4f0; border-radius: 18px; background: #fff; color: #172942; overflow: hidden; max-height: none; box-shadow: 0 16px 44px rgba(23,41,66,.14); }
    .care-cart-heading { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid #e5ebf4; }
    .care-cart-heading h2 { font-size: 17px; font-weight: 700; margin: 0; }
    .care-cart-close { border: 0; background: #f4f7fc; border-radius: 10px; width: 36px; height: 36px; color: #52627a; font-size: 20px; }
    .care-cart-items { list-style: none; margin: 0; padding: 0 20px; max-height: min(42vh, 360px); overflow-y: auto; overscroll-behavior: contain; }
    .care-cart-item { display: flex; align-items: center; gap: 14px; padding: 16px 0; border-bottom: 1px solid #edf1f7; }
    .care-cart-item:last-child { border-bottom: 0; }
    .care-cart-item > img { width: 64px; height: 64px; border-radius: 12px; object-fit: contain; background: #f7f9fc; flex-shrink: 0; }
    .care-cart-item-content { flex: 1; min-width: 0; }
    .care-cart-item-content a { display: block; font-size: 14px; font-weight: 650; color: #172942; overflow-wrap: anywhere; line-height: 1.5; }
    .care-cart-item-meta { margin: 4px 0; color: #52627a; font-size: 12px; }
    .care-cart-item-price { color: #1d4287; font-size: 14px; font-weight: 700; }
    .care-cart-footer { padding: 18px 20px; background: #f7f9fc; border-top: 1px solid #e5ebf4; }
    .care-cart-total { display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 15px; font-weight: 700; margin-bottom: 6px; }
    .care-cart-note { color: #52627a; font-size: 12px; margin-bottom: 16px; }
    .care-cart-footer .btn { width: 100%; min-height: 46px; display: flex; align-items: center; justify-content: center; border-radius: 12px; }
    .care-cart-empty { text-align: center; padding: 28px 24px; }
    .care-cart-empty > .bi { display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 18px; background: #edf3fc; color: #1d4287; font-size: 30px; margin-bottom: 14px; }
    .care-cart-empty h3 { font-size: 16px; margin-bottom: 8px; }
    .care-cart-empty p { font-size: 13px; color: #52627a; }
    .care-bottom-nav .care-cart-bottom-trigger { display: flex; flex-direction: column; align-items: center; gap: 5px; border: 0; background: transparent; color: inherit; font: inherit; padding: 0; min-height: 44px; }
    @media (max-width: 767px) {
        .care-cart-trigger-label { display: none; }
        .sigma-care .care-cart-menu.show { position: fixed !important; inset: auto 16px 88px 16px !important; transform: none !important; width: auto; max-width: none; z-index: 1055; }
        .care-cart-items { max-height: 40vh; }
    }
</style>
<div class="dropdown care-cart-dropdown">
    <button type="button" id="care-cart-trigger" class="care-cart-trigger" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-controls="care-cart-menu" aria-label="{{ translate('cart') }}">
        <i class="bi bi-bag" aria-hidden="true"></i>
        <span class="care-cart-trigger-label">{{ translate('cart') }}</span>
        <span class="count">{{ $cart->count() }}</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end care-cart-menu" id="care-cart-menu" aria-labelledby="care-cart-trigger">
        <div class="care-cart-heading"><h2>{{ translate('cart') }} <span class="text-muted">({{ $cart->count() }})</span></h2><button type="button" class="care-cart-close" data-care-cart-close aria-label="{{ translate('close') }}"><i class="bi bi-x" aria-hidden="true"></i></button></div>
        @if($cart->isNotEmpty())
            <ul class="care-cart-items">
                @foreach($cart as $cartItem)
                    <li class="care-cart-item">
                        <img src="{{ getStorageImages(path: $cartItem?->product?->thumbnail_full_url, type: 'product') }}" alt="" loading="lazy">
                        <div class="care-cart-item-content">
                            <a href="{{ route('product', $cartItem['slug']) }}">{{ $cartItem['name'] }}</a>
                            @if(!empty($cartItem['variant']))<div class="care-cart-item-meta">{{ $cartItem['variant'] }}</div>@endif
                            <div class="care-cart-item-meta"><span class="cart_quantity_{{ $cartItem['id'] }}">{{ $cartItem['quantity'] }}</span> × {{ webCurrencyConverter($cartItem['price'] - $cartItem['discount']) }}</div>
                            <div class="care-cart-item-price">{{ webCurrencyConverter(($cartItem['price'] - $cartItem['discount']) * (int) $cartItem['quantity']) }}</div>
                            @if(!$cartItem->product || !$cartItem->product->status)<div class="care-cart-item-meta text-danger">{{ translate('not_available') }}</div>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="care-cart-footer">
                <div class="care-cart-total"><span>{{ translate('subtotal') }}</span><span class="cart_total_amount">{{ webCurrencyConverter($cartSubtotal) }}</span></div>
                <div class="care-cart-note">{{ $cartArabic ? 'راجع الشحن والضرائب في صفحة السلة.' : 'Review shipping and taxes on the cart page.' }}</div>
                <a href="{{ route('shop-cart') }}" class="btn btn-primary">{{ translate('view_cart') }} <i class="bi bi-arrow-{{ $cartArabic ? 'left' : 'right' }} ms-2" aria-hidden="true"></i></a>
            </div>
        @else
            <div class="care-cart-empty"><i class="bi bi-bag" aria-hidden="true"></i><h3>{{ translate('your_cart_is_empty!') }}</h3><p>{{ $cartArabic ? 'أضف المنتجات التي تحتاجها وستجدها هنا.' : 'Add the products you need and find them here.' }}</p><a class="btn btn-primary" href="{{ route('products') }}">{{ $cartArabic ? 'تصفح المنتجات' : 'Browse products' }}</a></div>
            <div class="care-cart-footer"><a href="{{ route('shop-cart') }}" class="btn btn-outline-primary">{{ translate('view_cart') }}</a></div>
        @endif
    </div>
</div>
<script>
    if (!window.careCartPopoverBound) {
        window.careCartPopoverBound = true;
        document.addEventListener('click', function (event) {
            var open = event.target.closest('[data-care-cart-open]');
            var close = event.target.closest('[data-care-cart-close]');
            if (!open && !close) return;
            var trigger = document.getElementById('care-cart-trigger');
            if (!trigger || !window.bootstrap) return;
            event.preventDefault();
            var dropdown = bootstrap.Dropdown.getOrCreateInstance(trigger);
            if (open) dropdown.toggle(); else dropdown.hide();
        });
    }
</script>
