@extends('layouts.admin.app')
@section('title', 'قائمة مراجعة منتجات البائعين')
@section('content')
<div class="content container-fluid">
    <h1 class="page-header-title mb-2">قائمة مراجعة منتجات البائعين</h1>
    <p class="text-muted">راجع نسخة البائع، ثم احفظ النسخة الموجهة للعملاء واعتمد النشر من صفحة المنتج. تعديلات البائع تحجب المنتج مؤقتًا حتى اعتماد النسخة الجديدة.</p>
    <form method="get" class="d-flex flex-wrap align-items-end gap-3 mb-3">
        <label>الحالة<select class="form-control" name="state">
            @foreach(['pending'=>'كل المنتجات المنتظرة','submitted'=>'مرسل للمراجعة','under_review'=>'قيد المراجعة','approved_published'=>'منشور','suspended'=>'موقوف','rejected'=>'مرفوض','deleted'=>'محذوف'] as $key=>$label)
                <option value="{{ $key }}" @selected($state === $key)>{{ $label }}</option>
            @endforeach
        </select></label>
        <label>رقم البائع<input class="form-control" type="number" name="seller_id" min="1" value="{{ request('seller_id') }}"></label>
        <button class="btn btn--primary" type="submit">عرض</button>
    </form>
    <div class="card"><div class="table-responsive"><table class="table table-thead-bordered align-middle">
        <thead><tr><th>البائع / المنتج</th><th>بيانات البائع الجديدة</th><th>نسخة العملاء المحفوظة</th><th>الإصدار / الإرسال</th><th>المراجعة</th></tr></thead>
        <tbody>@forelse($submissions as $submission)
            @php($product = $products->get($submission->product_id))
            <tr>
                <td>{{ $product?->seller?->f_name }} {{ $product?->seller?->l_name }}<br><small>البائع #{{ $submission->seller_id }} · المنتج #{{ $submission->product_id }}</small></td>
                <td><strong>{{ $submission->seller_content['name'] ?? '—' }}</strong><br>
                    {{ \Illuminate\Support\Str::limit(strip_tags($submission->seller_content['details'] ?? ''), 140) }}<br>
                    <bdi>{{ $submission->seller_content['unit_price'] ?? '—' }}</bdi> · سعر البائع الأصلي
                </td>
                <td>{{ $product?->name ?? '—' }}<br><bdi>{{ $product?->unit_price ?? '—' }}</bdi>
                    <br><small>{{ $submission->approved_revision ? 'آخر نسخة عامة معتمدة محفوظة' : 'لم تنشر نسخة للعملاء بعد' }}</small>
                    @if($submission->editorial_content)<br><small>توجد نسخة تحرير محفوظة للإصدار {{ $submission->editorial_revision }}</small>@endif
                </td>
                <td>{{ $submission->revision > 1 ? 'تعديل من البائع' : 'إرسال منتج' }} · {{ $submission->revision }}<br>{{ $submission->submitted_at ?? $submission->created_at }}</td>
                <td>@if($product)
                    <a class="btn btn--primary btn-sm" href="{{ route('admin.products.update', $product->id) }}">تحرير النسخة الموجهة للعملاء</a>
                    <a class="btn btn-outline-primary btn-sm mt-2" href="{{ route('admin.products.view', ['addedBy'=>'vendor','id'=>$product->id]) }}">مراجعة المنتج واعتماد النشر</a>
                @else<span class="text-muted">المنتج غير موجود</span>@endif</td>
            </tr>
        @empty<tr><td colspan="5" class="text-center py-5">لا توجد منتجات بهذه الحالة.</td></tr>@endforelse</tbody>
    </table></div><div class="card-body">{{ $submissions->links() }}</div></div>
    @if($legacyProducts->isNotEmpty())
        <div class="card mt-3"><div class="card-body"><h2 class="h4">منتجات منتظرة بدون نسخة مراجعة مستقلة</h2>
            @foreach($legacyProducts as $legacyProduct)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                    <span>{{ $legacyProduct->name }} · #{{ $legacyProduct->id }}</span>
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.products.view', ['addedBy' => 'vendor', 'id' => $legacyProduct->id]) }}">مراجعة المنتج</a>
                </div>
            @endforeach
            {{ $legacyProducts->links() }}
        </div></div>
    @endif
</div>
@endsection
