@php
    $limitService = app(\App\Services\CustomerMonthlyAllowanceService::class);
    $limitCustomer = auth('customer')->user();
    $limitState = $limitCustomer ? app(\App\Services\CustomerPurchaseEligibilityService::class)->status($limitCustomer) : null;
    $limitCart = \App\Utils\CartManager::getCartListQuery(type:'checked');
    $limitAmount = app(\App\Services\CustomerPurchaseLimitService::class)->getCartProductTotal($limitCart);
    $limitProvisional = !$limitCustomer || ($limitState['initial_order_exception_available'] ?? false) || ($limitState['can_resume_initial_checkout'] ?? false);
    $limitSummary = $limitService->enabled() ? $limitService->summary((int)($limitCustomer?->id ?? 0), $limitAmount, $limitProvisional) : null;
@endphp
@if($limitSummary)
<section class="purchasing-limit-strip border rounded p-3 my-3" dir="rtl" data-purchasing-limit @if($limitProvisional) data-provisional @endif
    data-granted="{{ $limitSummary['granted_limit'] }}" data-available="{{ $limitSummary['available_limit'] }}"
    data-cart="{{ $limitAmount }}" data-reserved="{{ $limitSummary['cart_reserved_amount'] }}" data-items='@json(collect($limitCart)->map(fn($item)=>["id"=>$item->id,"price"=>max(0,(float)$item->price-(float)$item->discount),"quantity"=>(int)$item->quantity])->values())'>
    <h3 class="h6">مؤشر الأئتمان</h3><p class="mb-1">المتاح <strong data-limit-available>{{ number_format($limitSummary['available_limit'],2) }}</strong> ج.م</p>
    <p class="mb-1">سلة المشتريات <strong data-limit-cart>{{ number_format($limitAmount,2) }}</strong> ج.م · المتبقي المتوقع <strong data-limit-projected>{{ number_format($limitSummary['projected_remaining'],2) }}</strong> ج.م</p>
    <p data-limit-status role="status"></p><small>يعكس الحد الائتماني المتاح. تدفع قيمة الطلب كاملة بوسيلة دفع فعلية.</small>
    <div class="mt-2"><a href="{{ route('customer.subscription.index') }}">زيادة الحد أو ترقية الباقة</a></div>
</section>
@endif
