@extends('layouts.admin.app')

@section('title', 'إثبات التسليم')

@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div><h2 class="h1 mb-1">إثبات التسليم</h2><p class="text-muted mb-0">مراجعة الصور والفيديوهات والمستندات المرفوعة لكل طلب.</p></div>
        <form method="get" class="d-flex gap-2 flex-wrap">
            <input class="form-control" name="search" value="{{ request('search') }}" placeholder="رقم الطلب أو اسم التاجر">
            <select class="form-select" name="review_status"><option value="">كل الحالات</option>@foreach(['pending' => 'قيد المراجعة', 'approved' => 'معتمد', 'rejected' => 'مرفوض'] as $key => $label)<option value="{{ $key }}" @selected(request('review_status') === $key)>{{ $label }}</option>@endforeach</select>
            <button class="btn btn-primary">بحث</button>
        </form>
    </div>

    <div class="row g-3 mb-3">
        @foreach(['all' => 'الكل', 'pending' => 'قيد المراجعة', 'approved' => 'معتمد', 'rejected' => 'مرفوض'] as $key => $label)
            <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><span class="text-muted">{{ $label }}</span><strong class="d-block fs-3 mt-1">{{ $summary[$key] }}</strong></div></div></div>
        @endforeach
    </div>

    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>الطلب</th><th>التاجر</th><th>مرحلة الشحن</th><th>الملف</th><th>تاريخ الرفع</th><th>المراجعة</th></tr></thead>
        <tbody>@forelse($proofs as $proof)<tr>
            <td><a href="{{ route('admin.orders.details', $proof->order_id) }}">#{{ $proof->order_id }}</a><small class="d-block text-muted">{{ translate($proof->order?->order_status) }}</small></td>
            <td>{{ trim(($proof->seller?->f_name ?? '').' '.($proof->seller?->l_name ?? '')) ?: '#'.$proof->seller_id }}</td>
            <td><span class="badge badge-soft-info">{{ translate($proof->shipping_status) }}</span></td>
            <td><a class="btn btn-sm btn-outline-info" target="_blank" rel="noopener" href="{{ route('admin.orders.shipping-proofs.preview', $proof->id) }}">عرض الإثبات</a><a class="btn btn-sm btn-outline-secondary ms-1" href="{{ route('admin.orders.shipping-proofs.download', $proof->id) }}">تنزيل</a><small class="d-block text-muted mt-1">{{ $proof->original_name }}</small></td>
            <td>{{ $proof->created_at?->format('Y-m-d H:i') }}</td>
            <td><div class="mb-2"><span class="badge {{ $proof->review_status === 'approved' ? 'badge-soft-success' : ($proof->review_status === 'rejected' ? 'badge-soft-danger' : 'badge-soft-warning') }}">{{ translate($proof->review_status) }}</span></div><form method="post" action="{{ route('admin.orders.shipping-proofs.review', $proof->id) }}" class="d-flex gap-2">@csrf<select name="review_status" class="form-select form-select-sm"><option value="approved">اعتماد</option><option value="rejected">رفض</option></select><input name="review_note" class="form-control form-control-sm" value="{{ $proof->review_note }}" placeholder="ملاحظة"><button class="btn btn-sm btn-primary">حفظ</button></form></td>
        </tr>@empty<tr><td colspan="6" class="text-center text-muted py-5">لا توجد إثباتات تسليم حتى الآن.</td></tr>@endforelse</tbody>
    </table></div><div class="card-footer">{{ $proofs->links() }}</div></div>
</div>
@endsection
