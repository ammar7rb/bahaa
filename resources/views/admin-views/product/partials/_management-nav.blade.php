@once
@push('css_or_js')
<style>
.sigma-products-nav{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.25rem}.sigma-products-nav a{display:inline-flex;align-items:center;gap:.5rem;min-height:42px;padding:.6rem 1rem;border:1px solid var(--bs-border-color,#e5e7eb);border-radius:12px;text-decoration:none;color:inherit;transition:background-color .15s,border-color .15s}.sigma-products-nav a[aria-current=page]{color:var(--bs-primary,#0b7e84);border-color:currentColor;background:var(--bs-primary-bg-subtle,rgba(11,126,132,.08))}.sigma-products-nav a:hover{border-color:var(--bs-primary,#0b7e84)}.sigma-products-nav a:focus-visible{outline:2px solid currentColor;outline-offset:3px}@media(max-width:575px){.sigma-products-nav a{flex:1 1 45%;font-size:12px;justify-content:center}}
</style>
@endpush
@endonce
<nav class="sigma-products-nav" aria-label="إدارة المنتجات">
@foreach([
 ['admin.products.all','fi-rr-box-open','كل المنتجات'],
 ['admin.products.order','fi-rr-sort-amount-down','ترتيب الظهور'],
 ['admin.products.credit-listings','fi-rr-credit-card','منتجات الائتمان'],
 ['admin.products.credit-requests','fi-rr-comment-alt','طلبات الائتمان']
] as [$route,$icon,$label])
<a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif><i class="fi {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></a>
@endforeach
</nav>
