<section class="container care-product-section care-catalog-row" aria-labelledby="care-row-{{ $sectionKey }}" data-care-product-row>
    <div class="care-row-heading">
        <div class="care-row-title"><h2 id="care-row-{{ $sectionKey }}">{{ $configuredTitle }}</h2><p>{{ $configuredDescription }}</p></div>
        <div class="care-row-controls">
            <a class="care-row-all" href="{{ $configuredRoute }}">{{ translate('view_all') }} <i class="bi bi-arrow-{{ session('direction') === 'rtl' ? 'left' : 'right' }}" aria-hidden="true"></i></a>
            @if(count($configuredProducts) > 1)
                <div class="care-row-arrows">
                    <button type="button" data-care-row-pause aria-pressed="false" data-pause-label="{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'إيقاف الحركة التلقائية' : 'Pause automatic scrolling' }}" data-play-label="{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'تشغيل الحركة التلقائية' : 'Resume automatic scrolling' }}" aria-label="{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'إيقاف الحركة التلقائية' : 'Pause automatic scrolling' }}" aria-controls="care-products-{{ $sectionKey }}"><i class="bi bi-pause" aria-hidden="true"></i></button>
                    <button type="button" data-care-row-prev aria-label="{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'المنتجات السابقة' : 'Previous products' }}" aria-controls="care-products-{{ $sectionKey }}"><i class="bi bi-chevron-{{ session('direction') === 'rtl' ? 'right' : 'left' }}" aria-hidden="true"></i></button>
                    <button type="button" data-care-row-next aria-label="{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'المنتجات التالية' : 'Next products' }}" aria-controls="care-products-{{ $sectionKey }}"><i class="bi bi-chevron-{{ session('direction') === 'rtl' ? 'left' : 'right' }}" aria-hidden="true"></i></button>
                </div>
            @endif
        </div>
    </div>
    @if(count($configuredProducts))
        <div class="care-product-rail" id="care-products-{{ $sectionKey }}" tabindex="0" aria-label="{{ $configuredTitle }}">
            @foreach($configuredProducts as $product)
                @if($product)<div class="care-home-product">@include('theme-views.partials._product-large-card',compact('product'))</div>@endif
            @endforeach
        </div>
    @else
        <div class="care-row-empty"><p>{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'لا توجد منتجات متاحة في هذا القسم حاليًا.' : 'No products are currently available in this section.' }}</p><a href="{{ route('products') }}">{{ getLanguageCode(country_code:getDefaultLanguage()) === 'ar' ? 'عرض جميع المنتجات' : 'Browse all products' }} <i class="bi bi-arrow-{{ session('direction') === 'rtl' ? 'left' : 'right' }}" aria-hidden="true"></i></a></div>
    @endif
</section>
