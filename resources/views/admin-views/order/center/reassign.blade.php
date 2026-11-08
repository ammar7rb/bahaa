@extends('layouts.admin.app')

@section('title', 'إعادة تعيين منتج لتاجر')

@section('content')
@php($line = $workItem->line)
@php($order = $workItem->order)
<div class="content container-fluid" dir="rtl">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
        <div><h1 class="page-header-title mb-1">إعادة تعيين المنتج #{{ $line->id }}</h1><p class="text-muted mb-0">الطلب #{{ $order->id }} · {{ $order->order_group_id }} · التاجر الحالي: {{ $workItem->seller?->shop?->name ?? '#'.$workItem->seller_id }}</p></div>
        <a class="btn btn-outline-secondary" href="{{ route('admin.orders.center') }}">مركز الطلبات</a>
    </div>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="alert alert-info">سيُحفظ مبلغ العميل داخل مجموعة الطلب نفسها. يتحول المنتج إلى طلب جديد ينتظر مراجعة الإدارة، ويُسجل نقل المبالغ والمنتج والتاجر في سجل مستقل.</div>
    <form method="get" class="card card-body mb-3"><label class="form-label">ابحث باسم المتجر أو رمز المنتج</label><div class="d-flex gap-2"><input class="form-control" name="q" value="{{ request('q') }}" maxlength="100"><button class="btn btn-outline-primary">بحث</button></div></form>
    <form method="post" action="{{ route('admin.orders.reassignment.store', $workItem->id) }}" class="card">@csrf
        <div class="card-header"><h4 class="mb-0">التجار الذين لديهم المنتج نفسه</h4></div>
        <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>اختيار</th><th>التاجر</th><th>المنتج</th><th>سعر التاجر الحالي</th><th>المتاح</th></tr></thead><tbody>
            @forelse($candidates as $candidate)
            <tr><td><input type="radio" name="target_product_id" value="{{ $candidate->id }}" @checked(old('target_product_id') == $candidate->id) required></td><td>{{ $candidate->seller?->shop?->name ?? '#'.$candidate->user_id }}</td><td>{{ $candidate->name }}<div class="small text-muted">#{{ $candidate->id }} · {{ $candidate->code }}</div></td><td>{{ webCurrencyConverter($candidate->unit_price) }}</td><td>{{ $candidate->current_stock }}</td></tr>
            @empty<tr><td colspan="5" class="text-center p-4">لا يوجد تاجر آخر يملك المنتج بهذه المواصفات.</td></tr>@endforelse
        </tbody></table></div>
        <div class="card-body"><label class="form-label">سبب إعادة التعيين</label><textarea name="reason" class="form-control" rows="3" maxlength="2000" required>{{ old('reason') }}</textarea></div>
        <div class="card-footer d-flex justify-content-end"><button class="btn btn-primary" @disabled($candidates->isEmpty())>نقل المنتج للتاجر المختار</button></div>
    </form>
</div>
@endsection
