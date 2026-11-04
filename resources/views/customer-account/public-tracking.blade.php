@extends('layouts.front-end.app')
@section('title','تتبع الطلب')
@push('css_or_js')<link rel="stylesheet" href="{{ asset('assets/front-end/css/customer-communication.css') }}">@endpush
@section('content')
<main class="container customer-communication-page" dir="rtl"><header class="customer-communication-heading"><h1>تتبع طلب #{{ $tracking['id'] }}</h1><p>حالة التنفيذ وتحديثاتها</p></header>
@php($labels=['pending'=>'قيد الانتظار','confirmed'=>'تم التأكيد','processing'=>'جاري التجهيز','out_for_delivery'=>'جاري الشحن','delivered'=>'تم التسليم','canceled'=>'ملغي','returned'=>'تم الإرجاع','failed'=>'تعذر التسليم'])
<article class="customer-tracking-order"><h2>{{ $labels[$tracking['status']] ?? 'قيد المتابعة' }}</h2><time>{{ $tracking['created_at'] }}</time>
<ol>@foreach($tracking['history'] as $event)<li>{{ $labels[$event['status']] ?? 'تحديث الطلب' }} — {{ $event['at'] }}</li>@endforeach</ol>
<a class="btn btn-primary" href="{{ route('track-order.index') }}">تتبع طلب آخر</a>
</article></main>
@endsection
