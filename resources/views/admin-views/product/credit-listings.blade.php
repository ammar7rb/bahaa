@extends('layouts.admin.app')
@section('title', 'اشتر الآن وادفع لاحقًا')
@section('content')
<div class="content container-fluid" dir="rtl">
    @include('admin-views.product.partials._management-nav')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h1 class="h2 mb-0"><i class="fi fi-rr-credit-card me-2" aria-hidden="true"></i> اشتر الآن وادفع لاحقًا</h1><div class="d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('admin.products.credit-requests') }}">طلبات الائتمان</a><a class="btn btn-primary" href="{{ route('admin.products.add') }}">إضافة منتج إداري</a></div></div>
    <p class="text-muted">اختر المنتجات الإدارية المنشورة التي تظهر في صفحة الائتمان. إنشاء المنتج وتحريره يتمان عبر محرر المنتجات الحالي.</p>
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    <form method="get" class="d-flex gap-2 mb-3"><input name="q" class="form-control" aria-label="بحث عن منتج" placeholder="بحث عن منتج" value="{{ request('q') }}"><button class="btn btn-outline-primary">بحث</button></form>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>المنتج</th><th>الرمز</th><th>الحالة</th><th>صفحة الائتمان</th><th>الإجراءات</th></tr></thead><tbody>
    @forelse($products as $product)
        <tr><td>{{ $product->name }}</td><td><bdi dir="ltr">{{ $product->public_code ?: '—' }}</bdi></td><td>{{ (int)$product->status === 1 && (int)$product->request_status === 1 ? 'منشور' : 'غير منشور' }}</td><td>{{ $product->is_credit_listing ? 'مدرج' : 'غير مدرج' }}</td><td><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.products.update', $product->id) }}">تحرير</a><form method="post" action="{{ route('admin.products.credit-listings.toggle', $product->id) }}" onsubmit="return confirm('تأكيد تغيير ظهور المنتج في صفحة الائتمان؟')">@csrf<input type="hidden" name="enabled" value="{{ $product->is_credit_listing ? 0 : 1 }}"><button class="btn btn-sm {{ $product->is_credit_listing ? 'btn-outline-danger' : 'btn-primary' }}" @disabled(!$product->is_credit_listing && ((int)$product->status !== 1 || (int)$product->request_status !== 1))>{{ $product->is_credit_listing ? 'إزالة من الصفحة' : 'إدراج في الصفحة' }}</button></form></div></td></tr>
    @empty<tr><td colspan="5" class="text-center text-muted py-5">لا توجد منتجات إدارية مطابقة.</td></tr>@endforelse
    </tbody></table></div></div><div class="mt-3" dir="ltr">{{ $products->links() }}</div>
</div>
@endsection
