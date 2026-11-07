@php
    $assigned = collect($workspace['slots'])->pluck('product.id')->filter()->all();
    $saved = collect($workspace['products'])->reject(fn ($product) => in_array($product['id'], $assigned, true));
    $arabic = session('local', app()->getLocale()) === 'ar';
@endphp
<section class="vendor-saved-ads" aria-labelledby="saved-ads-title" data-ad-library>
    <div class="vendor-library-heading"><h2 id="saved-ads-title">{{ $arabic ? 'إعلاناتك المحفوظة' : 'Saved advertisements' }} <span class="vendor-library-count">{{ $saved->count() }}</span></h2>
        <div class="vendor-library-tools">
            <label><span class="sr-only">{{ $arabic ? 'ابحث باسم الإعلان' : 'Search advertisements' }}</span><input class="form-control" type="search" data-ad-search placeholder="{{ $arabic ? 'ابحث باسم الإعلان…' : 'Search advertisements…' }}"></label>
            <label><span class="sr-only">{{ translate('status') }}</span><select class="form-control" data-ad-filter><option value="all">{{ $arabic ? 'كل الإعلانات' : 'All advertisements' }}</option><option value="legacy">{{ $arabic ? 'الإعلانات القديمة' : 'Legacy advertisements' }}</option><option value="stopped">{{ $arabic ? 'غير نشطة' : 'Inactive' }}</option></select></label>
        </div>
    </div>
    <div class="vendor-saved-grid">
    @foreach($saved as $product)
        @php($legacy = $product['vendor_workspace']['legacy_exempt'] ?? false)
        @php($visible = $product['vendor_workspace']['advertising_visible'] ?? true)
        @php($formId = 'saved-ad-'.($product['vendor_workspace']['private_reference'] ?? $loop->index))
        <article class="vendor-saved-ad" data-ad-item data-ad-name="{{ $product['name'] }}" data-ad-legacy="{{ $legacy ? '1' : '0' }}" data-ad-review="{{ (int) ($product['request_status'] ?? 0) === 0 ? '1' : '0' }}" data-ad-stopped="{{ !$visible ? '1' : '0' }}">
            @if($product['thumbnail_url'] ?? null)<img class="vendor-ad-image {{ !$visible ? 'vendor-ad-inactive-image' : '' }}" src="{{ $product['thumbnail_url'] }}" alt="">@endif
            <div class="vendor-ad-copy"><h3>{{ $product['name'] }}</h3><strong>{{ webCurrencyConverter(amount: $product['unit_price'] ?? 0) }}</strong>
                @if($legacy)<span class="vendor-ad-label">{{ $arabic ? 'إعلان قديم مستثنى' : 'Legacy exempt advertisement' }}</span>@endif

            </div>
            <div class="vendor-ad-actions"><a class="btn btn-outline-primary" href="{{ route('vendor.products.view', $product['id']) }}">{{ translate('view') }}</a><a class="btn btn--primary" href="{{ route('vendor.products.update', $product['id']) }}">{{ translate('edit') }}</a>
                <form method="post" action="{{ route('vendor.products.delete', $product['id']) }}" id="{{ $formId }}">@csrf @method('delete')<button type="button" class="btn btn-outline-danger delete-data" data-id="{{ $formId }}">{{ translate('delete') }}</button></form>
            </div>
            @if(!$visible)<div class="vendor-ad-inactive-notice"><span>{{ $arabic ? 'الإعلان غير متاح للعملاء. اختر له مساحة سارية أو جدّد اشتراكك.' : 'This advertisement is hidden. Assign a valid space or renew your subscription.' }}</span></div>@endif
        </article>
    @endforeach
    </div>
    <p class="vendor-library-empty" data-ad-empty @if($saved->isNotEmpty()) hidden @endif>{{ $arabic ? 'لا توجد إعلانات تطابق اختيارك.' : 'No advertisements match your selection.' }}</p>
</section>
