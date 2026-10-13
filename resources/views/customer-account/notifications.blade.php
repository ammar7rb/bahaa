@extends('layouts.front-end.app')
@section('title','الإشعارات')
@push('css_or_js')<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-communication.css') }}?v=20261013">@endpush
@section('content')
<main class="container customer-communication-page" dir="rtl"><div class="row g-4">
    @includeFirst(['theme-views.partials._profile-aside','web-views.partials._profile-aside'])
    <section class="col-lg-9"><header class="customer-communication-heading"><h1>الإشعارات</h1><p>تحديثات الإدارة المتعلقة بحسابك وطلباتك وباقتك.</p></header>
        <div class="customer-communication-list">
        @forelse($notifications as $notice)<article class="customer-communication-item"><span class="customer-communication-icon" aria-hidden="true">@include('customer-subscription.icon',['name'=>'bell'])</span><div><h2>{{ $notice->title }}</h2><p>{{ $notice->description }}</p>@if($notice->created_at)<time datetime="{{ $notice->created_at->toIso8601String() }}">{{ $notice->created_at->format('Y/m/d · H:i') }}</time>@endif</div></article>
        @empty<div class="customer-communication-empty"><h2>لا توجد إشعارات حاليًا</h2><p>ستظهر هنا الإشعارات التي ترسلها الإدارة إليك.</p></div>@endforelse
        </div><div class="mt-4">{{ $notifications->links() }}</div>
    </section>
</div></main>
@endsection
