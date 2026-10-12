@extends('layouts.front-end.app')
@section('title','حذف الحساب')
@section('content')
<main class="container py-5" dir="rtl">
    <h1>حذف الحساب</h1><p>الخطوة {{ $stage }} من 3</p>
    @if($errors->any())<div role="alert" class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('customer.deletion.'.[1=>'begin',2=>'reason',3=>'finish'][$stage]) }}">@csrf
        @if($token)<input type="hidden" name="flow_token" value="{{ $token }}">@endif
        @if($stage === 2)
            <label for="deletion-reason">سبب حذف الحساب</label><textarea class="form-control" id="deletion-reason" name="reason" required maxlength="5000" rows="6" aria-describedby="reason-counter"></textarea>
            <p id="reason-counter" role="status">0 / 150 حرفًا على الأقل</p>
        @else
            @if($stage === 3)<p role="alert">حذف الحساب نهائي ولا يمكن التراجع عنه. تُحتفظ بهويتك وسجلاتك المالية للإدارة ويُوسم الحساب بأنه محذوف.</p>@endif
            <label for="deletion-password">{{ $stage === 1 ? 'كلمة المرور الحالية' : 'أدخل كلمة المرور مرة أخرى للتأكيد النهائي' }}</label>
            <input class="form-control" id="deletion-password" name="password" type="password" autocomplete="current-password" required maxlength="128">
        @endif
        <button id="deletion-submit" class="btn {{ $stage === 3 ? 'btn-danger' : 'btn-primary' }} mt-3" type="submit" @disabled($stage === 2)>{{ $stage === 3 ? 'حذف الحساب نهائيًا' : 'متابعة' }}</button>
        <a class="btn btn-link" href="{{ route('user-profile') }}">إلغاء</a>
        <a class="btn btn-link" href="{{ route('customer.deletion.show') }}">بدء الخطوات من جديد</a>
    </form>
</main>
@endsection
@push('script')
<script>
const reason = document.getElementById('deletion-reason');
reason?.addEventListener('input', () => {
    const count = Array.from(reason.value.trim()).length;
    document.getElementById('reason-counter').textContent = `${count} / 150 حرفًا على الأقل`;
    document.getElementById('deletion-submit').disabled = count < 150;
});
</script>
@endpush
