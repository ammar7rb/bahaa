@extends('layouts.front-end.app')

@section('title', $web_config['meta_title'])

@push('css_or_js')
    <link rel="stylesheet" href="{{theme_asset(path: 'public/assets/front-end/css/home.css')}}"/>
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/css/owl.carousel.min.css') }}">
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/css/owl.theme.default.min.css') }}">
@endpush

@section('content')

<?php
    $orderSuccessIds = session('order_success_ids');
    $isNewCustomerInSession = session('isNewCustomerInSession');
    session()->forget('order_success_ids');
    session()->forget('isNewCustomerInSession');
?>
@include("web-views.partials._order-success-modal",['orderSuccessIds' => $orderSuccessIds,'isNewCustomerInSession' => $isNewCustomerInSession])

@php
    $homepageSection = fn (string $key) => data_get($homepageLayout ?? [], 'sections.'.$key, []);
    $homepageSectionVisible = fn (string $key) => (bool) data_get($homepageSection($key), 'is_visible', true);
    $homepageSectionOrder = fn (string $key) => (int) data_get($homepageSection($key), 'sort_order', 999);
@endphp

<div class="__inline-61 d-flex flex-column gap-20">
        @php($decimalPointSettings = !empty(getWebConfig(name: 'decimal_point_settings')) ? getWebConfig(name: 'decimal_point_settings') : 0)

        @if($homepageSectionVisible('main_banner'))
            <div data-homepage-section="main_banner" style="order: {{ $homepageSectionOrder('main_banner') }}">
                @include('web-views.partials._home-top-slider',['bannerTypeMainBanner'=>$bannerTypeMainBanner])
            </div>
        @endif

        @if ($homepageSectionVisible('flash_deals') && $flashDeal['flashDeal'] && $flashDeal['flashDealProducts'] && count($flashDeal['flashDealProducts']) > 0)
            <div data-homepage-section="flash_deals" style="order: {{ $homepageSectionOrder('flash_deals') }}">
                @include('web-views.partials._flash-deal', ['decimal_point_settings'=>$decimalPointSettings])
            </div>
        @endif

        @if($homepageSectionVisible('paid_ads'))
            <div data-homepage-section="paid_ads" style="order: {{ $homepageSectionOrder('paid_ads') }}">
                @include('web-views.partials._homepage-featured-offers')
            </div>
        @endif

        @if ($homepageSectionVisible('featured_products') && $featuredProductsList->count() > 0 )
            <div class="container" data-homepage-section="featured_products" style="order: {{ $homepageSectionOrder('featured_products') }}">
                <div class="__inline-62 section-card-margin">
                    <div class="d-flex justify-content-between align-items-baseline px-3 pt-3">
                        <h2 class="feature-product-title font-bold m-0 text-capitalize h5 letter-spacing-0">
                            {{ translate('featured_products') }}
                        </h2>
                        <div class="text-end d-none d-md-block">
                            <a class="text-capitalize view-all-btn-text" href="{{ route('featured-products') }}">
                                <span class="view-btn-text">{{ translate('View_All') }}</span>
                                <i class="czi-arrow-{{ session('direction') === "rtl" ? 'left' : 'right' }}"></i>
                            </a>
                        </div>
                    </div>
                    <div class="feature-product">
                        <div class="carousel-wrap p-1">
                            <div class="owl-carousel featured_products_listSlide owl-theme" data-slide-items="{{ count($featuredProductsList) }}">
                                @foreach($featuredProductsList as $product)
                                    <div>
                                        @include('web-views.partials._feature-product',['product'=>$product, 'decimal_point_settings'=>$decimalPointSettings])
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="text-center pt-2 d-md-none">
                            <a class="text-capitalize view-all-btn-text" href="{{ route('featured-products') }}">
                                <span class="view-btn-text">{{ translate('View_All') }}</span>
                                <i class="czi-arrow-{{ session('direction') === "rtl" ? 'left' : 'right' }}"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($homepageSectionVisible('categories'))
            <div data-homepage-section="categories" style="order: {{ $homepageSectionOrder('categories') }}">
                @include('web-views.partials._category-section-home')
            </div>
        @endif

         @if($homepageSectionVisible('featured_deals') && getFeaturedDealsProductList() && (count(getFeaturedDealsProductList()) > 0))
            <section class="featured_deal pb-3" data-homepage-section="featured_deals" style="order: {{ $homepageSectionOrder('featured_deals') }}">
                <div class="container">
                    <div class="__featured-deal-wrap bg--light px-0-mobile">
                        <div class="d-flex flex-wrap justify-content-between align-items-sm-start gap-8 mb-xxl-4 mb-3">
                            <div class="w-0 flex-grow-1">
                                <span class="featured_deal_title font-bold text-dark">{{ translate('featured_deal')}}</span>
                                <br>
                                <span class="text-left">{{ translate('see_the_latest_deals_and_exciting_new_offers')}}!</span>
                            </div>
                            <div>
                                <a class="text-capitalize view-all-btn-text" href="{{ route('featured-deal-products') }}">
                                    <span class="view-btn-text">{{ translate('View_All') }}</span>
                                    <i class="czi-arrow-{{ session('direction') === "rtl" ? 'left' : 'right' }}"></i>
                                </a>
                            </div>
                        </div>
                        <div class="owl-carousel owl-theme new-arrivals-product" data-slide-items="{{ count(getFeaturedDealsProductList()) }}">
                           @foreach(getFeaturedDealsProductList() as $key=>$product)
                                @include('web-views.partials._product-card-1',['product'=>$product, 'decimal_point_settings'=>$decimalPointSettings])
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
         @endif

        @if($homepageSectionVisible('clearance_sale'))
            <div data-homepage-section="clearance_sale" style="order: {{ $homepageSectionOrder('clearance_sale') }}">
                @include('web-views.partials._clearance-sale-products', ['clearanceSaleProducts' => $clearanceSaleProducts])
            </div>
        @endif

        @if ($homepageSectionVisible('main_section_banner') && isset($bannerTypeMainSectionBanner))
            <div class="container rtl pt-0 px-0 px-md-3" data-homepage-section="main_section_banner" style="order: {{ $homepageSectionOrder('main_section_banner') }}">
                <a href="{{$bannerTypeMainSectionBanner->url}}" target="_blank"
                    class="cursor-pointer d-block">
                    <img loading="lazy" class="d-block footer_banner_img __inline-63" alt=""
                         src="{{ getStorageImages(path:$bannerTypeMainSectionBanner->photo_full_url, type: 'wide-banner') }}">
                </a>
            </div>
        @endif

        {{-- Seller and store discovery is intentionally hidden from customers. --}}

        @if($homepageSectionVisible('deal_of_day'))
            <div data-homepage-section="deal_of_day" style="order: {{ $homepageSectionOrder('deal_of_day') }}">
                @include('web-views.partials._deal-of-the-day', ['decimal_point_settings' => $decimalPointSettings])
            </div>
        @endif

            @if ($homepageSectionVisible('latest_products') && $newArrivalProducts->count() >0 )
                <section class="new-arrival-section" data-homepage-section="latest_products" style="order: {{ $homepageSectionOrder('latest_products') }}">
                <div class="container rtl">
                    <div class="section-header">
                        <h2 class="arrival-title d-block mb-1">
                            <div class="text-capitalize">
                                {{ translate('new_arrivals')}}
                            </div>
                        </h2>
                    </div>
                </div>
                <div class="container rtl mb-3 overflow-hidden">
                    <div class="py-2">
                        <div class="new_arrival_product">
                            <div class="carousel-wrap">
                                <div class="owl-carousel owl-theme new-arrivals-product" data-slide-items="{{ count($newArrivalProducts) }}">
                                    @foreach($newArrivalProducts as $key=> $product)
                                        @include('web-views.partials._product-card-2',['product'=>$product,'decimal_point_settings'=>$decimalPointSettings])
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </section>
            @endif

            @if ($homepageSectionVisible('best_selling') && $bestSellProduct->count() >0)
            <div class="container rtl px-0 px-md-3" data-homepage-section="best_selling" style="order: {{ $homepageSectionOrder('best_selling') }}">
                <div class="row g-3 mx-max-md-0">
                        @include('web-views.partials._best-selling')
                </div>
            </div>
            @endif

            @if ($homepageSectionVisible('top_rated') && $topRatedProducts->count() >0)
            <div class="container rtl px-0 px-md-3" data-homepage-section="top_rated" style="order: {{ $homepageSectionOrder('top_rated') }}">
                <div class="row g-3 mx-max-md-0">
                    @include('web-views.partials._top-rated')
                </div>
            </div>
            @endif

        @if ($homepageSectionVisible('footer_banners') && count($bannerTypeFooterBanner) > 1)
            <div class="container rtl" data-homepage-section="footer_banners" style="order: {{ $homepageSectionOrder('footer_banners') }}">
                <div class="promotional-banner-slider owl-carousel owl-theme">
                    @foreach($bannerTypeFooterBanner as $banner)
                        <a href="{{ $banner['url'] }}" class="d-block" target="_blank">
                            <img loading="lazy" class="footer_banner_img __inline-63" alt="" src="{{ getStorageImages(path:$banner->photo_full_url, type: 'banner') }}">
                        </a>
                    @endforeach
                </div>
            </div>
        @elseif ($homepageSectionVisible('footer_banners') && count($bannerTypeFooterBanner) === 1)
            <div class="container rtl" data-homepage-section="footer_banners" style="order: {{ $homepageSectionOrder('footer_banners') }}">
                <div class="row">
                    @foreach($bannerTypeFooterBanner as $banner)
                        <div class="col-md-6">
                            <a href="{{ $banner['url'] }}" class="d-block" target="_blank">
                                <img loading="lazy" class="footer_banner_img __inline-63" alt="" src="{{ getStorageImages(path:$banner->photo_full_url, type: 'banner') }}">
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($homepageSectionVisible('brands') && $web_config['brand_setting'] && $brands->count() > 0)
            <section class="container rtl" data-homepage-section="brands" style="order: {{ $homepageSectionOrder('brands') }}">

                <div class="section-header align-items-center mb-1">
                    <h2 class="text-black font-bold __text-22px mb-0">
                        <span> {{translate('brands')}}</span>
                    </h2>
                    <div class="__mr-2px">
                        <a class="text-capitalize view-all-btn-text" href="{{route('brands')}}">
                            <span class="view-btn-text">{{ translate('View_All') }}</span>
                            <i class="czi-arrow-{{ session('direction') === "rtl" ? 'left' : 'right' }}"></i>
                        </a>
                    </div>
                </div>

                <div class="mt-sm-3 mb-0 brand-slider">
                    <div class="owl-carousel owl-theme p-2 brands-slider" data-slide-items="{{ count($brands) }}">
                        @php($brandCount=0)
                        @foreach($brands as $brand)
                            @if($brandCount < 15 && !empty($brand['slug']))
                                <div class="text-center">
                                    <a href="{{ route('brand-products', ['slug' => $brand['slug']]) }}"
                                       class="__brand-item">
                                        <img loading="lazy" alt="{{ $brand?->image_alt_text ?? translate('Brand') }}"
                                             src="{{ getStorageImages(path: $brand->image_full_url, type: 'brand') }}">
                                    </a>
                                </div>
                            @endif
                            @php($brandCount++)
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($homepageSectionVisible('category_products') && $homeCategories->count() > 0)
            <div data-homepage-section="category_products" style="order: {{ $homepageSectionOrder('category_products') }}">
                @foreach($homeCategories as $category)
                    @include('web-views.partials._category-wise-product', ['decimal_point_settings'=>$decimalPointSettings])
                @endforeach
            </div>
        @endif

        @php($companyReliability = getWebConfig(name: 'company_reliability'))
        @if($homepageSectionVisible('reliability') && $companyReliability != null)
            <div data-homepage-section="reliability" style="order: {{ $homepageSectionOrder('reliability') }}">
                @include('web-views.partials._company-reliability')
            </div>
        @endif
    </div>

    <span id="direction-from-session" data-value="{{ session()->get('direction') }}"></span>
@endsection

@push('script')
    @if($orderSuccessIds)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('order_successfully');
                var orderModal = new bootstrap.Modal(modalEl, {
                    backdrop: 'static',
                    keyboard: false
                });
                orderModal.show();


                document.querySelectorAll('.copy-order-id').forEach(function(copyBtn) {
                    copyBtn.addEventListener('click', function() {
                        let orderTextEl = null;
                        orderTextEl = this.closest('tr')?.querySelector('.order-id-text');
                        if (!orderTextEl) {
                            orderTextEl = this.parentElement.querySelector('.order-id-text');
                        }
                        const orderText = orderTextEl?.textContent.trim();
                        if (orderText) {
                            navigator.clipboard.writeText(orderText).then(() => {
                                toastr.success('Order ID copied successfully!');
                            }).catch(err => {
                                console.warn('Clipboard error:', err);
                                toastr.warning('Unable to copy. Clipboard requires HTTPS or localhost.');
                            });
                        }
                    });
                });
                document.getElementById('modal-close-btn').addEventListener('click', function() {
                    setTimeout(() => { orderModal.hide(); }, 600);
                });
            });
        </script>
    @endif

    @if(Request::is('/') && \Illuminate\Support\Facades\Cookie::has('popup_banner') == false && empty($orderSuccessIds))
        <script>
            $(document).ready(function () {
                $('#popup-modal').modal('show');
            });
        </script>
        @php(\Illuminate\Support\Facades\Cookie::queue('popup_banner', 'off', 1))
    @endif
    <script src="{{theme_asset(path: 'public/assets/front-end/js/owl.carousel.min.js')}}"></script>
    <script src="{{ theme_asset(path: 'public/assets/front-end/js/home.js') }}"></script>
@endpush
