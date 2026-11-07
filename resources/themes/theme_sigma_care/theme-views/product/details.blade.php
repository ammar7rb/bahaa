@php use App\Utils\Helpers;use App\Utils\ProductManager; @endphp
@extends('theme-views.layouts.app')

@section('title', $product['name'].' | '.$web_config['company_name'].' '.translate('ecommerce'))

@push('css_or_js')
    @include(VIEW_FILE_NAMES['product_seo_meta_content_partials'], ['metaContentData' => $product?->seoInfo, 'productDetails' => $product])
@endpush

@section('content')
@php($careArabic = getLanguageCode(country_code:getDefaultLanguage()) === 'ar')
<main class="main-content care-detail-page"><div class="container">
<nav class="care-detail-breadcrumb" aria-label="{{ translate('breadcrumb') }}"><a href="{{ route('home') }}">{{ translate('home') }}</a><span>/</span><a href="{{ route('products') }}">{{ translate('products') }}</a><span>/</span><span>{{ $product->name }}</span></nav>
<div class="care-detail-layout">
<div class="care-detail-gallery">
                                        <div class="pd-img-wrap position-relative h-100">
                                            <div class="w-100 d-flex justify-content-center">
                                                <div class="swiper-container quickviewSlider2 active-border border rounded aspect--1 inline-size-100 border--gray">
                                                    <div class="product__actions d-flex align-items-start gap-12">
                                                        @if($product->product_type == "physical")
                                                            <a href="javascript:" style="--size: 35px;" class="border text-warning bg-white" data-bs-toggle="tooltip" title="{{ translate('Physical_Product') }}" data-placement="left">
                                                                <img width="20" height="20" src="{{ theme_asset('assets/img/icons/product-type.svg') }}" class="svg" alt="">
                                                            </a>
                                                        @else
                                                            <a href="javascript:" style="--size: 35px;" class="border text-warning bg-white" data-bs-toggle="tooltip" title="{{ translate('Digital_Product') }}" data-placement="left">
                                                                <img width="20" height="20" src="{{ theme_asset('assets/img/icons/digital-product.svg') }}" class="svg" alt="">
                                                            </a>
                                                        @endif
                                                        <div class="d-flex flex-column gap-12">
                                                            <a class="btn-wishlist add-to-wishlist cursor-pointer wishlist-{{$product['id']}} {{($wishlistStatus == 1?'wishlist_icon_active':'')}}"
                                                               style="--size: 35px;"
                                                               data-action="{{route('store-wishlist')}}"
                                                               data-product-id="{{$product['id']}}"
                                                               id="wishlist-{{$product['id']}}"
                                                               title="{{translate('add_to_wishlist')}}">
                                                                <i class="bi bi-heart fs-14 mt-2px"></i>
                                                            </a>
                                                            
                                                            <div class="product-share-icons">
                                                                <a href="javascript:" style="--size: 35px;" title="Share">
                                                                    <i class="bi bi-share-fill"></i>
                                                                </a>
                                                                <ul>
                                                                    <li>
                                                                        <a href="javascript:" class="share-on-social-media share_btn facebook"
                                                                           style="--size: 20px;"
                                                                           data-action="{{route('product',$product->slug)}}"
                                                                           data-social-media-name="facebook.com/sharer/sharer.php?u=">
                                                                            <i class="bi bi-facebook"></i>
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a href="javascript:"
                                                                           class="share-on-social-media share_btn twitter"
                                                                           style="--size: 20px;"
                                                                           data-action="{{route('product',$product->slug)}}"
                                                                           data-social-media-name="twitter.com/intent/tweet?text=">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" width="12"
                                                                                 height="12" fill="currentColor"
                                                                                 class="bi bi-twitter-x" viewBox="0 0 16 16">
                                                                                <path
                                                                                    d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.601.75Zm-.86 13.028h1.36L4.323 2.145H2.865l8.875 11.633Z"/>
                                                                            </svg>
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a href="javascript:"
                                                                           class="share-on-social-media share_btn linkedin"
                                                                           style="--size: 20px;"
                                                                           data-action="{{route('product',$product->slug)}}"
                                                                           data-social-media-name="linkedin.com/shareArticle?mini=true&url=">
                                                                            <i class="bi bi-linkedin"></i>
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a href="javascript:"
                                                                           class="share-on-social-media share_btn whatsapp"
                                                                           style="--size: 20px;"
                                                                           data-action="{{route('product',$product->slug)}}"
                                                                           data-social-media-name="api.whatsapp.com/send?text=">
                                                                            <i class="bi bi-whatsapp"></i>
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    @if($product->images!=null && count($product->images_full_url)>0)
                                                        <div class="swiper-wrapper">
                                                            @if(json_decode($product->colors) && count($product->color_images_full_url)>0)
                                                                @foreach ($product->color_images_full_url as $key => $photo)
                                                                    @if($photo['color'] != null)
                                                                        <div class="swiper-slide position-relative rounded aspect--1"
                                                                             id="preview-box-{{ $photo['color'] }}">
                                                                            @if (getProductPriceByType(product: $product, type: 'discount', result: 'value') > 0)
                                                                                <span class="product__discount-badge">
                                                                                    <span>
                                                                                        -{{ getProductPriceByType(product: $product, type: 'discount', result: 'string') }}
                                                                                    </span>
                                                                                </span>
                                                                            @endif
                                                                            <div class="easyzoom easyzoom--overlay">
                                                                                <a href="{{ getStorageImages(path:$photo['image_name'], type:'product') }}">
                                                                                    <img class="dark-support rounded" alt=""
                                                                                        src="{{ getStorageImages(path:$photo['image_name'], type:'product') }}">
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    @else
                                                                        <div class="swiper-slide position-relative rounded aspect--1"
                                                                             id="preview-box-{{ $photo['color'] }}">
                                                                            @if (getProductPriceByType(product: $product, type: 'discount', result: 'value') > 0)
                                                                                <span class="product__discount-badge">
                                                                                    <span>
                                                                                        -{{ getProductPriceByType(product: $product, type: 'discount', result: 'string') }}
                                                                                    </span>
                                                                                </span>
                                                                            @endif
                                                                            <div class="easyzoom easyzoom--overlay">
                                                                                <a href="{{ getStorageImages(path: $photo['image_name'], type:'product') }}">
                                                                                    <img class="dark-support rounded" alt=""
                                                                                        src="{{ getStorageImages(path: $photo['image_name'], type:'product') }}">
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                @endforeach
                                                            @else
                                                                @foreach ($product->images_full_url as $key => $photo)
                                                                    <div class="swiper-slide position-relative rounded aspect--1">
                                                                        @if (getProductPriceByType(product: $product, type: 'discount', result: 'value') > 0)
                                                                            <span class="product__discount-badge">
                                                                                    <span>
                                                                                        -{{ getProductPriceByType(product: $product, type: 'discount', result: 'string') }}
                                                                                    </span>
                                                                                </span>
                                                                        @endif
                                                                        <div class="easyzoom easyzoom--overlay">
                                                                            <a href="{{ getStorageImages(path: $photo, type:'product') }}">
                                                                                <img class="dark-support rounded" alt=""
                                                                                    src="{{ getStorageImages(path: $photo, type:'product') }}">
                                                                            </a>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            @endif
                                                        </div>
                                                    @endif

                                                    @if($product?->preview_file_full_url['path'])
                                                        <div class="product-preview-modal-text"
                                                             data-bs-toggle="modal"
                                                             data-bs-target="#product-preview-modal">
                                                                <span class="text-primary fw-bold py-2 user-select-none">
                                                                    {{ translate('See_Preview') }}
                                                                </span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="mt-2 user-select-none d-flex align-items-center gap-3">
                                                <div class="quickviewSliderThumb2 swiper-container active-border position-relative ">
                                                    @if($product->images!=null && json_decode($product->images)>0)
                                                        <div
                                                            class="swiper-wrapper auto-item-width justify-content-start border--gray width--4rem">
                                                            @if(count($product->color_images_full_url)>0)
                                                                @foreach ($product->color_images_full_url as $key => $photo)
                                                                    @if($photo['color'] != null)
                                                                        <div
                                                                            class="swiper-slide position-relative rounded border">
                                                                            <img class="dark-support rounded" alt=""
                                                                                src="{{ getStorageImages(path: $photo['image_name'], type:'product') }}">
                                                                        </div>
                                                                    @else
                                                                        <div class="swiper-slide position-relative rounded border">
                                                                            <img class="dark-support rounded" alt=""
                                                                                 src="{{ getStorageImages(path: $photo['image_name'], type:'product') }}">
                                                                        </div>
                                                                    @endif
                                                                @endforeach
                                                            @else
                                                                @foreach ($product->images_full_url as $key => $photo)
                                                                    <div
                                                                        class="swiper-slide position-relative rounded border">
                                                                        <img class="dark-support rounded" alt=""
                                                                            src="{{ getStorageImages(path: $photo, type:'product') }}">
                                                                    </div>
                                                                @endforeach
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="d-lg-flex d-none align-items-center gap-2">
                                                    <div class="swiper-button-prev swiper-quickview-button-prev size-1-5rem position-static"></div>
                                                    <div class="swiper-button-next swiper-quickview-button-next size-1-5rem position-static"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    
<div class="care-detail-information">
<h1>{{ $product->name }}</h1>
@if($product->brand)<a class="care-detail-brand" href="{{ route('products', ['brand_id'=>$product->brand_id]) }}">{{ $careArabic ? 'العلامة التجارية' : 'Brand' }}: {{ $product->brand->name }}</a>@endif
<div class="care-detail-main-price"><span class="care-detail-price-label">{{ $careArabic ? 'سعر المنتج' : 'Product price' }}</span><div class="care-detail-unit-price">{!! getPriceRangeWithDiscount(product: $product) !!}</div></div>
<dl class="care-detail-facts">
@if($product->category)<div><dt>{{ $careArabic ? 'الفئة' : 'Category' }}</dt><dd><a href="{{ route('products', ['category_id'=>$product->category_id]) }}">{{ $product->category->name }}</a></dd></div>@endif
@if($product->minimum_order_qty > 1)<div><dt>{{ $careArabic ? 'الحد الأدنى للطلب' : 'Minimum order' }}</dt><dd>{{ $product->minimum_order_qty }}</dd></div>@endif
</dl>
<section class="care-detail-about"><h2>{{ $careArabic ? 'عن هذا المنتج' : 'About this product' }}</h2><p>{{ \Illuminate\Support\Str::limit(html_entity_decode(strip_tags($product->details ?? ''), ENT_QUOTES, 'UTF-8'), 450) }}</p><a href="#careFullDescription">{{ $careArabic ? 'عرض تفاصيل المنتج كاملة' : 'View full product details' }}</a></section>
<div class="care-detail-benefits">
<a href="{{ route('buy-now-pay-later') }}"><span aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 9h18M7 14h4"/></svg></span>{{ $careArabic ? 'اشترِ الآن وادفع لاحقًا' : 'Buy now, pay later' }}</a>
<a href="#careSupportPanel" data-bs-toggle="offcanvas"><span aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13v-1a8 8 0 0116 0v5a3 3 0 01-3 3h-3M4 12H2v6h4v-6zm16 0h2v6h-4v-6z"/></svg></span>{{ $careArabic ? 'تواصل مع الإدارة' : 'Contact our team' }}</a>
</div>
<a class="care-detail-support" href="#careSupportPanel" data-bs-toggle="offcanvas">{{ $careArabic ? 'هل لديك مشكلة مع هذا المنتج؟' : 'Having a problem with this product?' }}</a>
</div>
<aside class="care-detail-purchase product-details-content product-cart-option-container" aria-label="{{ $careArabic ? 'خيارات الشراء' : 'Purchase options' }}"><form class="cart add-to-cart-details-form addToCartDynamicForm" action="{{ route('cart.add') }}"
                                                  data-errormessage="{{translate('please_choose_all_the_options')}}"
                                                  data-outofstock="{{translate('Sorry_Out_of_stock')}}.">
                                                @csrf

                                                

                                                @if($product['product_type'] == 'digital')
                                                    <div class="digital-product-authors mb-2">
                                                        @if(count($productPublishingHouseInfo['data']) > 0)
                                                            <div class="d-flex align-items-center g-2 me-2">
                                                                <span class="text-capitalize digital-product-author-title">{{ translate('Publishing_House') }} :</span>
                                                                <div class="item-list">
                                                                    @foreach($productPublishingHouseInfo['data'] as $publishingHouseName)
                                                                        <a href="{{ route('products', ['publishing_house_id' => $publishingHouseName['id'], 'product_type' => 'digital', 'page'=>1]) }}"
                                                                           class="text-base">
                                                                            {{ $publishingHouseName['name'] }}
                                                                        </a>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif

                                                        @if(count($productAuthorsInfo['data']) > 0)
                                                            <div class="d-flex align-items-center g-2 me-2">
                                                                <span class="text-capitalize digital-product-author-title">{{ translate('Author') }} :</span>
                                                                <div class="item-list">
                                                                    @foreach($productAuthorsInfo['data'] as $productAuthor)
                                                                        <a href="{{ route('products',['author_id' => $productAuthor['id'], 'product_type' => 'digital', 'page' => 1]) }}"
                                                                           class="text-base">
                                                                            {{ $productAuthor['name'] }}
                                                                        </a>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif

                                                <div class="product__price d-flex flex-wrap align-items-end gap-2 mb-4">
                                                    <div class="text-primary d-flex align-items-center gap-2 mb-0">
                                                        {!! getPriceRangeWithDiscount(product: $product) !!}
                                                    </div>
                                                </div>


                                                @if($product->product_type === 'physical')
                                                <p class="care-detail-stock" aria-live="polite">{{ $careArabic ? 'المتبقي من المخزون:' : 'Remaining stock:' }} <strong class="product-details-stock-qty">{{ $firstVariationQuantity }}</strong> <span>{{ $careArabic ? 'قطعة' : 'items' }}</span></p>
                                                @endif

                                                <div class="">
                                                    <input type="hidden" name="id" value="{{ $product->id }}">
                                                    @if (count(json_decode($product->colors)) > 0)
                                                        <div class="d-flex gap-4 align-items-center mb-3">
                                                            <span class="text-muted">{{translate('color')}}</span>
                                                            <ul class="option-select-btn custom_01_option flex-wrap weight-style--two gap-3 pt-2">
                                                                @foreach (json_decode($product->colors) as $key => $color)
                                                                    <li>
                                                                        <label>
                                                                            <input type="radio" hidden=""
                                                                                   id="{{ $product->id }}-color-{{ str_replace('#','',$color) }}"
                                                                                   name="color" value="{{ $color }}"
                                                                                {{ $key == 0 ? 'checked' : '' }}
                                                                            >
                                                                            <span
                                                                                class="color_variants rounded focus-preview-image-by-color p-0 {{ $key == 0 ? 'color_variant_active':''}}"
                                                                                style="background: {{ $color }}; --outline:2px;"
                                                                                data-slide-id="preview-box-{{ str_replace('#','',$color) }}"
                                                                                id="color_variants_preview-box-{{ str_replace('#','',$color) }}"
                                                                            ></span>
                                                                        </label>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    @endif

                                                    @foreach (json_decode($product->choice_options) as  $choice)
                                                        <div class="d-flex gap-4 align-items-center mb-4">
                                                            <span class="text-muted">{{translate($choice->title)}}</span>
                                                            <ul class="option-select-btn custom_01_option flex-wrap weight-style--two gap-2">
                                                                @foreach ($choice->options as $key =>$option)
                                                                    <li>
                                                                        <label>
                                                                            <input type="radio" hidden=""
                                                                                   id="{{ $choice->name }}-{{ $option }}"
                                                                                   name="{{ $choice->name }}"
                                                                                   value="{{ $option }}"
                                                                                   @if($key == 0) checked @endif >
                                                                            <span>{{$option}}</span>
                                                                        </label>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    @endforeach

                                                    @php($extensionIndex=0)
                                                    @if($product['product_type'] == 'digital' && $product['digital_product_file_types'] && count($product['digital_product_file_types']) > 0 && $product['digital_product_extensions'])
                                                        @foreach($product['digital_product_extensions'] as $extensionKey => $extensionGroup)
                                                            <div class="d-flex gap-4 align-items-center mb-4">
                                                                <span class="text-muted">
                                                                    {{ translate($extensionKey) }}
                                                                </span>

                                                                @if(count($extensionGroup) > 0)
                                                                    <ul class="option-select-btn custom_01_option flex-wrap weight-style--two gap-2">
                                                                    @foreach($extensionGroup as $index => $extension)
                                                                        <li>
                                                                            <label>
                                                                                <input type="radio" hidden
                                                                                       name="variant_key"
                                                                                       value="{{ $extensionKey.'-'.preg_replace('/\s+/', '-', $extension) }}"
                                                                                    {{ $extensionIndex == 0 ? 'checked' : ''}}>
                                                                                <span class="text-transform-none">{{ $extension }}</span>
                                                                            </label>
                                                                        </li>
                                                                    @php($extensionIndex++)
                                                                    @endforeach
                                                                    </ul>
                                                                @endif

                                                            </div>
                                                        @endforeach
                                                    @endif


                                                    <div class="d-flex gap-4 flex-wrap align-items-center mb-30">
                                                        <span class="text-muted">{{translate('Qty')}}</span>
                                                        <div class="quantity quantity--style-two d-flex align-items-center justify-content-between w-130px h-40px rounded overflow-hidden">
                                                           <span class="quantity__minus single-quantity-minus form-control h-100 bg--light w-32px" data-type="minus" data-field="quantity" data-form=".add-to-cart-details-form">
                                                            <i class="bi bi-dash"></i>
                                                        </span>
                                                            <input type="text"
                                                                   data-details-page="1"
                                                                   class="quantity__qty product_quantity__qty product-details-cart-qty w-100 fs-12 input-number"
                                                                   name="quantity"
                                                                   value="{{ $initialProductConfig['quantity'] ?? 1 }}"
                                                                   min="{{ $product?->minimum_order_qty ?? 1 }}"
                                                                   max="{{$product['product_type'] == 'physical' ? $product->current_stock : 100}}">
                                                            <span class="quantity__plus single-quantity-plus form-control h-100 bg--light w-32px" data-type="plus" data-field="quantity" data-form=".add-to-cart-details-form">
                                                                <i class="bi bi-plus"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" class="product-generated-variation-code" name="product_variation_code" data-product-id="{{ $product['id'] }}">
                                                    <input type="hidden" value="" class="product-exist-in-cart-list form-control w-50" name="key">
                                                    <div class="mb-30">
                                                        <div class="bg-light w-100 rounded px-3 py-2">
                                                            <div class="d-flex gap-1 align-items-center">
                                                                <h4 class="flex-middle-gap-2 mb-0 fs-12">
                                                                    <span>
                                                                        {{translate('total_price').':'}}
                                                                    </span>
                                                                    <span class="product-details-chosen-price-amount fs-18 text-primary">
                                                                        {{ webCurrencyConverter($initialProductConfig['total_quantity_price']) }}
                                                                    </span>
                                                                </h4>
                                                            </div>
                                                            <div class="">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mx-w d-flex flex-wrap gap-3 width--24rem product-add-and-buy-section-parent">
                                                        <div class="product-add-and-buy-section d--flex flex-wrap gap-3" {!! $firstVariationQuantity <= 0 ? 'style="display: none;"' : '' !!}>
                                                                <?php

                                                                $isTemporaryClose = false;
                                                                $isVacationMode = false;
                                                                $authorType = $product->added_by == 'admin' ? 'inhouse' : ($product->added_by == 'seller' ? 'vendor' : '');

                                                                if ($authorType === 'vendor') {
                                                                    $isTemporaryClose = checkVendorAbility(type: $authorType, status: 'temporary_close', vendor: $product->seller->shop);
                                                                    $isVacationMode = !$isTemporaryClose ? checkVendorAbility(type: $authorType, status: 'vacation_status', vendor: $product->seller->shop) : false;
                                                                } else {
                                                                    $isTemporaryClose = checkVendorAbility(type: $authorType, status: 'temporary_close');
                                                                    $isVacationMode = !$isTemporaryClose ? checkVendorAbility(type: $authorType, status: 'vacation_status') : false;
                                                                }
                                                                ?>

                                                             @if($isTemporaryClose || $isVacationMode)
                                                                <button type="button"
                                                                        class="btn btn-secondary fs-16 flex-grow-1"
                                                                        disabled>
                                                                    {{translate('buy_now')}}
                                                                </button>
                                                                <button type="button"
                                                                        class="btn btn-primary fs-16 flex-grow-1 text-capitalize"
                                                                        disabled>
                                                                    {{translate('add_to_cart')}}
                                                                </button>
                                                            @else
                                                                <button type="button"
                                                                        class="btn btn-secondary fs-16 product-buy-now-button"
                                                                        data-form=".add-to-cart-details-form"
                                                                        data-auth="{{( getWebConfig(name: 'guest_checkout') == 1 || Auth::guard('customer')->check() ? 'true':'false')}}"
                                                                        data-route="{{ route('shop-cart') }}"
                                                                >
                                                                    {{translate('buy_now')}}
                                                                </button>
                                                                <button type="button"
                                                                        class="btn btn-primary fs-16 text-capitalize product-add-to-cart-button"
                                                                        type="button"
                                                                        data-form=".add-to-cart-details-form"
                                                                        data-update="{{ translate('update_cart') }}"
                                                                        data-add="{{ translate('add_to_cart') }}">
                                                                    {{ $initialProductConfig['first_variant_in_cart'] ? translate('update_cart') : translate('add_to_cart') }}
                                                                </button>
                                                            @endif
                                                        </div>

                                                        @if(($product['product_type'] == 'physical'))
                                                            <div class="product-restock-request-section collapse" {!! $firstVariationQuantity <= 0 ? 'style="display: block;"' : '' !!}>
                                                                <button type="button"
                                                                        class="btn request-restock-btn btn-outline-primary fw-semibold product-restock-request-button"
                                                                        data-auth="{{ auth('customer')->check() }}"
                                                                        data-form=".addToCartDynamicForm"
                                                                        data-default="{{ translate('Request_Restock') }}"
                                                                        data-requested="{{ translate('Request_Sent') }}"
                                                                >
                                                                    {{ translate('Request_Restock')}}
                                                                </button>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    @if($product->added_by == 'admin')
                                                        @if(checkVendorAbility(type: 'inhouse', status: 'temporary_close') || checkVendorAbility(type: 'inhouse', status: 'vacation_status'))
                                                            <div class="alert alert-danger mt-3" role="alert">
                                                                {{ translate('this_shop_is_temporary_closed_or_on_vacation').'.'.translate('you_cannot_add_product_to_cart_from_this_shop_for_now') }}
                                                            </div>
                                                        @endif
                                                    @elseif($product->added_by == 'seller')
                                                        @if(checkVendorAbility(type: 'vendor', status: 'temporary_close', vendor: $product->seller->shop) || checkVendorAbility(type: 'vendor', status: 'vacation_status', vendor: $product->seller->shop))
                                                            <div class="alert alert-danger mt-3" role="alert">
                                                                {{ translate('this_shop_is_temporary_closed_or_on_vacation').'.'.translate('you_cannot_add_product_to_cart_from_this_shop_for_now') }}
                                                            </div>
                                                        @endif
                                                    @endif
                                                </div>
                                            </form>
<a class="care-detail-installments" href="{{ route('buy-now-pay-later') }}">{{ $careArabic ? 'اشترِ الآن وادفع لاحقًا' : 'Buy now, pay later' }}</a>
</aside></div>
<section id="careFullDescription" class="care-detail-about care-detail-related"><h2>{{ $careArabic ? 'تفاصيل المنتج' : 'Product details' }}</h2><div class="rich-editor-html-content">{!! $product->details !!}</div></section>
@if(count($relatedProducts)>0)<section class="care-detail-related"><h2>{{ translate('similar_products') }}</h2><div class="care-detail-related-grid">@foreach($relatedProducts as $relatedProduct)@include('theme-views.partials._product-large-card',['product'=>$relatedProduct])@endforeach</div></section>@endif
@if($product?->preview_file_full_url['path'])@include('theme-views.partials._product-preview-modal', ['previewFileInfo'=>$previewFileInfo])@endif
</div></main>
@endsection
@push('script')
<script src="{{ theme_asset('assets/js/product-details.js') }}"></script>
<script src="{{ theme_asset('assets/plugins/easyzoom/easyzoom.min.js') }}"></script>
<script>$(function(){ $('.easyzoom').each(function(){ $(this).easyZoom(); });getVariantPrice('.add-to-cart-details-form'); });</script>
@endpush
