@extends('layouts.admin.app')
@section('title', translate('inbox_title'))
@section('content')
<div class="admin-dashboard">
 <div class="admin-page-heading"><h1>{{ translate('inbox_title') }}</h1></div>
 <form method="get" class="admin-inbox-filter"><label for="notification-category">{{ translate('inbox_filter') }}</label><select id="notification-category" name="category" class="form-control"><option value="">{{ translate('all') }}</option>@foreach(\App\Services\AdminNotificationInbox::CATEGORIES as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ translate('inbox_'.$category) }}</option>@endforeach</select><button class="btn btn-primary">{{ translate('filter') }}</button></form>
 <p data-inbox-status role="status" hidden></p>
 <div class="card">
 @forelse($items as $item)
 <div class="admin-notification-row">
 @include('layouts.admin.partials.v2._notification-item', ['item'=>$item, 'kind'=>'inbox'])
 <button type="button" class="btn btn-sm btn-outline-primary" data-notice-toggle="{{ $item['key'] }}" data-unread="{{ $item['unread'] ? '0' : '1' }}">{{ translate($item['unread'] ? 'inbox_mark_read' : 'inbox_mark_unread') }}</button>
 </div>
 @empty
 <p class="admin-empty">{{ translate('inbox_empty') }}</p>
 @endforelse
 </div>
 {{ $items->links() }}
</div>
@endsection
