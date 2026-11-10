@extends('theme-views.layouts.app')
@section('title', 'اشتر الآن وادفع لاحقًا')
@section('content')
<main class="container py-4" dir="rtl"><h1 class="mb-2">اشتر الآن وادفع لاحقًا</h1><p class="text-muted mb-4">يمكنك إرسال طلب ائتمان للمنتج. سنراجع الطلب ونتواصل معك؛ إرسال الطلب لا يعني الموافقة على التمويل.</p>
@if(session('credit_request_notice'))<div class="alert alert-info">{{ session('credit_request_notice') }}</div>@endif
<div class="row g-3">@forelse($products as $product)<div class="col-12 col-sm-6 col-lg-3"><div class="card h-100"><a href="{{ route('product', $product->slug) }}"><img class="card-img-top" style="aspect-ratio:1;object-fit:cover" src="{{ getStorageImages(path: $product->thumbnail_full_url, type: 'product') }}" alt="{{ $product->name }}"></a><div class="card-body d-flex flex-column"><h2 class="h5">{{ $product->name }}</h2><bdi dir="ltr" class="text-muted">{{ $product->public_code }}</bdi><p class="mt-auto">{{ webCurrencyConverter($product->unit_price) }}</p><a class="btn btn-primary" href="{{ route('product', $product->slug) }}">طلب ائتمان</a></div></div></div>@empty<div class="col-12 text-center text-muted py-5">لا توجد منتجات متاحة حاليًا.</div>@endforelse</div><div class="mt-3" dir="ltr">{{ $products->links() }}</div></main>
@endsection
