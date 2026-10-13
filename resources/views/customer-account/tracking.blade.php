@extends('layouts.front-end.app')
@section('title','تتبع الطلبات')
@push('css_or_js')<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-communication.css') }}?v=20261013.6">@endpush
@section('content')
<main class="container customer-communication-page" dir="rtl"><div class="row g-4">@includeFirst(['theme-views.partials._profile-aside','web-views.partials._profile-aside'])<section class="col-lg-9"><header class="customer-communication-heading"><h1>تتبع الطلبات</h1><p>تابع الحالة الفعلية لطلباتك، من الاستلام إلى التسليم.</p></header>
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
@if(session('tracking_message'))<p role="status">{{ session('tracking_message') }}</p>@endif
<form class="customer-tracking-search" method="get" action="{{ route('customer.tracking.index') }}"><label for="tracking-order-id">رقم الطلب<input class="form-control" type="number" id="tracking-order-id" name="order_id" min="1" value="{{ request('order_id') }}" placeholder="اكتب رقم طلبك"></label><button class="btn btn-primary" type="submit">تتبع الطلب</button>@if(request('order_id'))<a href="{{ route('customer.tracking.index') }}">كل طلباتي</a>@endif</form>
<div class="customer-communication-list">@forelse($orders as $order)
@php($state=app(\App\Services\CustomerPurchaseEligibilityService::class)->orderPayload($order))
@php($labels=['pending'=>'قيد الانتظار','confirmed'=>'تم التأكيد','processing'=>'قيد التجهيز','out_for_delivery'=>'في الطريق للتسليم','delivered'=>'تم التسليم','canceled'=>'ملغي','failed'=>'تعذر التسليم','returned'=>'تم الإرجاع'])
@php($steps=['pending','confirmed','processing','out_for_delivery','delivered'])
@php($position=array_search($order->order_status,$steps,true))
<article class="customer-tracking-order"><header><h2>طلب #{{ $order->id }}</h2><span>{{ $labels[$order->order_status] ?? 'قيد المتابعة' }}</span></header><time>{{ $order->created_at?->format('Y/m/d · H:i') }}</time>
@if($position!==false)<ol class="customer-tracking-steps" aria-label="مراحل الطلب">@foreach($steps as $index=>$step)<li class="{{ $index<=$position ? 'completed' : '' }}" @if($index===$position) aria-current="step" @endif><span aria-hidden="true">{{ $index+1 }}</span>{{ $labels[$step] }}</li>@endforeach</ol>@endif
@if(!empty($state['pending_reasons']) || $order->is_pause)<p class="customer-tracking-hold">{{ $order->is_pause ? 'الطلب متوقف مؤقتًا لدى الإدارة.' : $state['message'] }}</p>@endif
<div class="customer-tracking-actions"><span>الدفع: {{ $order->payment_status==='paid' ? 'تم تأكيد الدفع' : 'لم يُؤكَّد الدفع بعد' }}</span><a href="{{ route('account-order-details',['id'=>$order->id]) }}">تفاصيل الطلب ←</a></div></article>
@empty<div class="customer-communication-empty"><h2>لا توجد طلبات لتتبعها بعد</h2><p>ستظهر طلباتك هنا بعد إنشائها.</p></div>@endforelse</div><div class="mt-4">{{ $orders->links() }}</div></section></div></main>
@endsection
