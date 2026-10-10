@php($careFooterPages = $web_config['business_pages']->reject(fn ($page) => in_array($page['slug'], ['buy-now-pay-later', 'medical-devices-accessories', 'terms-and-conditions', 'privacy-policy'])))
<footer class="footer">
    <div class="footer-bg-img">
    </div>
    <div class="footer-top">
        <div class="container">
            <div class="row gy-3 align-items-center">
                <div class="col-sm-6">
                    <div class="d-flex justify-content-center justify-content-md-start">
                        <img width="180" loading="lazy" alt="{{ translate('image') }}"
                             class="max-height-45px w-auto img-fit ba-platform-logo"
                             src="{{ getStorageImages(path: $web_config['footer_logo'], type:'logo') }}">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-main px-2  px-lg-0">
        <div class="container">
            <div class="row gy-5">
                <div class="col-lg-4">
                    <div class="widget widget--about text-center text-lg-start absolute-white">
                        <p>{{ getWebConfig(name: 'shop_address') }}</p>
                        <a href="mailto:{{$web_config['email']}}">{{$web_config['email']}}</a>

                        <div class="d-flex gap-3 justify-content-center justify-content-lg-start flex-wrap mt-4">
                            @if(isset($web_config['android']['status']) && $web_config['android']['status'])
                                <a href="{{ $web_config['android']['link'] }}">
                                    <img
                                        src="{{ theme_asset('assets/img/media/google-play.png') }}" loading="lazy"
                                        alt="{{ translate('image') }}">
                                </a>
                            @endif
                            @if(isset($web_config['ios']['status']) && $web_config['ios']['status'])
                                <a href="{{ $web_config['ios']['link'] }}">
                                    <img
                                        src="{{ theme_asset('assets/img/media/app-store.png') }}" loading="lazy"
                                        alt="{{ translate('image') }}">
                                </a>
                            @endif
                        </div>
                        <div class="mt-4 mb-3">
                            <div class="d-flex gap-2">
                                <h6 class="text-uppercase mb-2 font-weight-bold footer-heder">{{ translate('newsletter') }}</h6>
                                <i class="bi bi-send-fill mt-n1"></i>
                            </div>
                            <p class="text-start">{{ translate('subscribe_our_newsletter_to_get_latest_updates') }}</p>
                        </div>
                        <form class="newsletter-form" action="{{ route('subscription') }}" method="post">
                            @csrf
                            <div class="position-relative">
                                <label class="position-relative m-0 d-block">
                                    <i class="bi bi-envelope envelop-icon text-muted fs-18"></i>
                                    <input type="text" placeholder="{{ translate('enter_your_email') }}"
                                           class="form-control" name="subscription_email" required>
                                </label>
                                <button type="submit" class="btn btn-primary">{{ translate('submit') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="row gy-5">
                        <div class="col-sm-4 col-6">
                            <div class="widget widget--nav absolute-white">
                                <h4 class="widget__title">{{ translate('accounts') }}</h4>
                                <ul class="d-flex flex-column gap-3">
                                    <li>
                                        @if(auth('customer')->check())
                                            <a href="{{ route('user-profile') }}">{{ translate('profile') }}</a>
                                        @else
                                            <button class="bg-transparent border-0 p-0" data-bs-toggle="modal"
                                                    data-bs-target="#loginModal">
                                                {{ translate('profile') }}
                                            </button>
                                        @endif
                                    </li>
                                    <li>
                                        <a class="text-capitalize" href="#careSupportPanel" data-bs-toggle="offcanvas" aria-controls="careSupportPanel">{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'المساعدة والدعم' : 'Help & Support' }}</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-sm-4 col-6">
                            <div class="widget widget--nav absolute-white">
                                <h4 class="widget__title text-capitalize">{{ translate('quick_links') }}</h4>
                                <ul class="d-flex flex-column gap-3">
                                    @if($web_config['flash_deals'] && count($web_config['flash_deals_products']) > 0)
                                        <li>
                                            <a class="text-capitalize"
                                               href="{{ route('flash-deals',['id' => $web_config['flash_deals']['id']])}}">{{ translate('flash_deals') }}</a>
                                        </li>
                                    @endif
                                    <li>
                                        <a class="text-capitalize" href="{{ route('featured-products') }}">
                                            {{ translate('featured_products') }}
                                        </a>
                                    </li>
                                    {{-- The public footer does not expose the vendor directory. --}}
                                    @if(false)
                                        <li>
                                            <a class="text-capitalize" href="{{ route('vendors') }}">
                                                {{ translate('top_stores') }}
                                            </a>
                                        </li>
                                    @endif
                                    <li>
                                        <a class="text-capitalize"
                                           href="{{ route('latest-products') }}">
                                            {{ translate('latest_products') }}
                                        </a>
                                    </li>

                                    @if(Route::has('frontend.blog.index') && getWebConfig(name: 'blog_feature_active_status'))
                                        <li>
                                            <a href="{{ route('frontend.blog.index') }}">{{ translate('blogs') }}</a>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                        <div class="col-sm-4 col-6">
                            <div class="widget widget--nav absolute-white">
                                <h4 class="widget__title">{{ translate('other') }}</h4>
                                <ul class="d-flex flex-column gap-3">
                                    @foreach($careFooterPages->where('default_status', 1) as $businessPage)
                                        <li>
                                            <a class="text-capitalize"
                                               href="{{ route('business-page.view', ['slug' => $businessPage['slug']]) }}">
                                                {{ $businessPage['slug'] === 'terms-and-conditions' ? $businessPage['title'] : Str::limit(translate($businessPage['title']), 25, '...') }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if(count($careFooterPages->where('default_status', 0)) > 0)
    <div class="footer-bottom absolute-white">
        <div class="container">
            <div class="row justify-content-md-center">
                <div class="col"></div>
                <div class="col-md-10">
                    <div class="d-flex flex-column gap-3">
                        @if(count($careFooterPages->where('default_status', 0)) > 0)
                            <ul class="d-flex flex-wrap justify-content-center align-content-center flex-column flex-sm-row gap-3 row-gap-2 m-0">
                                @foreach($careFooterPages->where('default_status', 0) as $businessPage)
                                    <li class="pe-3 opacity-75 fs-12">
                                        <a href="{{ route('business-page.view', ['slug' => $businessPage['slug']]) }}"
                                           class="text-absolute-white">
                                            {{ Str::limit($businessPage['title'], 25, '...') }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
                <div class="col"></div>
            </div>
        </div>
    </div>
    @endif
</footer>
