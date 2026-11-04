@extends('layouts.front-end.app')
@section('title','محادثة الدعم')
@push('css_or_js')
<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-support-chat.css') }}?v=20261016.5">
@endpush
@section('content')
<main class="container support-chat-page" dir="rtl">
    <a class="support-chat-back" href="{{ route('account-tickets') }}">@include('customer-subscription.icon',['name'=>'arrow']) جميع المحادثات</a>
    <section class="support-chat" data-support-chat data-feed="{{ route('support-ticket.messages',$conversation->id) }}" data-download="{{ route('support-ticket.attachments.download',[$conversation->id,'MESSAGE',0]) }}">
        @include('customer-support.activation-result', ['hidden' => true, 'redirectUrl' => route('home')])
        <header class="support-chat-header">
            <span class="support-chat-avatar">@include('customer-subscription.icon',['name'=>'message'])</span>
            <div><h1>{{ 'محادثة الدعم' }}</h1></div>
            <span class="support-chat-status" data-chat-status role="status" hidden></span>
        </header>
        <div class="support-chat-intro"><p>{{ $conversation->description }}</p>
            @foreach($conversation->attachment_full_url ?? [] as $file)
                @include('customer-support.attachment',['file'=>$file,'messageId'=>'initial','attachmentIndex'=>$loop->index])
            @endforeach
        </div>
        <div class="support-chat-log" role="log" aria-label="رسائل المحادثة" aria-live="polite" aria-relevant="additions" tabindex="0" data-chat-log>
            @foreach($conversation->conversations->sortBy('id') as $message)
                <article class="support-chat-message {{ $message->admin_id ? 'is-support' : 'is-own' }}" data-message-id="{{ $message->id }}">
                    <strong>{{ $message->admin_id ? 'فريق الدعم' : 'أنت' }}</strong>
                    <p>{{ $message->admin_message ?: $message->customer_message }}</p>
                    @foreach($message->attachment_full_url ?? [] as $file)
                        @include('customer-support.attachment',['file'=>$file,'messageId'=>$message->id,'attachmentIndex'=>$loop->index])
                    @endforeach
                    <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('Y/m/d · H:i') }}</time>
                </article>
            @endforeach
            <p class="support-chat-empty" data-chat-empty @if($conversation->conversations->isNotEmpty()) hidden @endif>ابدأ المحادثة بإرسال رسالة أو مرفق. ستظهر ردود الإدارة هنا تلقائيًا.</p>
        </div>
        <button type="button" class="support-chat-new" data-chat-new hidden>رسائل جديدة · الانتقال لآخر رسالة</button>
        <form class="support-chat-composer" method="post" action="{{ route('support-ticket.comment',$conversation->id) }}" enctype="multipart/form-data" data-chat-form>
            @csrf
            <div class="support-chat-error" role="alert" data-chat-error @if(!$errors->any()) hidden @endif>{{ $errors->first() }}</div>
            <label for="support-message">رسالتك إلى فريق الدعم</label>
            <div class="support-chat-input-row">
                <textarea id="support-message" name="comment" rows="2" maxlength="5000" placeholder="اكتب رسالتك هنا…">{{ old('comment') }}</textarea>
                <button class="support-chat-send" type="submit" data-chat-send>@include('customer-subscription.icon',['name'=>'send'])<span>إرسال</span></button>
            </div>
            <div class="support-chat-tools">
                <label class="support-chat-attach" for="support-images">@include('customer-subscription.icon',['name'=>'attachment'])<span>إضافة مرفقات</span><input id="support-images" type="file" name="image[]" multiple accept="image/jpeg,image/png,image/gif,image/webp,.pdf,.doc,.docx" data-chat-files></label>
                <span>صور، PDF أو Word · حتى 6 ميجابايت للملف</span>
            </div>
            <div class="support-chat-selection" data-chat-selection aria-live="polite"></div>
        </form>
    </section>
</main>
@endsection
@push('script')<script src="{{ asset('assets/front-end/js/customer-support-chat.js') }}?v=20261016.5" defer></script>@endpush
