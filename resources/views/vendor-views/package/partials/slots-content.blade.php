<div class="content container-fluid vendor-finance-page vendor-advertising-workspace">
    <header class="vendor-page-intro"><div><h1>{{ translate('phase5_spaces') }}</h1><p>{{ translate('vendor_account_summary') }} · {{ $workspace['capacity']['used'] }} / {{ $workspace['capacity']['total'] }} {{ translate('phase5_used') }}</p></div><a class="btn btn-outline-primary" href="{{ route('vendor.packages.index') }}">{{ translate('vendor_subscriptions') }}</a></header>
    @if(session('vendor_product_published'))<div class="alert alert-success" role="status">تم إرسال المنتج إلى الإدارة للمراجعة</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="card card-body mb-4"><h2>{{ $workspace['subscription']['package_name'] ?? 'لا يوجد اشتراك مدفوع' }}</h2>
        <p><strong>صلاحية المساحات:</strong> {{ ($workspace['subscription']['starts_at'] ?? null)?->format('Y/m/d H:i') ?? '—' }} — {{ ($workspace['subscription']['expires_at'] ?? null)?->format('Y/m/d H:i') ?? '—' }}</p>
        <p>{{ $workspace['capacity']['used'] }} نشطة · {{ $workspace['capacity']['reserved'] }} قيد الإضافة · {{ $workspace['capacity']['available'] }} متاحة · {{ $workspace['capacity']['total'] }} إجماليًا</p>

    </section>
    <h2 class="h3 mb-3">{{ translate('phase5_spaces') }}</h2><div class="vendor-slot-grid">
    @foreach($workspace['slots'] as $slot)
        @php($product = $slot['product'])
        @php($state = $slot['capacity_status'])
        <article class="vendor-slot-card" aria-labelledby="slot-title-{{ $slot['id'] }}">
            <div class="vendor-slot-card-heading"><h2 id="slot-title-{{ $slot['id'] }}">الإعلان {{ $slot['position'] }}</h2><span class="vendor-slot-state vendor-slot-{{ $state }}">{{ ['available'=>'متاحة','occupied'=>'نشط','reserved'=>'إضافة غير مكتملة','expired'=>'منتهية'][$state] }}</span></div>
            <p>{{ $slot['extra_ad_purchase_id'] ?? null ? 'إعلان إضافي' : 'ضمن الباقة' }} · حتى {{ \Carbon\Carbon::parse($slot['expires_at'])->timezone(config('app.timezone'))->format('Y/m/d H:i') }}</p>
            @if($product)
                <div class="vendor-slot-product-image">@if($product['thumbnail_url'] ?? null)<img class="vendor-ad-image {{ $state === 'expired' ? 'vendor-ad-inactive-image' : '' }}" src="{{ $product['thumbnail_url'] }}" alt="{{ $product['name'] }}" loading="lazy">@else<i class="tio-image" aria-hidden="true"></i>@endif</div>
                <h3>{{ $product['name'] }}</h3>
                <dl class="vendor-slot-product-facts"><div><dt>سعر المنتج</dt><dd>{{ webCurrencyConverter(amount: $product['unit_price'] ?? 0) }}</dd></div><div><dt>الكمية المتبقية</dt><dd>{{ number_format($product['current_stock'] ?? 0) }}</dd></div></dl>
                <div class="vendor-slot-product-actions"><a class="btn btn-outline-primary" href="{{ route('vendor.products.update', $product['id']) }}"><i class="tio-edit" aria-hidden="true"></i> تعديل المنتج</a><a class="btn btn-outline-primary" href="{{ route('vendor.products.view', $product['id']) }}">تفاصيل المنتج</a></div>
                <p>إحصاءات المنتج خلال الاشتراك: {{ $slot['statistics']['visits'] ?? 'غير متاحة' }} زيارة · {{ $slot['statistics']['orders'] ?? 'غير متاحة' }} طلب</p>
            @endif
            @if($slot['editable'])
                @if($state === 'available')<form method="post" action="{{ route('vendor.packages.slots.open', $slot['id']) }}">@csrf<button class="btn btn--primary w-100">إضافة منتج</button></form>@endif
                @if($state === 'reserved')<form method="post" action="{{ route('vendor.packages.slots.cancel-reservation', $slot['id']) }}">@csrf<button class="btn btn-outline-primary w-100">إلغاء الإضافة</button></form>@else
                    @if(!$product)
                    <details class="mt-3"><summary>اختيار منتج محفوظ</summary>
                        @php($choices = collect($workspace['products'])->reject(fn ($choice) => $choice['id'] === ($product['id'] ?? null)))
                        <form method="post" action="{{ route('vendor.packages.slots.update', $slot['id']) }}" class="mt-3">@csrf
                            <input type="hidden" name="slot_revision" value="{{ $slot['assignment_revision'] ?? 0 }}">
                            <fieldset class="vendor-product-picker"><legend>اختر منتجًا محفوظًا</legend><div class="vendor-product-picker-list">
                            @foreach($choices as $choice)
                                    <label class="vendor-product-picker-option"><input type="radio" name="product_id" value="{{ $choice['id'] }}" required>
                                        @if($choice['thumbnail_url'] ?? null)<img src="{{ $choice['thumbnail_url'] }}" alt="" width="48" height="48" loading="lazy">@else<span class="vendor-product-picker-placeholder" aria-hidden="true">@include('layouts.vendor.partials.v2._workspace-icon',['icon'=>'products'])</span>@endif
                                        <span>{{ $choice['name'] }}</span>
                                    </label>
                            @endforeach
                            @if($choices->isEmpty())<p class="small mb-0">لا توجد منتجات محفوظة للاختيار.</p>@endif
                            </div></fieldset>
                            <button class="btn btn-outline-primary w-100" @disabled($choices->isEmpty())>نشر المنتج</button>
                        </form>
                    </details>
                    @endif
                    @if($product)<form method="post" action="{{ route('vendor.packages.slots.update', $slot['id']) }}" class="mt-3">@csrf<input type="hidden" name="slot_revision" value="{{ $slot['assignment_revision'] ?? 0 }}"><input type="hidden" name="product_id" value=""><p class="small">الإزالة توقف الإعلان وتحتفظ بالمنتج وبياناته.</p><button class="btn btn-outline-danger w-100"><i class="tio-delete" aria-hidden="true"></i> حذف من الإعلان</button></form>@endif
                @endif
            @endif
        </article>
    @endforeach
        <article class="vendor-slot-card vendor-slot-buy"><h2>إعلان إضافي</h2><p>تنتهي مع الاشتراك الحالي ولا تتجدد تلقائيًا.</p>@if($extraSettings['enabled'] && $extraSettings['unit_price'] > 0 && $summary['active_subscription'])<strong>{{ webCurrencyConverter(amount: $extraSettings['unit_price']) }} للإعلان</strong>@if(config('vendor_advertising.extra_publish_enabled') && config('vendor_product_workspace.enabled'))<a class="btn btn--primary mt-3" href="{{ route('vendor.products.add', ['extra_ad' => 1]) }}">إضافة إعلان</a>@else<a class="btn btn--primary mt-3" href="#extra-ad-purchase">شراء الآن</a>@endif @else<p>الشراء غير متاح حتى تفعيل الإضافات وتحديد سعرها ووجود اشتراك سارٍ.</p>@endif</article>
    </div>
    @include('vendor-views.product.partials.saved-advertisements')
    <div id="extra-ad-purchase">@include('vendor-views.package.partials.extra-ads')</div>
</div>
