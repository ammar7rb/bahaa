@extends('layouts.front-end.app')
@section('title','التواصل مع الدعم')
@section('content')
<main class="container py-5" dir="rtl"><h1>التواصل مع الدعم</h1>
    @if($errors->any())<div role="alert" class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('customer.support.report') }}">@csrf<label for="support-problem">الإبلاغ عن مشكلة</label><textarea class="form-control" id="support-problem" name="message" required minlength="3" maxlength="5000" rows="4" placeholder="اكتب ما حدث وكيف يمكننا مساعدتك"></textarea><button class="btn btn-primary mt-3" type="submit">إرسال إلى الدعم</button></form>
    <h2 class="mt-5">محادثاتك السابقة</h2>
    @forelse($conversations as $conversation)<a class="d-block border-bottom py-3" href="{{ route('support-ticket.index',$conversation->id) }}">{{ $conversation->purpose === 'account_activation' ? 'تفعيل الحساب' : $conversation->subject }}<br><small>{{ $conversation->updated_at }}</small></a>@empty<p>ستظهر محادثتك هنا بعد إرسال المشكلة.</p>@endforelse
</main>
@endsection
