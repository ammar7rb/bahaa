@extends('layouts.admin.app')

@section('title', 'ملف تأمين الحساب')

@section('content')
<div class="content container-fluid" dir="rtl">
    <div class="mb-4"><a href="{{ route('admin.insurance-center.index', ['tab'=>$subjectType === 'customer' ? 'customers' : 'sellers']) }}">← مركز التأمين</a><h1 class="h3 mt-2">ملف تأمين {{ $subjectType === 'customer' ? 'العميل' : 'التاجر' }}</h1><p class="mb-0"><strong>{{ trim($account->f_name.' '.$account->l_name) }}</strong> · <bdi>{{ $account->email }}</bdi> · #{{ $account->id }}</p></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-3 mb-4">
        @foreach([['عدد تأمينات الطلبات',$recordCount],['إجمالي التأمين المدفوع',setCurrencySymbol(amount:usdToDefaultCurrency(amount:$paidAmount))],['الرصيد المتاح المستحق',setCurrencySymbol(amount:usdToDefaultCurrency(amount:$availableBalance))],['رصيد محجوز',setCurrencySymbol(amount:usdToDefaultCurrency(amount:$heldBalance))],['إجمالي التأمين المصادر',setCurrencySymbol(amount:usdToDefaultCurrency(amount:$confiscatedAmount))]] as [$label,$value])
        <div class="col-md"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ $label }}</small><h3 class="h5 mt-2">{{ $value }}</h3></div></div></div>
        @endforeach
    </div>
    <div class="alert alert-info">حالة الحساب: {{ match($override?->mode) { 'included' => 'مشمول بالقواعد العامة', 'required' => 'تأمين خاص', 'exempt' => 'معفى', default => 'يتبع نطاق التأمين العام' } }}. سحب الرصيد أدناه يخص الرصيد المستحق والمتاح فقط؛ لا يسحب تأمينًا لم يستحق بعد.</div>

    <div class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">حجز أو مصادرة من رصيد التأمين المستحق</h2></div><div class="card-body">
        <form method="post" action="{{ route('admin.insurance-center.balance-actions.store') }}">@csrf
            <input type="hidden" name="request_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="subject_type" value="{{ $subjectType }}"><input type="hidden" name="subject_id" value="{{ $account->id }}">
            <div class="row g-3"><div class="col-md-3"><label class="form-label">الإجراء</label><select class="form-select" name="action"><option value="hold">حجز مؤقت</option><option value="confiscate">مصادرة نهائية</option></select></div><div class="col-md-3"><label class="form-label">المبلغ</label><input class="form-control" type="number" min="0.01" max="{{ $availableBalance }}" step="0.01" name="amount" required></div><div class="col-md-3"><label class="form-label">رقم الطلب (اختياري)</label><input class="form-control" type="number" name="order_id" min="1"></div><div class="col-md-3"><label class="form-label">السبب</label><input class="form-control" name="reason" maxlength="2000" required></div><div class="col-md-9"><label class="form-label">ملاحظة دليل المخالفة (اختياري)</label><textarea class="form-control" name="evidence_note" maxlength="2000" rows="2"></textarea></div><div class="col-md-3 d-flex align-items-end"><button class="btn btn-danger w-100" @disabled($availableBalance <= 0)>تسجيل الإجراء</button></div></div>
        </form>
    </div></div>

    <div class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">تأمينات الطلبات</h2></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>الطلب</th><th>قيمة التأمين</th><th>المدفوع</th><th>الحالة</th><th>المصادر</th><th>التفاصيل</th></tr></thead><tbody>@forelse($records as $record)<tr><td>#{{ $record->order_id }}</td><td>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$record->amount)) }}</td><td>{{ $record->payment_status === 'paid' ? 'مدفوع' : 'غير مدفوع' }}</td><td>{{ translate($record->status) }}</td><td>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$record->confiscated_amount ?? 0)) }}</td><td><a href="{{ $subjectType === 'customer' ? route('admin.insurance-center.customers.show', $record) : route('admin.insurance-center.sellers.show', $record) }}">عرض التأمين</a></td></tr>@empty<tr><td colspan="6" class="text-center text-muted">لا توجد تأمينات لهذا الحساب.</td></tr>@endforelse</tbody></table></div>@if($records->hasPages())<div class="card-footer">{{ $records->links() }}</div>@endif</div>

    <div class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">سجل الحجز والمصادرة</h2></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>التاريخ</th><th>الإجراء</th><th>المبلغ</th><th>السبب</th><th>المرجع</th></tr></thead><tbody>@forelse($actions as $action)<tr><td>{{ $action->created_at?->format('Y-m-d H:i') }}</td><td>{{ translate('insurance_action_'.$action->action) }}</td><td>{{ setCurrencySymbol(amount:usdToDefaultCurrency(amount:$action->amount)) }}</td><td>{{ $action->reason }}</td><td>{{ $action->display_reference }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">لا توجد إجراءات بعد.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
