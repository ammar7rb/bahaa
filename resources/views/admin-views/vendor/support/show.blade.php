@extends('layouts.admin.app')
@section('title', $thread->subject)
@section('content')
<div class="content container-fluid">
    <a href="{{ route('admin.vendor-support.index') }}">{{ translate('vendor_support_conversations') }}</a><h1 class="h2 my-3">{{ $thread->subject }}</h1><p>{{ $thread->seller?->administrative_reference }} — {{ $thread->seller?->shop?->name }}</p>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="card mb-3"><div class="card-body">@foreach($thread->messages as $message)<article class="border-bottom py-3"><strong>{{ translate('administration_message') }}</strong><p style="white-space:pre-wrap">{{ $message->body }}</p><small>{{ $message->created_at->format('Y-m-d H:i') }}</small></article>@endforeach</div></div>
    @if($thread->status === 'open')<form class="card mb-3" method="post" action="{{ route('admin.vendor-support.reply', $thread) }}">@csrf<div class="card-body"><label for="support-reply">{{ translate('message') }}</label><textarea class="form-control" name="body" id="support-reply" maxlength="10000" rows="4" required>{{ old('body') }}</textarea><button class="btn btn-primary mt-3">{{ translate('send') }}</button></div></form>@endif
    <form method="post" action="{{ route('admin.vendor-support.status', $thread) }}">@csrf<input type="hidden" name="status" value="{{ $thread->status === 'open' ? 'closed' : 'open' }}"><button class="btn btn-outline-secondary">{{ translate($thread->status === 'open' ? 'vendor_close_conversation' : 'vendor_reopen_conversation') }}</button></form>
</div>
@endsection
