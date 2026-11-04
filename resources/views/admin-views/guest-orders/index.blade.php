@extends('layouts.admin.app')
@section('title', 'طلبات زوار')
@section('content')
<main class="content container-fluid" dir="rtl">
    <header class="mb-4">
        <h1 class="h2">طلبات زوار <span class="badge badge-soft-dark">{{ $reviews->total() }}</span></h1>
        <p class="text-muted">طلبات الحسابات المنشأة من نموذج الشراء السريع، مع بيانات التحويل وإثبات الدفع وإجراءات المراجعة. يكمل العميل الاشتراك بنفس الحساب.</p>
    </header>
    @include('admin-views.payment-reviews._review-list')
</main>
@endsection
