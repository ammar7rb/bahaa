<nav aria-label="{{ translate('navigation') }}" class="care-bottom-nav">
    <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>@include('theme-views.partials._care-icon',['icon'=>'home.png'])<span>{{ translate('home') }}</span></a>
    <a href="{{ route('categories') }}">@include('theme-views.partials._care-icon',['icon'=>'category.png'])<span>{{ translate('categories') }}</span></a>
    <button type="button" class="care-cart-bottom-trigger" data-care-cart-open aria-label="{{ translate('cart') }}">@include('theme-views.partials._care-icon',['icon'=>'cart.png'])<span>{{ translate('cart') }}</span></button>
    <a href="{{ auth('customer')->check() ? route('wishlists') : '#' }}" @if(!auth('customer')->check()) data-bs-toggle="modal" data-bs-target="#loginModal" @endif>@include('theme-views.partials._care-icon',['icon'=>'wishlist.png'])<span>{{ translate('wishlist') }}</span></a>
    <a href="{{ auth('customer')->check() ? route('user-profile') : '#' }}" @if(!auth('customer')->check()) data-bs-toggle="modal" data-bs-target="#loginModal" @endif>@include('theme-views.partials._care-icon',['icon'=>'user.png'])<span>{{ translate('profile') }}</span></a>
</nav>
