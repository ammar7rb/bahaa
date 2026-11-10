@extends('layouts.admin.app')
@section('title', 'سجل تغييرات الإعدادات')
@section('content')
<main class="content container-fluid" dir="rtl">
    <div class="page-header mb-3"><h1 class="page-header-title">سجل تغييرات الإعدادات</h1></div>
    <form method="get" class="card mb-3"><div class="card-body d-flex gap-2"><select class="form-control" name="kind" aria-label="نوع التغيير"><option value="">كل الأنواع</option>@foreach(['tax'=>'الضريبة','commission'=>'العمولة','package'=>'الباقات','shipping'=>'الشحن','payment_destination'=>'جهات الدفع','legal_content'=>'المحتوى القانوني','advertising_placement'=>'الإعلانات','catalog'=>'الكتالوج','company_product'=>'منتجات الشركة','account_security'=>'أمان الحسابات'] as $kind => $label)<option value="{{ $kind }}" @selected(request('kind')===$kind)>{{ $label }}</option>@endforeach</select><button class="btn btn-outline-primary">تصفية</button></div></form>
    <div class="card"><div class="card-body table-responsive"><table class="table table-sm"><thead><tr><th>الوقت</th><th>النوع</th><th>السجل</th><th>المسؤول</th><th>قبل</th><th>بعد</th></tr></thead><tbody>@foreach($records as $entry)<tr><td>{{ $entry->created_at }}</td><td>{{ $entry->kind }}</td><td>{{ $entry->subject_type }} #{{ $entry->subject_id }}</td><td>#{{ $entry->admin_id }}</td><td><code class="text-wrap">{{ $entry->before }}</code></td><td><code class="text-wrap">{{ $entry->after }}</code></td></tr>@endforeach</tbody></table>{{ $records->links() }}</div></div>
</main>
@endsection
