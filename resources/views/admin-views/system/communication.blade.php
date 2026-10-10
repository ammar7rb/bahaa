@extends('layouts.admin.app')
@section('title', translate('workspace_communication_center'))
@section('content')
<div class="content container-fluid admin-dashboard">
    <div class="admin-page-heading"><div><h1>{{ translate('workspace_communication_center') }}</h1><p>{{ translate('workspace_communication_help') }}</p></div><span>{{ translate('unread_messages') }}: {{ $unread }}</span></div>
    <form class="admin-communication-search" method="get"><input type="search" class="form-control" name="q" value="{{ request('q') }}" maxlength="100" placeholder="{{ translate('Search') }}" aria-label="{{ translate('Search') }}"><button class="btn btn-primary">{{ translate('Search') }}</button></form>
    <section class="admin-activation">
        @forelse($conversations as $conversation)
            <div class="admin-communication-row">
                <a class="admin-conversation-link" href="{{ $workspace->conversationUrl($conversation) }}"><strong>{{ $conversation->subject ?: translate('messages') }} @if($conversation->unread)<span class="admin-account-status">{{ $conversation->unread }} {{ translate('unread_messages') }}</span>@endif</strong><span>{{ $conversation->sender ?: '#'.$conversation->id }} · {{ translate($conversation->audience) }} · {{ translate('workspace_channel_'.$conversation->kind) }}</span><span>{{ \Illuminate\Support\Str::limit(strip_tags($conversation->preview), 140) }}</span><time>{{ \Carbon\Carbon::parse($conversation->updated_at)->diffForHumans() }}</time></a>
                @if($conversation->unread && in_array($conversation->kind, ['contact', 'ticket']))
                    <form action="{{ route('admin.workspace.read') }}" method="post">@csrf<input type="hidden" name="kind" value="{{ $conversation->kind }}"><input type="hidden" name="id" value="{{ $conversation->id }}"><button class="btn btn-outline-primary">{{ translate('workspace_mark_read') }}</button></form>
                @endif
            </div>
        @empty
            <p class="admin-empty">{{ translate('workspace_search_empty') }}</p>
        @endforelse
    </section>
    <div class="mt-3">{{ $conversations->links() }}</div>
</div>
@endsection
