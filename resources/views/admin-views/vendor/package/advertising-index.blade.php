@extends('layouts.admin.app')
@section('title', 'باقات البائعين الإعلانية')
@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4"><div><h1>باقات البائعين الإعلانية</h1><p>أسعار نهائية، ومساحات لمدة 30 يومًا. التعديلات للمشتريات الجديدة، مع حفظ شروط الاشتراكات السابقة.</p></div><a href="{{ route('admin.vendors.package-payments.index') }}" class="btn btn-primary">مراجعة التحويلات</a></div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <a href="{{ route('admin.vendors.extra-ads.index') }}" class="btn btn-outline-primary mb-4">إعدادات ومشتريات المساحات الإضافية</a>
    <section class="card card-body mb-4"><h2>إضافة باقة</h2><form method="post" action="{{ route('admin.vendors.packages.store') }}">@csrf @include('admin-views.vendor.package.partials.advertising-fields', ['package' => null])<button class="btn btn-primary mt-3">حفظ الباقة</button></form></section>
    <section class="card card-body"><h2>الباقات</h2><div class="table-responsive"><table class="table"><thead><tr><th>الباقة</th><th>السعر النهائي</th><th>المساحات</th><th>المدة</th><th>الاشتراكات</th><th>الحالة</th><th>الإدارة</th></tr></thead><tbody>
    @foreach($packages as $package)<tr><td>{{ $package->name }}</td><td>{{ webCurrencyConverter(amount: $package->package_price) }}</td><td>{{ $package->product_limit }}</td><td>30 يومًا</td><td>{{ $package->subscriptions_count }}</td><td>{{ $package->status ? 'متاحة للشراء' : 'معطلة للمشتريات الجديدة' }}</td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.vendors.packages.edit', $package->id) }}">تعديل الباقة والحالة</a></td></tr>@endforeach
    </tbody></table></div>{{ $packages->links() }}</section>
</div>
@endsection
