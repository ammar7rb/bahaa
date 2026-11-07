@if(config('vendor_advertising.enabled'))
<nav class="vendor-product-sections" aria-label="أقسام المنتجات">
    <a href="{{ route('vendor.products.list', ['type' => 'all']) }}" @if($section === 'list') aria-current="page" @endif>قائمة المنتجات</a>
    <a href="{{ route('vendor.products.list', ['type' => 'all', 'section' => 'slots']) }}" @if($section === 'slots') aria-current="page" @endif>المساحات الإعلانية</a>
</nav>
@endif
