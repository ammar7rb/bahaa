@extends('layouts.admin.app')
@section('title', 'أمان الحسابات')
@section('content')
<main class="content container-fluid" dir="rtl">
    <div class="page-header mb-4"><h1 class="page-header-title">أمان الحسابات</h1><p class="text-muted">تحقق من هوية صاحب الحساب قبل إعادة تعيين كلمة المرور. لا ترسل كلمة المرور عبر الإشعارات أو رسائل الدعم.</p></div>
    <form method="get" class="card mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-md-3"><label for="account-type" class="form-label">نوع الحساب</label><select id="account-type" name="type" class="form-control">@if(\App\Utils\Helpers::module_permission_check('customer_password_reset'))<option value="customer" @selected($type==='customer')>عميل</option>@endif @if(\App\Utils\Helpers::module_permission_check('vendor_password_reset'))<option value="vendor" @selected($type==='vendor')>تاجر</option>@endif</select></div>
        <div class="col-md-7"><label for="account-q" class="form-label">اسم أو هاتف أو بريد</label><input id="account-q" name="q" value="{{ $q }}" class="form-control" required></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">بحث</button></div>
    </div></form>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @foreach($accounts as $account)
        <section class="card mb-2"><div class="card-body">
            <h2 class="h6">{{ $account->f_name }} {{ $account->l_name }} · #{{ $account->id }}</h2>
            <p class="small text-muted"><bdi>{{ $account->phone }}</bdi> · <bdi>{{ $account->email }}</bdi></p>
            <form method="post" action="{{ $type === 'customer' ? route('admin.customer.reset-password', $account->id) : route('admin.vendors.reset-password', $account->id) }}" class="row g-2" autocomplete="new-password">@csrf
                <div class="col-md-4"><label class="form-label">كلمة مرور جديدة</label><input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password"></div>
                <div class="col-md-4"><label class="form-label">تأكيدها</label><input type="password" name="password_confirmation" class="form-control" minlength="8" required autocomplete="new-password"></div>
                <div class="col-md-4 align-self-end"><button class="btn btn-outline-danger w-100" onclick="return confirm('تأكيد إعادة تعيين كلمة مرور الحساب المحدد؟')">إعادة تعيين للحساب #{{ $account->id }}</button></div>
            </form>
        </div></section>
    @endforeach
</main>
@endsection
