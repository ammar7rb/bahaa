@extends('layouts.admin.app')
@section('title', 'الرسائل والإشعارات')
@section('content')
<main class="content container-fluid" dir="rtl">
    <div class="page-header mb-3"><h1 class="page-header-title">الرسائل والإشعارات</h1><p class="text-muted">هذه الصفحة ترسل إشعارات صندوق الوارد. رسائل الدعم تظل في <a href="{{ route('admin.messages.index', ['type' => 'customer']) }}">محادثات الدعم</a>.</p></div>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="card mb-3"><div class="card-body"><h2 class="h5">ابحث عن الحسابات</h2>
        <form method="get" class="row g-2"><div class="col-md-3"><label for="audience" class="form-label">الجمهور</label><select id="audience" name="audience" class="form-control"><option value="customer" @selected($audience==='customer')>عملاء</option><option value="vendor" @selected($audience==='vendor')>تجار</option></select></div>
            <div class="col-md-7"><label for="notify-q" class="form-label">بحث بالاسم أو الهاتف أو البريد</label><input id="notify-q" name="q" value="{{ $q }}" class="form-control"></div><div class="col-md-2 align-self-end"><button class="btn btn-outline-primary w-100">بحث</button></div></form>
    </div></section>
    <section class="card mb-3"><div class="card-body"><h2 class="h5">إنشاء إشعار</h2>
        <form method="post" action="{{ route('admin.settings.notifications.preview') }}">@csrf<input type="hidden" name="audience" value="{{ $audience }}">
            <div class="table-responsive" style="max-height: 250px; overflow: auto"><table class="table table-sm"><thead><tr><th>اختيار</th><th>الحساب</th><th>بيانات التمييز</th></tr></thead><tbody>@foreach($accounts as $account)<tr><td><input type="checkbox" name="account_ids[]" value="{{ $account->id }}" aria-label="تحديد الحساب {{ $account->id }}"></td><td>{{ $account->f_name }} {{ $account->l_name }} · #{{ $account->id }}</td><td>{{ $account->phone }} · {{ $account->email }}</td></tr>@endforeach</tbody></table></div>
            <fieldset class="border rounded p-2 mb-3"><legend class="h6 float-none w-auto">المستلمون</legend><label class="d-block"><input type="radio" name="scope" value="selected" checked> الحسابات المحددة فقط</label><label><input type="radio" name="scope" value="all"> جميع {{ $audience === 'customer' ? 'العملاء' : 'التجار' }} (بغض النظر عن البحث)</label></fieldset>
            <div class="mb-2"><label class="form-label" for="notify-title">العنوان</label><input id="notify-title" class="form-control" name="title" maxlength="150" required></div>
            <div class="mb-2"><label class="form-label" for="notify-body">النص</label><textarea id="notify-body" class="form-control" name="body" rows="4" maxlength="2000" required></textarea></div>
            <button class="btn btn-primary">معاينة الجمهور والنص</button>
        </form>
    </div></section>
    @if($preview)<section class="card border-primary mb-3"><div class="card-body"><h2 class="h5">تأكيد الإرسال</h2><p>الجمهور: <strong>{{ $preview['audience'] === 'customer' ? 'عملاء' : 'تجار' }}</strong> · النطاق: {{ $preview['scope'] === 'all' ? 'الجميع' : 'المحددون' }} · العدد: <strong>{{ count($preview['ids']) }}</strong></p>
        <p class="small">أرقام الحسابات الأولى: {{ implode('، ', array_slice($preview['ids'], 0, 30)) }} @if(count($preview['ids']) > 30)…@endif</p>
        <div class="border rounded p-3 mb-3"><h3 class="h6">{{ $preview['title'] }}</h3><p class="mb-0" style="white-space: pre-wrap">{{ $preview['body'] }}</p></div>
        <form method="post" action="{{ route('admin.settings.notifications.send') }}">@csrf<input type="hidden" name="key" value="{{ $preview['key'] }}"><button class="btn btn-danger" onclick="return confirm('تأكيد إرسال الإشعار إلى {{ count($preview['ids']) }} حساب؟')">تأكيد الإرسال</button></form>
    </div></section>@endif
    <section class="card"><div class="card-body"><h2 class="h5">دفعات الإرسال الأخيرة</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>الدفعة</th><th>المسؤول</th><th>الجمهور</th><th>العدد</th><th>النتيجة</th><th>الوقت</th></tr></thead><tbody>@foreach($batches as $batch)<tr><td>#{{ $batch->id }}</td><td>#{{ $batch->admin_id }}</td><td>{{ $batch->audience }}</td><td>{{ $batch->recipient_count }}</td><td>{{ $batch->status }} · @foreach($outcomes->get($batch->id, []) as $outcome) {{ $outcome->status }}: {{ $outcome->total }} @endforeach</td><td>{{ $batch->created_at }}</td></tr>@endforeach</tbody></table></div></div></section>
</main>
@endsection
