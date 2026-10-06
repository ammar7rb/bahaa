@extends('layouts.vendor.app')
@section('title', $data['subject'])
@section('content')
<div class="content container-fluid vendor-conversation-page">
    <a class="vendor-text-link" href="{{ route('vendor.messages') }}">{{ translate('vendor_back_to_conversations') }}</a>
    <div class="vendor-conversation-heading"><span class="vendor-thread-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'messages'])</span><div><h1>{{ $data['subject'] }}</h1><p>{{ translate('administration_messages') }} · {{ translate($data['status']) }}</p></div></div>
    <div class="vendor-conversation-history">
    @foreach($data['messages'] as $message)
        <article class="vendor-conversation-message"><div class="vendor-conversation-meta"><strong>{{ translate('administration_message') }}</strong><time>{{ \Carbon\Carbon::parse($message['created_at'])->translatedFormat('d M Y · H:i') }}</time></div><p class="vendor-message-body">{{ $message['body'] }}</p>
        @if($message['unread'])<form method="post" action="{{ route('vendor.inbox.read') }}">@csrf<input type="hidden" name="kind" value="message"><input type="hidden" name="id" value="{{ $message['id'] }}"><button class="vendor-read-button">{{ translate('mark_as_read') }}</button></form>@else<span class="vendor-read-status">{{ translate('vendor_read') }}</span>@endif
        </article>
    @endforeach
    </div>
    <p class="vendor-conversation-note">{{ translate('vendor_conversation_review_hint') }}</p>
</div>
@endsection
