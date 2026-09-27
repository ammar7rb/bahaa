@if($homepagePromotedProducts->isNotEmpty())
    <section class="container rtl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="font-bold mb-1 text-capitalize h4">{{ translate('Featured_Offers') }}</h2>
                <p class="text-muted mb-0 fs-13">{{ translate('selected_offers_from_our_verified_vendors') }}</p>
            </div>
        </div>
        <div class="owl-carousel owl-theme new-arrivals-product" data-slide-items="{{ count($homepagePromotedProducts) }}">
            @foreach($homepagePromotedProducts as $product)
                <div class="position-relative">
                    <span class="badge badge-warning position-absolute m-2" style="z-index: 3; inset-inline-start: 0;">
                        {{ translate('Featured_Offer') }}
                    </span>
                    @include('web-views.partials._product-card-1', [
                        'product' => $product,
                        'decimal_point_settings' => $decimalPointSettings,
                    ])
                </div>
            @endforeach
        </div>
    </section>
@endif
