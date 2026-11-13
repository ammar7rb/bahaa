@extends('layouts.admin.app')
@section('title', 'عدد مشاهدات منتجات التجار')
@section('content')
<main class="content container-fluid" dir="rtl">
    <div class="page-header mb-3"><h1 class="page-header-title">عدد مشاهدات منتجات التجار</h1><p class="text-muted">الزيارات المسجلة منفصلة عن الإضافات الإدارية. الزيادة تطبق على كل منتج محدد على حدة.</p></div>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="card mb-3"><div class="card-body"><h2 class="h5">١. ابحث عن التاجر وحدده</h2>
        <form method="get" class="d-flex flex-wrap gap-2"><input class="form-control flex-grow-1" name="vendor_q" value="{{ request('vendor_q') }}" placeholder="اسم التاجر أو هاتفه أو بريده" aria-label="بحث عن تاجر"><button class="btn btn-outline-primary">بحث</button></form>
        <div class="d-flex flex-wrap gap-2 mt-2">@foreach($vendors as $item)<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.settings.product-views.index', ['vendor_id' => $item->id]) }}">{{ $item->f_name }} {{ $item->l_name }} · #{{ $item->id }} · {{ $item->phone }}</a>@endforeach</div>
        @if($vendor)<p class="mt-2 mb-0">التاجر المحدد: <strong>{{ $vendor->f_name }} {{ $vendor->l_name }} · #{{ $vendor->id }}</strong> <a href="{{ route('admin.settings.product-views.index') }}">عرض كل منتجات التجار</a></p>@endif
    </div></section>
    <section class="card mb-3"><div class="card-body"><h2 class="h5">٢. المنتجات</h2>
        <form method="get" class="d-flex gap-2 mb-3"><input type="hidden" name="vendor_id" value="{{ $vendor?->id }}"><input class="form-control" name="product_q" value="{{ request('product_q') }}" placeholder="اسم المنتج أو رقمه" aria-label="بحث عن منتج"><button class="btn btn-outline-primary">بحث</button></form>
        <form method="post" action="{{ route('admin.settings.product-views.preview') }}">@csrf
            <input type="hidden" name="vendor_id" value="{{ $vendor?->id }}"><input type="hidden" name="product_q" value="{{ request('product_q') }}"><input type="hidden" name="page" value="{{ $products->currentPage() }}">
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>تحديد</th><th>المنتج</th><th>التاجر</th><th>زيارات مسجلة</th><th>إضافات إدارية</th><th>العدد الظاهر</th></tr></thead><tbody>
                @foreach($products as $item)<tr><td><input type="checkbox" name="product_ids[]" value="{{ $item->id }}" aria-label="تحديد {{ $item->name }}"></td><td>{{ $item->name }} · #{{ $item->id }}</td><td>#{{ $item->user_id }}</td><td>{{ $counts[$item->id]['actual'] }}</td><td>{{ $counts[$item->id]['added'] }}</td><td><strong>{{ $counts[$item->id]['total'] }}</strong></td></tr>@endforeach
            </tbody></table></div>
            {{ $products->links() }}
            <fieldset class="border rounded p-3 mt-3"><legend class="h6 float-none w-auto px-1">نطاق الاختيار</legend>
                <label class="d-block"><input type="radio" name="selection" value="selected" checked> المنتجات المؤشّر عليها</label>
                <label class="d-block"><input type="radio" name="selection" value="page"> كل منتجات الصفحة الحالية ({{ $products->count() }})</label>
                <label class="d-block"><input type="radio" name="selection" value="matching"> كل نتائج التاجر المطابقة للبحث ({{ $products->total() }})</label>
                <label class="d-block"><input type="radio" name="selection" value="all_vendors"> كل منتجات كل التجار (لا يلزم تحديد تاجر)</label>
            </fieldset>
            <div class="row g-2 mt-1"><div class="col-md-3"><label class="form-label" for="view-amount">عدد المشاهدات الإضافية لكل منتج</label><input id="view-amount" class="form-control" type="number" name="amount" min="1" max="1000000" required></div>
                <div class="col-md-7"><label class="form-label" for="view-reason">سبب الإضافة</label><input id="view-reason" class="form-control" name="reason" minlength="5" maxlength="500" required></div>
                <div class="col-md-2 align-self-end"><button class="btn btn-primary w-100">معاينة الزيادة</button></div></div>
        </form>
    </div></section>
    @if($preview)
        <section class="card border-primary"><div class="card-body"><h2 class="h5">٣. راجع ثم أكد</h2>
            <p>{{ $preview['vendor_id'] ? 'التاجر #'.$preview['vendor_id'] : 'كل التجار' }} · عدد المنتجات {{ count($preview['products']) }} · زيادة مستقلة لكل منتج: <strong>+{{ $preview['amount'] }}</strong> · السبب: {{ $preview['reason'] }}</p>
            <div class="table-responsive" style="max-height: 320px; overflow: auto"><table class="table table-sm"><thead><tr><th>المنتج</th><th>الحالي</th><th>الزيادة</th><th>بعد الإضافة</th></tr></thead><tbody>
                @foreach(array_slice($preview['products'], 0, 100) as $item)<tr><td>{{ $item['name'] }} · #{{ $item['id'] }}</td><td>{{ $item['total'] }} <small>(مسجلة {{ $item['actual'] }} + إدارية {{ $item['added'] }})</small></td><td>+{{ $preview['amount'] }}</td><td><strong>{{ $item['result'] }}</strong></td></tr>@endforeach
            </tbody></table></div>
            @if(count($preview['products']) > 100)<p class="text-muted">يعرض أول 100 منتج من {{ count($preview['products']) }}؛ الزيادة تُطبق على الكل.</p>@endif
            <form method="post" action="{{ route('admin.settings.product-views.apply') }}" class="mt-3">@csrf<input type="hidden" name="key" value="{{ $preview['key'] }}"><button class="btn btn-danger" onclick="return confirm('تأكيد إضافة المشاهدات لكل المنتجات المعروضة في المعاينة؟')">تأكيد الإضافة</button></form>
        </div></section>
    @endif
</main>
@endsection
