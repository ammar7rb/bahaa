@extends('theme-views.layouts.app')

@section('title', $businessPage?->title.' | '.$web_config['company_name'].' '.translate('ecommerce'))

@section('content')
    <main class="main-content d-flex flex-column gap-3 py-4 {{ $businessPage->slug === 'buy-now-pay-later' ? 'care-bnpl-page' : '' }}">
        <div class="container {{ $businessPage->slug === 'buy-now-pay-later' ? 'care-credit-page' : '' }}">

            @if($businessPage->slug === 'buy-now-pay-later')
                <section class="care-bnpl-banner" aria-labelledby="care-bnpl-heading">
                    @if(getLanguageCode(country_code:getDefaultLanguage()) === 'ar')
                    <img class="care-bnpl-campaign" src="{{ theme_asset('assets/care/bnpl-campaign-wide-ar.webp') }}" width="2172" height="724" alt="سيجما للمستلزمات الطبية — اشترِ الآن وادفع لاحقًا" fetchpriority="high">
                    <h1 id="care-bnpl-heading" class="visually-hidden">اشترِ الآن وادفع لاحقًا</h1>
                    @else
                    <img class="care-bnpl-art" src="{{ theme_asset('assets/care/bnpl-medical-banner.webp') }}" width="2172" height="724" alt="" fetchpriority="high">
                    <div class="care-bnpl-copy">
                        <img class="care-bnpl-logo" src="{{ getStorageImages(path: $web_config['web_logo'], type: 'logo') }}" width="200" height="64" alt="{{ $web_config['company_name'] ?: 'SIGMA' }}">
                        <h1 id="care-bnpl-heading">@if(getLanguageCode(country_code:getDefaultLanguage()) === 'ar')<span>اشترِ الآن</span><span>وادفع لاحقًا</span>@else<span>Buy now.</span><span>Pay later.</span>@endif</h1>
                    </div>
                    @endif
                </section>
            @elseif(in_array($businessPage->slug, ['terms-and-conditions', 'medical-devices-accessories', 'veterinary-services', 'about-us']))
                <h1 class="fs-24 mb-3 {{ in_array($businessPage->slug, ['buy-now-pay-later', 'medical-devices-accessories', 'veterinary-services', 'about-us']) ? 'text-center' : '' }}">{{ $businessPage->title }}</h1>
            @else
            <div class="page-title overlay py-5 __opacity-half background-custom-fit rounded-10 overflow-hidden"
                 data-bg-img="{{ getStorageImages(path: $businessPage?->banner_full_url, type: 'business-page') }}">
                <div class="container {{ $businessPage->slug === 'buy-now-pay-later' ? 'care-credit-page' : '' }}">
                    <h1 class="absolute-white text-center text-capitalize">{{ $businessPage?->title }}</h1>
                </div>
            </div>

            @endif
            <div class="card my-4 {{ $businessPage->slug === 'about-us' ? 'care-about-content' : '' }} {{ in_array($businessPage->slug, ['buy-now-pay-later', 'medical-devices-accessories', 'veterinary-services']) ? 'care-pay-later-content mx-auto' : '' }}">
                <div class="card-body p-lg-4 text-dark page-paragraph {{ $businessPage->slug === 'terms-and-conditions' ? 'care-legal-document' : '' }} {{ in_array($businessPage->slug, ['buy-now-pay-later', 'medical-devices-accessories', 'veterinary-services']) ? 'care-page-placeholder' : '' }}">
                    @if($businessPage->slug === 'buy-now-pay-later')
                        <div class="care-bnpl-details-copy">{!! $businessPage?->description !!}</div>
                        @include('theme-views.partials._care-credit-request')
                    @else
                        {!! $businessPage?->description !!}
                    @endif
                </div>
            </div>
        </div>
    </main>
@endsection
