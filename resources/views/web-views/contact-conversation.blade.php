@extends('layouts.front-end.app')
@section('title', translate('support_conversation'))
@section('content')
<div class="container py-4 text-align-direction"><div class="row justify-content-center"><div class="col-lg-9">
<div class="card"><div class="card-body p-4">
    <h1 class="h4">{{ translate('support_conversation') }} #{{ $contact->id }}</h1>
    <p class="text-muted">{{ translate('conversation_keep_open_notice') }}</p>
    <h2 class="h6">{{ $contact->subject }}</h2>
    <p class="border rounded p-3" style="white-space:pre-wrap">{{ $contact->message }}</p>
    @if($contact->reply && !$messages->contains('sender', 'admin'))
        <p class="alert alert-info">{{ data_get(json_decode($contact->reply, true), 'body') }}</p>
    @endif
    <div id="contact-message-list" aria-live="polite">
        @foreach($messages as $message)
            <div class="border rounded p-3 mb-3" data-message-id="{{ $message->id }}">
                <strong class="text-primary">{{ $message->sender === 'admin' ? translate('admin') : $contact->name }}</strong>
                <p class="mb-0 mt-2" style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $message->body }}</p>
            </div>
        @endforeach
    </div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('contact.conversation.reply', $contact->id) }}">
        @csrf
        <label for="contact-reply">{{ translate('message') }}</label>
        <textarea id="contact-reply" class="form-control mb-3" name="message" rows="3" maxlength="5000" required>{{ old('message') }}</textarea>
        <button class="btn btn--primary" type="submit">{{ translate('send') }}</button>
    </form>
</div></div></div></div></div>
@endsection
@push('script')
<script>
(() => {
    const list = document.getElementById('contact-message-list');
    const labels = {admin: @json(translate('admin')), requester: @json($contact->name)};
    async function refresh() {
        try {
            if (document.hidden) return;
            const response = await fetch(@json(route('contact.conversation', $contact->id)), {headers: {'Accept':'application/json'}, cache:'no-store'});
            if (!response.ok) return;
            const data = await response.json();
            for (const message of data.messages) {
                if (list.querySelector('[data-message-id="' + Number(message.id) + '"]')) continue;
                const box = document.createElement('div');
                box.className = 'border rounded p-3 mb-3'; box.dataset.messageId = message.id;
                const label = document.createElement('strong'); label.className = 'text-primary'; label.textContent = labels[message.sender];
                const body = document.createElement('p'); body.className = 'mb-0 mt-2'; body.style.whiteSpace = 'pre-wrap'; body.style.overflowWrap = 'anywhere'; body.textContent = message.body;
                box.append(label, body); list.append(box);
            }
        } catch (_) {} finally { window.setTimeout(refresh, 12000); }
    }
    window.setTimeout(refresh, 12000);
})();
</script>
@endpush
