@if((float)$totalTax['item_tax'] > 0)
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16" data-checkout-product-tax><div class="opacity-75">ضريبة المنتجات</div><div class="fw-semibold">{{ webCurrencyConverter((float)$totalTax['item_tax']) }}</div></div>
@endif
@if((float)$totalTax['shipping_cost_tax'] > 0)
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16" data-checkout-shipping-tax><div class="opacity-75">ضريبة الشحن</div><div class="fw-semibold">{{ webCurrencyConverter((float)$totalTax['shipping_cost_tax']) }}</div></div>
@endif
