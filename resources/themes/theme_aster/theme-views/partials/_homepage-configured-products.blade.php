<section class="py-3 flash-deal-dark-support">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h2 class="text-capitalize mb-0">{{ $configuredTitle }}</h2>
            <a href="{{ $configuredRoute }}" class="btn-link text-capitalize">
                {{ translate('View_All') }}
                <i class="bi bi-chevron-{{ session('direction') === 'rtl' ? 'left' : 'right' }} text-primary"></i>
            </a>
        </div>
        <div class="auto-col mobile-items-2 gap-2 gap-sm-3 recommended-product-grid minWidth-12rem slide-shadow-wrapper middle-align-info">
            @foreach($configuredProducts as $product)
                @if($product)
                    @include('theme-views.partials._product-large-card', ['product' => $product])
                @endif
            @endforeach
        </div>
    </div>
</section>
