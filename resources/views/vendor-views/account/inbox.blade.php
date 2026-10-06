@extends('layouts.vendor.app')
@section('title', translate($kind === 'messages' ? 'administration_messages' : 'notifications'))
@section('content')
<div class="content container-fluid vendor-account">
    <div class="vendor-page-heading"><h1>{{ translate($kind === 'messages' ? 'administration_messages' : 'notifications') }}</h1><a href="{{ route('vendor.account') }}">{{ translate('vendor_account') }}</a></div>
    <p class="text-muted">{{ translate($kind === 'messages' ? 'vendor_messages_hint' : 'vendor_notifications_hint') }}</p>
    <div class="card"><div class="card-body">
    @forelse($data[$kind] as $item)
        <article class="vendor-inbox-item {{ $item['unread'] ? 'is-unread' : '' }}">
            <div><h2>{{ $item['title'] }} @if($item['unread'])<span class="vendor-count {{ $kind === 'messages' ? 'is-green' : '' }}">+1</span>@endif</h2>
                @if(isset($item['order_reference']))<p>{{ translate('order') }} <b dir="ltr">{{ $item['order_reference'] }}</b></p>@endif
                <p class="vendor-message-body">{{ $item['body'] }}</p><time>{{ $item['created_at'] }}</time>
                @foreach($item['attachments'] ?? [] as $file)<a href="{{ $file['url'] }}" target="_blank" rel="noopener">{{ $file['name'] }}</a>@endforeach
            </div>
            <div class="vendor-inbox-actions">
                @if(isset($item['action_url']))<a class="btn btn-outline-primary" href="{{ $item['action_url'] }}">{{ translate('view_order') }}</a>@endif
                @if($item['unread'])<form method="post" action="{{ route('vendor.inbox.read') }}">@csrf<input type="hidden" name="kind" value="{{ $item['kind'] }}"><input type="hidden" name="id" value="{{ $item['id'] }}"><button type="submit" class="btn btn-light">{{ translate('mark_as_read') }}</button></form>@endif
            </div>
        </article>
    @empty
        <p class="text-center py-5">{{ translate('vendor_inbox_empty') }}</p>
    @endforelse
    </div></div>
</div>
@endsection
