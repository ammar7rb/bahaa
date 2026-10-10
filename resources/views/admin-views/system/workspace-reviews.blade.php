@extends('layouts.admin.app')
@section('title', $title)
@section('content')
<div class="content container-fluid admin-dashboard">
    <div class="admin-page-heading"><h1>{{ $title }}</h1><span>{{ $records->total() }}</span></div>
    <section class="admin-activation">
        @forelse($records as $record)
            <a class="admin-inbox-row" href="{{ $record['url'] }}"><strong>{{ $record['title'] }}</strong><span>{{ $record['detail'] }}</span></a>
        @empty
            <p class="admin-empty">{{ translate('workspace_no_pending') }}</p>
        @endforelse
    </section>
    <div class="mt-3">{{ $records->links() }}</div>
</div>
@endsection
