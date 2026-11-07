@extends('theme-views.layouts.app')
@section('title', $web_config['meta_title'])
@section('content')
    @php
        $careArabic = getLanguageCode(country_code: getDefaultLanguage()) === 'ar';
        $orderSuccessIds = session('order_success_ids');
        $isNewCustomerInSession = session('isNewCustomerInSession');
        session()->forget(['order_success_ids', 'isNewCustomerInSession']);
        $careSections = collect($homepageLayout['sections'] ?? [])->filter(fn($section) => $section['is_visible'])->sortBy('sort_order');
        $careProductSections = [
            'savings_offers' => [app(\App\Services\HomepageSectionBuilderService::class)->resolveProducts(theme_root_path(), 'savings_offers', collect(), $homepageLayout), $careArabic ? 'عروض التوفير' : 'Savings Offers', route('products'), $careArabic ? 'كل المنتجات المتاحة في مكان واحد.' : 'All available products in one place.'],
            'latest_products' => [$latestProductsList, translate('latest_products'), route('latest-products'), $careArabic ? 'اكتشف أحدث الإضافات إلى متجر سيجما.' : 'Explore the latest additions to SIGMA.'],
            'featured_products' => [$featuredProductsList, translate('featured_products'), route('featured-products'), $careArabic ? 'المنتجات المميزة في مكان واحد.' : 'Discover our featured products.'],
            'featured_deals' => [$careSavingsProducts, $careArabic ? 'اشتري ووفر' : 'Shop & Save', route('discounted-products'), $careArabic ? 'تصفح المنتجات المتاحة بأسعار مخفضة.' : 'Explore products with current discounts.'],
        ];
    @endphp
    @include('theme-views.partials._order-success-modal', compact('orderSuccessIds', 'isNewCustomerInSession'))
    <main class="main-content care-home">
        @foreach($careSections as $sectionKey => $section)
            <div data-homepage-section="{{ $sectionKey }}">
            @if($sectionKey === 'main_banner')
                <section class="container care-hero-section">
                    <div class="care-hero">
                        <div class="care-hero-copy">
                            <h1>{{ $careArabic ? 'كل ما تحتاجه.' : 'Everything you need.' }}<br><span>{{ $careArabic ? 'لرعاية أفضل.' : 'For better care.' }}</span></h1>
                            <p>{{ $careArabic ? 'تصفح المستلزمات الطبية وكروت الاختبار من سيجما.' : 'Explore medical supplies and diagnostic tests from SIGMA.' }}</p>
                            <a class="btn btn-primary care-hero-cta" href="{{ route('products') }}">{{ translate('shop_now') }}<i class="bi bi-arrow-{{ $careArabic ? 'left' : 'right' }}"></i></a>
                        </div>
                        <div class="care-hero-media">
                            @if(count($bannerTypeMainBanner))
                                <div class="swiper" data-swiper-loop="{{ count($bannerTypeMainBanner) > 1 ? 'true' : 'false' }}" data-swiper-navigation-next="null" data-swiper-navigation-prev="null"><div class="swiper-wrapper">@foreach($bannerTypeMainBanner as $banner)<a class="swiper-slide" href="{{ $banner['url'] ?: route('products') }}"><img src="{{ getStorageImages(path:$banner->photo_full_url,type:'banner') }}" alt="{{ $banner['title'] ?: $web_config['company_name'] }}" @if(!$loop->first) loading="lazy" @endif width="720" height="390"></a>@endforeach</div></div>
                            @else
                                <img src="{{ theme_asset('assets/care/sigma-medical-essentials.webp') }}" alt="{{ $careArabic ? 'مستلزمات سيجما الطبية' : 'SIGMA medical supplies' }}" width="720" height="390" fetchpriority="high">
                            @endif
                        </div>
                    </div>
                </section>
            @elseif($sectionKey === 'categories')
                <nav class="container care-category-shortcuts" aria-label="{{ translate('categories') }}">
                    @foreach(\App\Utils\CategoryManager::getCategoriesWithCountingAndPriorityWiseSorting() as $category)
                        <a href="{{ route('category-products',['slug'=>$category['slug']]) }}"><span class="care-category-circle">@if($category->icon_full_url && $category->icon_full_url['status'] == 200)<img src="{{ getStorageImages(path:$category->icon_full_url,type:'category') }}" alt="" width="36" height="36" loading="lazy">@else @include('theme-views.partials._care-icon',['icon'=>'category.png']) @endif</span><span>{{ $category['name'] }}</span></a>
                    @endforeach
                </nav>
            @elseif(isset($careProductSections[$sectionKey]))
                @php([$configuredProducts, $configuredTitle, $configuredRoute, $configuredDescription] = $careProductSections[$sectionKey])
                @if($section['source_mode'] === 'manual')
                    @php($configuredRoute = route('products'))
                    @if($sectionKey === 'featured_deals')
                        @php($configuredDescription = $careArabic ? 'كل مستلزماتك الطبية في مكان واحد.' : 'All your medical essentials in one place.')
                    @endif
                @endif
                @php($configuredTitle = data_get($section, 'settings.title') ?: $configuredTitle)
                @include('theme-views.partials._homepage-configured-products', compact('configuredProducts','configuredTitle','configuredRoute','configuredDescription','sectionKey'))
            @elseif(str_starts_with($sectionKey, 'custom_') || in_array($sectionKey, ['best_selling', 'top_rated']))
                @php($configuredProducts = app(\App\Services\HomepageSectionBuilderService::class)->resolveProducts(theme_root_path(), $sectionKey, collect(), $homepageLayout))
                @php($configuredTitle = data_get($section, 'settings.title') ?: translate($sectionKey))
                @php($configuredRoute = route('products'))
                @php($configuredDescription = '')
                @php($configuredTitle = data_get($section, 'settings.title') ?: $configuredTitle)
                @include('theme-views.partials._homepage-configured-products', compact('configuredProducts','configuredTitle','configuredRoute','configuredDescription','sectionKey'))
            @elseif($sectionKey === 'main_section_banner')
                @include('theme-views.partials._care-promotions')
            @elseif($sectionKey === 'footer_banners')
                @if(count($bannerTypeFooterBanner))<section class="container care-footer-banners">@foreach($bannerTypeFooterBanner as $banner)<a href="{{ $banner['url'] ?: route('products') }}"><img src="{{ getStorageImages(path:data_get($banner, 'photo_full_url'),type:'wide-banner') }}" alt="{{ $banner['title'] ?: translate('offers') }}" loading="lazy"></a>@endforeach</section>@endif
            @endif
            </div>
        @endforeach
        <section class="container care-contact-section"><div class="care-contact-band"><div><h2>{{ translate('track_order') }}</h2><p>{{ $careArabic ? 'تابع طلبك وتواصل معنا في أي خطوة.' : 'Track your order and contact us whenever you need help.' }}</p></div><div class="d-flex gap-3 flex-wrap"><a class="btn care-reverse-button" href="{{ route('track-order.index') }}">{{ translate('track_order') }}</a><a class="btn care-contact-button" href="#careSupportPanel" data-bs-toggle="offcanvas" aria-controls="careSupportPanel">{{ translate('contact_us') }}</a></div></div></section>
    </main>
@endsection
@push('script')
    @if(Request::is('/') && !Cookie::has('popup_banner') && empty($orderSuccessIds) && $web_config['popup_banner'])
        <script>document.addEventListener('DOMContentLoaded',function(){const modal=document.getElementById('initialModal');if(modal)new bootstrap.Modal(modal).show();});</script>
        @php(Cookie::queue('popup_banner', 'off', 1))
    @endif
    @if($orderSuccessIds)<script>document.addEventListener('DOMContentLoaded',function(){const modal=document.getElementById('order_successfully');if(modal)new bootstrap.Modal(modal).show();});</script>@endif
@endpush
