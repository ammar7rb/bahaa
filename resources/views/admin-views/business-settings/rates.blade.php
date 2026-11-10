@extends('layouts.admin.app')

@section('title', 'الضريبة ونسبة الشركة')

@section('content')
<main class="content container-fluid" dir="rtl">
    <div class="page-header mb-3"><h1 class="page-header-title">الضريبة ونسبة الشركة</h1><p class="text-muted">تسري التغييرات على الطلبات الجديدة. أولوية القواعد: الحساب، ثم المجموعة، ثم القاعدة العامة.</p></div>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="row g-3 mb-3">
        @if(\App\Utils\Helpers::module_permission_check('tax_manage'))
        <section class="col-12 col-lg-6"><div class="card h-100"><div class="card-body">
            <h2 class="h5">ضريبة العملاء 14٪</h2>
            <p class="text-muted small">على قيمة المنتجات بعد الخصومات، قبل الشحن، والأسعار غير شاملة. الحالة العامة: <strong>{{ $taxGlobal ? 'مفعلة' : 'معطلة' }}</strong></p>
            <form method="post" action="{{ route('admin.settings.rates.tax') }}">@csrf
                <label class="form-label" for="tax-enabled">القاعدة العامة</label>
                <select class="form-control" id="tax-enabled" name="enabled"><option value="1" @selected($taxGlobal)>تطبيق الضريبة</option><option value="0" @selected(!$taxGlobal)>تعطيل الضريبة</option></select>
                <button class="btn btn-primary mt-2" type="submit">حفظ قاعدة الضريبة</button>
            </form>
        </div></div></section>
        @endif
        @if(\App\Utils\Helpers::module_permission_check('commission_manage'))
        <section class="col-12 col-lg-6"><div class="card h-100"><div class="card-body">
            <h2 class="h5">نسبة الشركة من مبيعات التجار</h2>
            <p class="text-muted small">على قيمة المنتجات بعد الخصومات، قبل الضريبة والشحن. النسبة العامة الحالية: <strong>{{ $commissionGlobal }}٪</strong></p>
            <form method="post" action="{{ route('admin.settings.rates.commission') }}">@csrf
                <label class="form-label" for="commission-rate">النسبة العامة ٪</label>
                <input class="form-control" id="commission-rate" name="rate" type="number" min="0" max="100" step="0.001" value="{{ $commissionGlobal }}" required>
                <button class="btn btn-primary mt-2" type="submit">حفظ نسبة الشركة</button>
            </form>
        </div></div></section>
        @endif
    </div>
    <section class="card mb-3"><div class="card-body">
        <h2 class="h5">البحث عن حساب</h2>
        <form method="get" class="d-flex gap-2"><label class="visually-hidden" for="account-search">اسم أو هاتف أو بريد</label><input class="form-control" id="account-search" name="q" value="{{ $search }}" placeholder="اسم أو رقم هاتف أو بريد إلكتروني" required><button class="btn btn-outline-primary" type="submit">بحث</button></form>
    </div></section>
    <div class="row g-3 mb-3">
        @if(\App\Utils\Helpers::module_permission_check('tax_manage'))
        <section class="col-12 col-lg-6"><div class="card h-100"><div class="card-body"><h2 class="h5">العملاء</h2>
            @foreach($customers as $customer)
                @php($rule = $taxRules[$customer->id])
                <div class="border rounded p-2 mb-2">
                    <div><strong>{{ $customer->f_name }} {{ $customer->l_name }}</strong> <small>#{{ $customer->id }} · {{ $customer->phone }} · {{ $customer->email }}</small></div>
                    <div class="small">الفعالة: {{ $rule['enabled'] ? '14٪' : 'معطلة' }} · المصدر: {{ $rule['source'] }}</div>
                    <button class="btn btn-sm btn-link p-0" type="button" data-group-account="{{ $customer->id }}" data-group-kind="tax">إضافة إلى اختيار المجموعة</button>
                    <form method="post" action="{{ route('admin.settings.rates.tax.customer') }}" class="d-flex gap-2 mt-2">@csrf
                        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                        <select class="form-control" name="mode" aria-label="استثناء ضريبة العميل"><option value="inherit">اتباع المجموعة أو العام</option><option value="on">تطبيق 14٪</option><option value="off">إعفاء</option></select>
                        <button class="btn btn-outline-primary" type="submit">حفظ</button>
                    </form>
                </div>
            @endforeach
        </div></div></section>
        @endif
        @if(\App\Utils\Helpers::module_permission_check('commission_manage'))
        <section class="col-12 col-lg-6"><div class="card h-100"><div class="card-body"><h2 class="h5">التجار</h2>
            @foreach($vendors as $vendor)
                @php($rule = $commissionRules[$vendor->id])
                <div class="border rounded p-2 mb-2">
                    <div><strong>{{ $vendor->f_name }} {{ $vendor->l_name }}</strong> <small>#{{ $vendor->id }} · {{ $vendor->phone }} · {{ $vendor->email }}</small></div>
                    <div class="small">الفعالة: {{ $rule['effective_rate'] }}٪ · المصدر: {{ $rule['source'] }}</div>
                    <button class="btn btn-sm btn-link p-0" type="button" data-group-account="{{ $vendor->id }}" data-group-kind="commission">إضافة إلى اختيار المجموعة</button>
                    <form method="post" action="{{ route('admin.settings.rates.commission.vendor') }}" class="d-flex flex-wrap gap-2 mt-2">@csrf
                        <input type="hidden" name="vendor_id" value="{{ $vendor->id }}">
                        <select class="form-control" name="mode" aria-label="استثناء نسبة التاجر"><option value="inherit">اتباع المجموعة أو العام</option><option value="custom">نسبة خاصة</option></select>
                        <input class="form-control" name="rate" type="number" min="0" max="100" step="0.001" placeholder="النسبة ٪" aria-label="النسبة الخاصة">
                        <button class="btn btn-outline-primary" type="submit">حفظ</button>
                    </form>
                </div>
            @endforeach
        </div></div></section>
        @endif
    </div>
    <section class="card"><div class="card-body"><h2 class="h5">قواعد المجموعات</h2>
        <p class="text-muted small">ابحث عن الحسابات أولًا واضغط «إضافة إلى اختيار المجموعة». ينتمي كل حساب إلى مجموعة واحدة من نوع القاعدة نفسه.</p>
        @foreach($groups as $group)<div class="border-bottom py-2"><strong>{{ $group->name }}</strong> · {{ $group->kind === 'tax' ? 'ضريبة' : 'عمولة' }} · {{ $group->kind === 'tax' ? ($group->enabled ? '14٪' : 'معطلة') : $group->rate.'٪' }} · #{{ $group->id }}</div>@endforeach
        <form method="post" action="{{ route('admin.settings.rates.groups') }}" class="row g-2 mt-2">@csrf
            <div class="col-12 col-md-3"><label class="form-label" for="group-edit">إنشاء أو تعديل</label><select class="form-control" id="group-edit" name="group_id"><option value="">مجموعة جديدة</option>@foreach($groups as $group)<option value="{{ $group->id }}" data-kind="{{ $group->kind }}" data-name="{{ $group->name }}" data-rate="{{ $group->rate }}" data-enabled="{{ $group->enabled }}" data-members="{{ implode(', ', $groupMembers->get($group->id, [])) }}">#{{ $group->id }} · {{ $group->name }}</option>@endforeach</select></div>
            <div class="col-12 col-md-3"><label class="form-label" for="group-kind">نوع القاعدة</label><select class="form-control" id="group-kind" name="kind">@if(\App\Utils\Helpers::module_permission_check('tax_manage'))<option value="tax">ضريبة العملاء</option>@endif @if(\App\Utils\Helpers::module_permission_check('commission_manage'))<option value="commission">عمولة التجار</option>@endif</select></div>
            <div class="col-12 col-md-3"><label class="form-label" for="group-name">اسم المجموعة</label><input class="form-control" id="group-name" name="name" required maxlength="150"></div>
            <div class="col-12 col-md-3"><label class="form-label" for="group-ids">أرقام الحسابات</label><input class="form-control" id="group-ids" name="account_ids_text" required placeholder="12, 45, 73"></div>
            <div class="col-6 col-md-2"><label class="form-label" for="group-rate">نسبة العمولة ٪</label><input class="form-control" id="group-rate" name="rate" type="number" min="0" max="100" step="0.001"></div>
            <div class="col-6 col-md-1"><label class="form-label" for="group-enabled">الضريبة</label><select class="form-control" id="group-enabled" name="enabled"><option value="1">نعم</option><option value="0">لا</option></select></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">حفظ المجموعة</button></div>
        </form>
    </div></section>
    <section class="card mt-3"><div class="card-body"><h2 class="h5">سجل التغييرات الأخيرة</h2>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>الوقت</th><th>النوع</th><th>الحساب أو المجموعة</th><th>المسؤول</th><th>قبل</th><th>بعد</th></tr></thead><tbody>
            @foreach($auditRows as $entry)<tr><td>{{ $entry->created_at }}</td><td>{{ $entry->kind }}</td><td>{{ $entry->subject_type }} #{{ $entry->subject_id }}</td><td>#{{ $entry->admin_id }}</td><td><code>{{ $entry->before }}</code></td><td><code>{{ $entry->after }}</code></td></tr>@endforeach
        </tbody></table></div>
    </div></section>
</main>
@endsection

@push('script')
<script>
document.getElementById('group-edit').addEventListener('change', function () {
    var selected = this.selectedOptions[0];
    document.getElementById('group-kind').value = selected.dataset.kind || document.getElementById('group-kind').options[0].value;
    document.getElementById('group-name').value = selected.dataset.name || '';
    document.getElementById('group-rate').value = selected.dataset.rate || '';
    document.getElementById('group-enabled').value = selected.dataset.enabled || '1';
    document.getElementById('group-ids').value = selected.dataset.members || '';
});
document.querySelectorAll('[data-group-account]').forEach(function (button) {
    button.addEventListener('click', function () {
        var kind = document.getElementById('group-kind');
        var field = document.getElementById('group-ids');
        var nextKind = button.dataset.groupKind;
        if (kind.value !== nextKind) {
            field.value = '';
            document.getElementById('group-edit').value = '';
            document.getElementById('group-name').value = '';
            document.getElementById('group-rate').value = '';
        }
        kind.value = nextKind;
        var ids = field.value.split(',').map(function (id) { return id.trim(); }).filter(Boolean);
        if (!ids.includes(button.dataset.groupAccount)) ids.push(button.dataset.groupAccount);
        field.value = ids.join(', ');
        field.focus();
    });
});
</script>
@endpush
