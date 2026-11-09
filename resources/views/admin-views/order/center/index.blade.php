@extends('layouts.admin.app')

@section('title', 'مركز الطلبات')
@push('css_or_js')
<link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/orders-workspace.css') }}">
@endpush

@section('content')
@php
    $queues = \App\Support\OrderCenterFilters::OPTIONS;
    $statusLabels = ['awaiting_admin'=>'لم تتم الموافقة', 'sent_to_vendor'=>'مرسلة للتاجر', 'accepted'=>'مقبولة', 'overdue'=>'معلقة / متأخرة', 'rejected'=>'مرفوضة', 'canceled'=>'ملغاة', 'completed'=>'مكتملة'];
@endphp
<div class="content container-fluid orders-workspace" dir="rtl">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div><h1 class="page-header-title mb-1">مركز الطلبات</h1><p class="text-muted mb-0">تابع الطلبات على مستوى كل منتج وتاجر، مع بقاء رقم الطلب الأصلي جامعًا لكل أجزائه.</p></div>
    </div>
    <div class="card mb-3"><div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-12"><label class="form-label" for="orders-queue">نوع الطلبات</label><select id="orders-queue" name="queue" class="form-control">@foreach($queues as $key=>[$label])<option value="{{ $key }}" @selected($queue === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">بحث</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="الطلب أو المنتج أو العميل أو التاجر"></div>
            <div class="col-md-2"><label class="form-label">رقم التاجر</label><input name="seller_id" value="{{ request('seller_id') }}" type="number" min="1" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">من تاريخ</label><input name="from" value="{{ request('from') }}" type="date" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">إلى تاريخ</label><input name="to" value="{{ request('to') }}" type="date" class="form-control"></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1">بحث</button><a class="btn btn-outline-secondary" href="{{ route('admin.orders.center', ['queue'=>$queue]) }}">مسح</a></div>
        </form>
    </div></div>

    <div class="card"><div class="card-header d-flex justify-content-between"><h3 class="mb-0">قائمة الطلبات</h3><span class="badge bg-primary">{{ $items->total() }} حصة</span></div><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>الطلب / الجزء</th><th>المنتج</th><th>العميل</th><th>التاجر</th><th>المبلغ والدفع</th><th>الحالة</th><th>مهلة رد التاجر</th><th>الإجراء</th></tr></thead>
        <tbody>
        @forelse($items as $item)
            @php
                $line = $item->line;
                $order = $item->order;
                $productData = json_decode($line?->getRawOriginal('product_details') ?? '{}', true) ?: [];
                $productName = data_get($productData, 'name') ?: ('منتج #'.($item->product_id ?: $line?->product_id));
                $seller = $line?->seller ?: $order?->seller;
                $due = $item->vendor_response_due_at;
                $late = $item->status === 'sent_to_vendor' && $due && $due->isPast();
                $displayStatus = $late ? 'overdue' : $item->status;
                $isReview = $item->status === 'awaiting_admin';
                $target = $isReview ? route('admin.orders.admin-gate.show', $order->id) : route('admin.orders.details', $order->id);
            @endphp
            <tr>
                <td><a class="fw-bold" href="{{ $target }}">#{{ $order?->id }}</a><div class="small text-muted">{{ $references[$item->id] ?? ('حصة المنتج #'.($line?->id ?? '—')) }}</div><div class="small text-muted">{{ $order?->created_at?->format('Y-m-d H:i') }}</div></td>
                <td><strong>{{ $productName }}</strong><div class="small text-muted">كمية: {{ $line?->qty ?? 0 }} · كود المنتج #{{ $item->product_id ?: '—' }}</div></td>
                <td>{{ trim(($order?->customer?->f_name ?? '').' '.($order?->customer?->l_name ?? '')) ?: ($order?->is_guest ? 'زائر' : '—') }}<div class="small text-muted">{{ $order?->customer?->phone ?? '—' }}</div></td>
                <td>@if($seller)<a class="fw-semibold" href="{{ route('admin.vendors.view', ['id'=>$seller->id]) }}">{{ $seller->shop?->name ?? ('تاجر #'.$seller->id) }}</a><div class="small text-muted">#{{ $seller->id }}</div>@else — @endif</td>
                <td><strong>{{ $item->customer_total === null ? 'بانتظار المراجعة' : webCurrencyConverter($item->customer_total) }}</strong><div class="small text-muted">قيمة الحصة</div><div class="small">إجمالي الطلب: {{ webCurrencyConverter($order->order_amount) }}</div><span class="badge {{ $order->payment_status === 'paid' ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ translate($order->payment_status) }}</span></td>
                <td><span class="badge bg-{{ in_array($displayStatus, ['accepted','completed'] , true) ? 'success' : (in_array($displayStatus, ['overdue','rejected','canceled'], true) ? 'danger' : ($displayStatus === 'awaiting_admin' ? 'primary' : 'warning')) }}">{{ $statusLabels[$displayStatus] ?? $displayStatus }}</span><div class="small text-muted mt-1">{{ translate($order->order_status) }}</div><div class="small text-muted">{{ translate($order->shipping_fulfillment_mode ?? 'pending') }}</div></td>
                <td>@if($due) <span class="{{ $late ? 'text-danger fw-bold' : 'text-muted' }}">{{ $due->format('Y-m-d H:i') }}</span>@if($late)<div class="small text-danger">تجاوزت 24 ساعة</div>@endif @else — @endif</td>
                <td><div class="d-flex flex-wrap gap-1"><a class="btn btn-sm {{ $isReview ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ $target }}">{{ $isReview ? 'مراجعة وإرسال' : 'فتح الطلب' }}</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.orders.generate-invoice', [$order->id]) }}" aria-label="فاتورة الطلب {{ $order->id }}"><i class="fi fi-rr-document" aria-hidden="true"></i> فاتورة</a>@if(in_array($displayStatus, ['sent_to_vendor','accepted','overdue','rejected'], true))<a class="btn btn-sm btn-outline-warning" href="{{ route('admin.orders.reassignment.show', $item->id) }}">تعيين لتاجر آخر</a>@endif</div></td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center p-5 text-muted">لا توجد طلبات في هذه القائمة.</td></tr>
        @endforelse
        </tbody>
    </table></div><div class="card-footer">{{ $items->links() }}</div></div>
    @include('admin-views.order.partials._order-services')
</div>
@endsection
