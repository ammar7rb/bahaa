@extends('layouts.admin.app')

@section('title', 'المنتجات | Products')

@section('content')
<div class="content container-fluid" dir="rtl">
    @include('admin-views.product.partials._management-nav')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h2 mb-0"><i class="fi fi-sr-box-open me-2"></i>المنتجات <span class="badge bg-body-secondary text-dark">{{ $products->total() }}</span></h1>
        <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('admin.products.order') }}"><i class="fi fi-rr-sort-amount-down" aria-hidden="true"></i> ترتيب الظهور</a><a class="btn btn-outline-primary" href="{{ route('admin.products.list', ['type' => 'vendor', 'request_status' => 0]) }}">طلبات مراجعة البائعين</a></div>
    </div>

    <div class="card mb-3"><div class="card-body">
        <form method="get" action="{{ route('admin.products.all') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-5"><label for="product-search" class="form-label">بحث بالاسم أو رمز المنتج</label><input id="product-search" class="form-control" type="search" name="q" value="{{ request('q') }}" maxlength="120"></div>
            <div class="col-6 col-md-3"><label for="product-owner" class="form-label">المالك</label><select id="product-owner" name="owner" class="form-control"><option value="">الكل</option><option value="seller" @selected(request('owner') === 'seller')>بائع</option><option value="admin" @selected(request('owner') === 'admin')>الإدارة</option></select></div>
            <div class="col-6 col-md-2"><label for="product-visibility" class="form-label">الحالة</label><select id="product-visibility" name="visibility" class="form-control"><option value="">الكل</option><option value="visible" @selected(request('visibility') === 'visible')>منشور</option><option value="hidden" @selected(request('visibility') === 'hidden')>مخفي</option><option value="pending" @selected(request('visibility') === 'pending')>غير منشور</option></select></div>
            <div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fi fi-rr-search" aria-hidden="true"></i> بحث</button></div>
        </form>
    </div></div>

    <div class="card"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>المنتج</th><th>الرمز</th><th>المالك</th><th>الحالة</th><th>مواضع محددة في الرئيسية</th><th class="text-end">الإجراءات</th></tr></thead>
            <tbody>
            @forelse($products as $product)
                @php
                    $owner = $product->added_by === 'seller' ? 'vendor' : 'in-house';
                    $visible = (int) $product->request_status === 1 && (int) $product->status === 1;
                    $placed = $placements->filter(fn ($section) => in_array((int) $product->id, $section['product_ids'], true));
                @endphp
                <tr>
                    <td><div class="d-flex align-items-center gap-2"><img src="{{ getStorageImages(path: $product->thumbnail_full_url, type: 'backend-product') }}" onerror="this.onerror=null;this.src='{{ dynamicAsset(path: 'public/assets/front-end/img/placeholder.png') }}'" alt="" width="48" height="48" class="rounded" style="object-fit:cover"><div><a class="fw-semibold" href="{{ route('admin.products.view', ['addedBy' => $owner, 'id' => $product->id]) }}">{{ $product->name }}</a><div class="text-muted small">{{ $product->category?->name }}</div></div></div></td>
                    <td><bdi dir="ltr">{{ $product->public_code ?: '—' }}</bdi></td>
                    <td>{{ $product->added_by === 'seller' ? ($product->seller?->shop?->name ?: trim(($product->seller?->f_name ?? '').' '.($product->seller?->l_name ?? ''))) : 'الإدارة' }}</td>
                    <td><span class="badge {{ $visible ? 'bg-success' : ((int) $product->request_status === 1 ? 'bg-secondary' : 'bg-warning text-dark') }}">{{ $visible ? 'منشور' : ((int) $product->request_status === 1 ? 'مخفي' : 'غير منشور') }}</span></td>
                    <td>@forelse($placed as $section)<span class="badge bg-body-secondary text-dark me-1">{{ translate($section['label']) }}</span>@empty<span class="text-muted">—</span>@endforelse</td>
                    <td class="text-end"><div class="dropdown"><button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fi fi-rr-menu-dots-vertical" aria-hidden="true"></i> الإجراءات</button><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="{{ route('admin.products.view', ['addedBy' => $owner, 'id' => $product->id]) }}"><i class="fi fi-rr-eye me-2" aria-hidden="true"></i> عرض التفاصيل</a><a class="dropdown-item" href="{{ route('admin.products.update', ['id' => $product->id]) }}"><i class="fi fi-rr-pencil me-2" aria-hidden="true"></i> تحرير المنتج</a></div></div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-5">لا توجد منتجات مطابقة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3" dir="ltr">{{ $products->links() }}</div>
</div>
@endsection
