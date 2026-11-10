@extends('layouts.admin.app')
@section('title', 'ترتيب المنتجات')
@section('content')
<div class="content container-fluid" dir="rtl">
    @include('admin-views.product.partials._management-nav')<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h2 mb-0"><i class="fi fi-rr-sort-amount-down me-2" aria-hidden="true"></i> ترتيب المنتجات</h1><a class="btn btn-outline-primary" href="{{ route('admin.products.all') }}">كل المنتجات</a></div>
<p class="text-muted">اختر مجموعة لترتيب منتجاتها: أقسام الرئيسية اليدوية والتلقائية، أو قائمة المنتجات، أو البحث، أو تصنيف محدد. ترتيب القوائم والتصنيفات مشترك بين الويب والتطبيق؛ تظل الإعلانات المدفوعة وقواعد التوفر واختيار العميل للترتيب بالسعر أو الاسم محفوظة.</p>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="get" class="mb-3"><label for="section" class="form-label">القسم</label><select id="section" name="section" class="form-control" onchange="this.form.submit()">@foreach($sections as $section)<option value="{{ $section['key'] }}" @selected(($selected['key'] ?? null) === $section['key'])>{{ $section['label'] }}</option>@endforeach</select></form>
@if($selected && count($ids))<form method="post" action="{{ route('admin.products.order.save') }}">@csrf<input type="hidden" name="section" value="{{ $selected['key'] }}"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>المنتج</th><th>الرمز</th><th>الأولوية داخل القسم</th></tr></thead><tbody>@foreach($ids as $position => $id)@if($product = $products->get($id))<tr><td>{{ $product->name }}</td><td><bdi dir="ltr">{{ $product->public_code }}</bdi></td><td><input class="form-control" style="max-width:110px" type="number" name="order[{{ $id }}]" min="1" max="10000" value="{{ $position + 1 }}" required></td></tr>@endif @endforeach</tbody></table></div></div><button class="btn btn-primary mt-3" type="submit"><i class="fi fi-rr-check" aria-hidden="true"></i> حفظ الترتيب</button></form>@else<div class="card card-body text-center text-muted py-5">لا توجد منتجات مختارة يدويًا في هذا القسم.</div>@endif
</div>
@endsection
