@if($homepagePromotedProducts->isNotEmpty())
    <section class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="fs-24 fw-bold mb-1 text-capitalize">{{ translate('Featured_Offers') }}</h2>
                <p class="text-muted mb-0 fs-14">{{ translate('selected_offers_from_our_verified_vendors') }}</p>
            </div>
        </div>
        <div class="auto-col gap-3 mobile_two_items" style="--maxWidth: 14rem;">
            @foreach($homepagePromotedProducts as $product)
                <div class="position-relative">
                    <span class="badge bg-warning text-dark position-absolute m-2 start-0 top-0" style="z-index: 3;">
                        {{ translate('Featured_Offer') }}
                    </span>
                    @include('theme-views.partials._product-small-card', ['product' => $product])
                </div>
            @endforeach
        </div>
    </section>
@endif
