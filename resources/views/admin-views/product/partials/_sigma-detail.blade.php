@php
    $sigmaImages = collect($product->images_full_url ?? [])->map(fn ($photo) => getStorageImages(path: $photo['image_name'] ?? $photo, type: 'backend-product'));
    $sigmaThumbnail = getStorageImages(path: $product->thumbnail_full_url, type: 'backend-product');
@endphp
<section class="sigma-detail card mb-4" aria-labelledby="sigma-product-name">
    <div class="sigma-gallery">
        <a href="{{ $sigmaThumbnail }}" data-lightbox="sigma-product-gallery" aria-label="عرض صورة المنتج"><img class="sigma-main-image" src="{{ $sigmaThumbnail }}" alt="{{ $product->name }}"></a>
        @if($sigmaImages->isNotEmpty())<div class="sigma-thumbnails">@foreach($sigmaImages as $sigmaImage)<a href="{{ $sigmaImage }}" data-lightbox="sigma-product-gallery" aria-label="تكبير صورة المنتج"><img src="{{ $sigmaImage }}" alt="" loading="lazy"></a>@endforeach</div>@endif
    </div>
    <div class="sigma-description">
        <h2 id="sigma-product-name">{{ $product->name }}</h2>
        <p class="sigma-caption">بيانات النسخة المعروضة للعميل</p>
        <dl class="sigma-facts">
            <div><dt><i class="fi fi-rr-tag" aria-hidden="true"></i> سعر البيع</dt><dd>{{ webCurrencyConverter($product->unit_price) }}</dd></div>
            <div><dt><i class="fi fi-rr-box" aria-hidden="true"></i> الكمية المتبقية</dt><dd>{{ number_format($product->current_stock) }}</dd></div>
            @if($product->category)<div><dt>القسم</dt><dd>{{ $product->category->default_name }}</dd></div>@endif
            @if($product->brand?->default_name)<div><dt>العلامة التجارية</dt><dd>{{ $product->brand->default_name }}</dd></div>@endif
            @if($product->unit)<div><dt>الوحدة</dt><dd>{{ $product->unit }}</dd></div>@endif
            @if((float)$product->discount > 0)<div><dt>الخصم</dt><dd>{{ getProductPriceByType(product: $product, type: 'discount', result: 'string') }}</dd></div>@endif
            @if($productWiseTax)@foreach($product->taxVats as $sigmaTax)<div><dt>{{ $sigmaTax->tax->name }}</dt><dd>{{ $sigmaTax->tax->tax_rate }}%</dd></div>@endforeach
@endif
        </dl>
        <h3><i class="fi fi-rr-document" aria-hidden="true"></i> وصف المنتج</h3>
        <div class="sigma-rich-content" dir="rtl">{!! $product->details !!}</div>
        <div class="sigma-detail-actions"><a class="btn btn-primary" href="{{ route('admin.products.update', $product->id) }}"><i class="fi fi-rr-edit" aria-hidden="true"></i> تعديل نسخة العميل</a>@if($productActive && $isActive)<a class="btn btn-outline-primary" href="{{ route('product', $product->slug) }}" target="_blank" rel="noopener"><i class="fi fi-rr-globe" aria-hidden="true"></i> عرض في المتجر</a>@endif</div>
    </div>
</section>
@if($sigmaWorkspace)
<details class="sigma-advanced mb-4"><summary><i class="fi fi-rr-shop" aria-hidden="true"></i> بيانات التاجر الأصلية — النسخة {{ $sigmaWorkspace->revision }}</summary><div class="sigma-original"><h3>{{ $sigmaWorkspace->seller_content['name'] ?? '' }}</h3><p>سعر التاجر: <strong>{{ webCurrencyConverter($sigmaWorkspace->seller_content['unit_price'] ?? 0) }}</strong></p><p>{{ strip_tags($sigmaWorkspace->seller_content['details'] ?? '') }}</p></div></details>
@endif
@if(count($product->tags)>0)<section class="sigma-tags mb-4" aria-label="الكلمات المفتاحية">@foreach($product->tags as $sigmaTag)<span>{{ $sigmaTag->tag }}</span>@endforeach</section>@endif
@if($reviews->isNotEmpty())<details class="sigma-advanced mb-4"><summary><i class="fi fi-rr-star" aria-hidden="true"></i> تقييمات العملاء ({{ $reviews->total() }})</summary><div class="sigma-original">@foreach($reviews as $sigmaReview)<article class="sigma-review"><strong>{{ $sigmaReview->customer?->f_name ?? 'عميل' }} — {{ $sigmaReview->rating }} / 5</strong><p>{{ $sigmaReview->comment }}</p><a href="{{ route('admin.reviews.list') }}" class="text-primary">إدارة التقييمات</a></article>@endforeach{{ $reviews->links() }}</div></details>@endif

@if(count(json_decode($product->variation ?? '[]', true) ?: []) > 0)
<section class="card mb-4"><div class="card-header"><h3>خيارات المنتج</h3></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>الخيار</th><th>السعر</th><th>الكمية</th></tr></thead><tbody>
@foreach(json_decode($product->variation, true) as $sigmaVariant)
<tr><td>{{ $sigmaVariant['type'] ?? '—' }}</td><td>{{ webCurrencyConverter($sigmaVariant['price'] ?? 0) }}</td><td>{{ $sigmaVariant['qty'] ?? 0 }}</td></tr>
@endforeach
</tbody></table></div></section>
@endif
