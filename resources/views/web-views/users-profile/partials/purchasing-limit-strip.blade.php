@if(!request()->is('vendor', 'vendor/*', 'seller', 'seller/*') && !request()->routeIs('vendor.*', 'seller.*'))
@php
    $limitService = app(\App\Services\CustomerMonthlyAllowanceService::class);
    $limitCustomer = auth('customer')->user();
    $limitState = $limitCustomer ? app(\App\Services\CustomerPurchaseEligibilityService::class)->status($limitCustomer) : null;
    $limitCart = \App\Utils\CartManager::getCartListQuery(type:'checked');
    $limitAmount = app(\App\Services\CustomerPurchaseLimitService::class)->getCartProductTotal($limitCart);
    $limitProvisional = !$limitCustomer || ($limitState['is_urgent_account'] ?? false);
    $limitSummary = $limitService->enabled() ? $limitService->summary((int)($limitCustomer?->id ?? 0), $limitAmount, $limitProvisional) : null;
    $limitCartItems = collect($limitCart)->map(fn($item) => ['id'=>$item->id, 'price'=>max(0,(float)$item->price-(float)$item->discount), 'quantity'=>(int)$item->quantity])->values();
    $limitRatio = $limitSummary && $limitSummary['granted_limit'] > 0 ? $limitSummary['projected_remaining'] / $limitSummary['granted_limit'] : 0;
    $limitPercent = max(0, min(100, $limitRatio * 100));
@endphp
@if($limitSummary && $limitSummary['show_credit_indicator'] && (($limitVariant ?? 'cart') !== 'browsing' || $limitSummary['has_active_package']))
<section class="purchasing-limit-strip credit-indicator credit-indicator--{{ $limitVariant ?? 'cart' }}" dir="rtl" data-purchasing-limit data-tone="{{ $limitSummary['indicator'] }}" @if($limitProvisional) data-provisional @endif
    data-granted="{{ $limitSummary['granted_limit'] }}" data-available="{{ $limitSummary['available_limit'] }}"
    data-cart="{{ $limitAmount }}" data-reserved="{{ $limitSummary['cart_reserved_amount'] }}" data-items='@json($limitCartItems)'>
    <div class="credit-indicator-heading"><h3>مؤشر الائتمان</h3><span>{{ $limitSummary['provisional'] ? 'حد مؤقت للطلب الأول' : $limitSummary['package_name'] }}</span></div>
    <div class="credit-indicator-values"><div><span>الحد المتاح</span><strong><bdi data-limit-available>{{ number_format($limitSummary['available_limit'],2) }}</bdi> <small>ج.م</small></strong></div><div class="credit-indicator-remaining"><span>المتبقي من الحد المتاح</span><strong><bdi data-limit-projected>{{ number_format($limitSummary['projected_remaining'],2) }}</bdi> <small>ج.م</small></strong></div></div>
    <div class="credit-indicator-track" role="progressbar" aria-label="الحد الائتماني المتبقي" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $limitPercent }}"><span data-limit-progress style="width:{{ $limitPercent }}%"></span></div>
    <div class="credit-indicator-footer"><p data-limit-status role="status" aria-live="polite">{{ $limitSummary['projected_remaining'] < 0 ? 'الحد الائتماني غير كافٍ' : ($limitRatio > .5 ? 'الحد المتبقي متاح' : ($limitRatio > .1 ? 'انخفض الحد المتبقي' : 'الحد المتبقي منخفض')) }}</p><a href="{{ route('customer.subscription.index') }}#credit-increase">{{ $limitSummary['has_active_package'] ? 'زيادة الحد أو الترقية' : 'اختيار باقة' }} <span aria-hidden="true">←</span></a></div>
    <button type="button" class="credit-indicator-help" data-limit-help hidden>الحد غير كافٍ · عرض الحلول</button>
    @if(($limitVariant ?? 'cart') === 'cart')<small class="credit-indicator-explanation">مؤشر للحد الائتماني المتاح، وليس رصيد محفظة. تُدفع قيمة الطلب كاملة؛ الشحن والضرائب خارج حساب الحد.</small>@endif
</section>
@endif

@endif
