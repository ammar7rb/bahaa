@php
    $careCategories = \App\Utils\CategoryManager::getCategoriesWithCountingAndPriorityWiseSorting();
    $careArabic = getLanguageCode(country_code: getDefaultLanguage()) === 'ar';
    $careCustomer = auth('customer')->check();
@endphp
@if(isset($web_config['announcement']) && $web_config['announcement']['status'] == 1)
    <div class="offer-bar announcement-color py-2 d--none"><div class="container d-flex align-items-center gap-3"><button class="offer-bar-close border-0 bg-transparent" aria-label="{{ translate('close') }}"><i class="bi bi-x-lg"></i></button><span class="top-offer-text flex-grow-1 text-center">{{ $web_config['announcement']['announcement'] }}</span></div></div>
@endif
@if($web_config['header_banner'])
    <div class="container care-header-banner"><a href="{{ $web_config['header_banner']['url'] ?: route('products') }}"><img src="{{ getStorageImages(path:$web_config['header_banner']['photo_full_url'],type:'wide-banner') }}" alt="{{ $web_config['header_banner']['title'] ?: translate('offers') }}"></a></div>
@endif
<header class="header care-header">
    <div class="container care-masthead">
        <a class="logo care-logo" href="{{ route('home') }}"><img class="ba-platform-logo" src="{{ getStorageImages(path: $web_config['web_logo'], type:'logo') }}" width="164" height="52" alt="{{ $web_config['company_name'] ?: 'SIGMA' }}"></a>
        <div class="search-box care-search position-relative">
            <form action="{{ route('products') }}" method="GET" role="search" class="d-flex align-items-center">
                <label class="visually-hidden" for="global-search">{{ translate('search_for_items') }}</label>
                <input type="search" class="form-control border-0 search-bar-input" id="global-search" name="product_name" value="{{ request('product_name') }}" placeholder="{{ translate('search_for_items') }}…" autocomplete="off">
                <input type="hidden" id="search_category_value" name="category_id" value="all"><input type="hidden" name="data_from" value="search"><input type="hidden" name="global_search_input" value="1"><input type="hidden" name="page" value="1">
                <button type="submit" class="btn btn-primary care-search-submit" aria-label="{{ translate('Search') }}">@include('theme-views.partials._care-icon',['icon'=>'search.png'])</button>
            </form>
            <div class="card shadow-none search-card search-result-box position-absolute z-99 w-100 bg-white top-100 start-0" style="display:none"></div>
        </div>
        <div class="care-account-actions">
            <a class="care-header-action" href="{{ route('user-profile') }}" aria-label="{{ $careArabic ? 'حسابي' : 'Account' }}">@include('theme-views.partials._care-icon',['icon'=>'user.png'])<span>{{ $careArabic ? 'حسابي' : 'Account' }}</span></a>
            <a class="care-header-action" aria-label="{{ translate('wishlist') }}" href="{{ $careCustomer ? route('wishlists') : '#' }}" @if(!$careCustomer) data-bs-toggle="modal" data-bs-target="#loginModal" @endif>@include('theme-views.partials._care-icon',['icon'=>'wishlist.png'])<span>{{ translate('wishlist') }}</span><span class="care-count wishlist_count_status">{{ session()->has('wish_list') ? count(session('wish_list')) : 0 }}</span></a>
            <div id="cart_items" class="care-header-cart">@include('theme-views.layouts.partials._cart')</div>
            <div class="dropdown care-language-picker">
                <button type="button" class="care-header-action care-language-button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ $careArabic ? 'اختيار اللغة' : 'Choose language' }}" title="{{ $careArabic ? 'اختيار اللغة' : 'Choose language' }}">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18M4.5 7.5h15M4.5 16.5h15"/></svg>
                    <span>{{ $careArabic ? 'العربية' : 'English' }}</span>
                </button>
                <ul class="dropdown-menu care-language-menu">
                    @foreach($web_config['language'] as $language)
                        @if($language['status'] == 1)
                            <li class="change-language" data-action="{{ route('change-language') }}" data-language-code="{{ $language['code'] }}"><button type="button" class="care-language-option" lang="{{ $language['code'] }}" @if($language['code'] === getLanguageCode(country_code:getDefaultLanguage())) aria-current="true" @endif><span>{{ $language['code'] === 'ar' ? 'العربية' : ucwords($language['name']) }}</span>@if($language['code'] === getLanguageCode(country_code:getDefaultLanguage()))<i class="bi bi-check2" aria-hidden="true"></i>@endif</button></li>
                        @endif
                    @endforeach
                    @if($web_config['currency_model'] === 'multi_currency')
                        <li class="care-language-divider">{{ $careArabic ? 'العملة' : 'Currency' }}</li>
                        @foreach($web_config['currencies'] as $currency)<li class="currency-change" data-currency-code="{{ $currency['code'] }}"><button type="button" class="care-language-option">{{ $currency->name }}</button></li>@endforeach
                    @endif
                </ul>
                @if($web_config['currency_model'] === 'multi_currency')<span id="currency-route" data-currency-route="{{ route('currency.change') }}"></span>@endif
            </div>
            <div class="dropdown care-merchant-picker">
                <button type="button" class="care-header-action" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ $careArabic ? 'منطقة التاجر' : 'Merchant area' }}">
                    @include('theme-views.partials._care-icon',['icon'=>'vendor_icon.svg'])
                    <span>{{ $careArabic ? 'منطقة التاجر' : 'Merchant area' }}</span>
                </button>
                <ul class="dropdown-menu care-language-menu care-merchant-menu">
                    <li><a class="care-language-option" href="{{ route('vendor.auth.registration.index') }}">{{ $careArabic ? 'انضم كتاجر' : 'Join as a merchant' }}</a></li>
                    <li><a class="care-language-option" href="{{ route('vendor.auth.login') }}">{{ $careArabic ? 'تسجيل الدخول للتاجر' : 'Merchant sign in' }}</a></li>
                </ul>
            </div>
        </div>
    </div>
    <nav class="care-navigation" aria-label="{{ translate('navigation') }}"><div class="container d-flex align-items-center gap-3">
        <div class="dropdown care-departments"><button class="btn btn-primary d-flex align-items-center gap-2 dropdown-toggle" type="button" data-care-dropdown data-bs-display="static" aria-controls="care-departments-panel" aria-expanded="false">@include('theme-views.partials._care-icon',['icon'=>'category.png']) {{ $careArabic ? 'كل الأقسام' : 'All categories' }}</button><ul class="dropdown-menu" id="care-departments-panel">@foreach($careCategories as $category)<li><a href="{{ route('category-products',['slug'=>$category['slug']]) }}"><img src="{{ getStorageImages(path:$category->icon_full_url,type:'category') }}" width="26" height="26" alt="" loading="lazy"><span>{{ $category['name'] }}</span></a>@if($category->childes->count())<ul class="care-subcategories">@foreach($category->childes as $child)<li><a href="{{ route('category-products',['slug'=>$child['slug']]) }}">{{ $child['name'] }}</a>@foreach($child->childes as $grandchild)<a class="care-subcategory" href="{{ route('category-products',['slug'=>$grandchild['slug']]) }}">{{ $grandchild['name'] }}</a>@endforeach</li>@endforeach</ul>@endif</li>@endforeach<li><a class="care-all-categories" href="{{ route('categories') }}">{{ translate('view_all') }}</a></li></ul></div>
        <div class="care-nav-links">
        <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>{{ translate('home') }}</a>
        <a href="{{ route('products') }}">{{ translate('products') }}</a>
        <a href="{{ route('discounted-products') }}">{{ translate('offers') }}</a>
        <a href="{{ route('business-page.view', ['slug' => 'medical-devices-accessories']) }}" @if(request()->routeIs('business-page.view') && request()->route('slug') === 'medical-devices-accessories') aria-current="page" @endif>{{ $careArabic ? 'الأجهزة الطبية وملحقاتها' : 'Medical Devices & Accessories' }}</a>
        <a href="{{ route('business-page.view', ['slug' => 'veterinary-services']) }}" @if(request()->routeIs('business-page.view') && request()->route('slug') === 'veterinary-services') aria-current="page" @endif>{{ $careArabic ? 'خدمات بيطرية' : 'Veterinary Services' }}</a>
        <a href="{{ route('buy-now-pay-later') }}" @if(request()->routeIs('buy-now-pay-later')) aria-current="page" @endif>{{ $careArabic ? 'اشترِ الآن وادفع لاحقًا' : 'Buy Now, Pay Later' }}</a>
        <a href="#careSupportPanel" data-bs-toggle="offcanvas" aria-controls="careSupportPanel">{{ translate('contact_us') }}</a>
        </div>
    </div></nav>
</header>
