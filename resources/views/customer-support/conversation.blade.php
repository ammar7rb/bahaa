@extends('layouts.front-end.app')
@section('title','التواصل مع الدعم')
@section('content')
<main class="container py-5" dir="rtl"><a href="{{ route('account-tickets') }}">محادثات الدعم</a><h1>{{ $conversation->purpose === 'account_activation' ? 'تفعيل الحساب' : 'التواصل مع الدعم' }}</h1>
    @if($errors->any())<div role="alert" class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <p class="border rounded p-3" style="white-space:pre-wrap">{{ $conversation->description }}</p>
    @foreach($conversation->attachment_full_url ?? [] as $image)<a href="{{ $image['path'] }}" target="_blank" rel="noopener">مرفق سابق</a>@endforeach
    <section aria-label="الرسائل السابقة">
    @foreach($conversation->conversations->sortBy('id') as $message)
        <article class="border-bottom py-3"><strong>{{ $message->customer_message ? 'أنت' : 'الدعم' }}</strong><p style="white-space:pre-wrap">{{ $message->customer_message ?: $message->admin_message }}</p><small>{{ $message->created_at }}</small>@foreach($message->attachment_full_url ?? [] as $image)<a href="{{ $image['path'] }}" target="_blank" rel="noopener">مرفق الرسالة</a>@endforeach</article>
    @endforeach
    </section>
    <form class="mt-4" method="post" action="{{ route('support-ticket.comment',$conversation->id) }}" enctype="multipart/form-data">@csrf<label for="support-message">رسالتك</label><textarea class="form-control" id="support-message" name="comment" rows="3" required maxlength="5000"></textarea><label class="mt-2" for="support-images">إرفاق صورة (اختياري)</label><input id="support-images" type="file" name="image[]" multiple accept="image/jpeg,image/png,image/webp"><button class="btn btn-primary mt-3" type="submit">إرسال الرسالة</button></form>
</main>
@endsection
