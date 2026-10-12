@extends('layouts.vendor.app')
@section('title', translate($kind === 'messages' ? 'administration_messages' : 'notifications'))
@section('content')
<div class="content container-fluid vendor-inbox-page">
    <div class="vendor-page-heading"><div><h1>{{ translate($kind === 'messages' ? 'administration_messages' : 'notifications') }}</h1><p>{{ translate($kind === 'messages' ? 'vendor_conversations_hint' : 'vendor_notifications_hint') }}</p></div><a class="btn btn-outline-primary" href="{{ route('vendor.account') }}">{{ translate('vendor_account') }}</a></div>
    <nav class="vendor-inbox-tabs" aria-label="{{ translate('vendor_navigation') }}"><a href="{{ route('vendor.messages') }}" class="{{ $kind === 'messages' ? 'is-active' : '' }}">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'messages']){{ translate('administration_messages') }}</a><a href="{{ route('vendor.notifications') }}" class="{{ $kind === 'notifications' ? 'is-active' : '' }}">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'notifications']){{ translate('notifications') }}</a></nav>
    <div class="vendor-inbox-surface">
    @if($kind === 'messages')
        @forelse($data['threads'] as $item)
            <a class="vendor-thread-row {{ $item['unread_count'] ? 'is-unread' : '' }}" href="{{ $item['action_url'] }}"><span class="vendor-thread-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'messages'])</span><span class="vendor-thread-copy"><strong>{{ $item['title'] }}</strong><span>{{ \Illuminate\Support\Str::limit($item['body'], 180) }}</span><small>{{ translate($item['status']) }} · {{ $item['message_count'] }} {{ translate('vendor_messages_count_label') }}</small></span><span class="vendor-thread-meta"><time>{{ \Carbon\Carbon::parse($item['created_at'])->diffForHumans() }}</time>@if($item['unread_count'])<span class="vendor-count is-green">+{{ $item['unread_count'] }}</span>@endif</span></a>
        @empty
            @include('vendor-views.account.inbox-empty', ['channel'=>'messages'])
        @endforelse
    @else
        @forelse($data['notifications'] as $item)
            <article class="vendor-notification-row {{ $item['unread'] ? 'is-unread' : '' }}"><span class="vendor-thread-icon">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>$item['kind'] === 'order_alert' ? 'orders' : 'notifications'])</span><div class="vendor-thread-copy"><h2>{{ $item['title'] }} @if($item['unread'])<span class="vendor-count">+1</span>@endif</h2>@if(isset($item['order_reference']))<span>{{ translate('order') }} <b dir="ltr">{{ $item['order_reference'] }}</b></span>@endif<p class="vendor-message-body">{{ $item['body'] }}</p><time>{{ \Carbon\Carbon::parse($item['created_at'])->diffForHumans() }}</time><div class="vendor-inbox-actions">@if(isset($item['action_url']))<a class="vendor-text-link" href="{{ $item['action_url'] }}">{{ translate('view_order') }}</a>@endif @if($item['unread'])<form method="post" action="{{ route('vendor.inbox.read') }}">@csrf<input type="hidden" name="kind" value="{{ $item['kind'] }}"><input type="hidden" name="id" value="{{ $item['id'] }}"><button type="submit" class="vendor-read-button">{{ translate('mark_as_read') }}</button></form>@endif</div></div></article>
        @empty
            @include('vendor-views.account.inbox-empty', ['channel'=>'notifications'])
        @endforelse
    @endif
    </div>
</div>
@endsection
