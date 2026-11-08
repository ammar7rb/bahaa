@extends('layouts.admin.app')

@section('title', 'مركز الطلبات')

@section('content')
@php
    $queues = [
        'all' => ['الكل', '🛒'],
        'awaiting_admin' => ['لم تتم الموافقة', '◷'],
        'sent_to_vendor' => ['مرسلة للتاجر', '➤'],
        'accepted' => ['مقبولة', '✓'],
        'rejected' => ['مرفوضة من التاجر', '×'],
        'overdue' => ['معلقة / متأخرة', '⌛'],
        'canceled' => ['ملغاة', '×'],
        'completed' => ['مكتملة', '✓'],
        'guests' => ['طلبات الزوار', '♙'],
        'history' => ['سجل الطلبات الأقدم', '◴'],
    ];
    $statusLabels = ['awaiting_admin'=>'لم تتم الموافقة', 'sent_to_vendor'=>'مرسلة للتاجر', 'accepted'=>'مقبولة', 'overdue'=>'معلقة / متأخرة', 'rejected'=>'مرفوضة', 'canceled'=>'ملغاة', 'completed'=>'مكتملة'];
@endphp
<div class="content container-fluid" dir="rtl">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div><h1 class="page-header-title mb-1">مركز الطلبات</h1><p class="text-muted mb-0">تابع الطلبات على مستوى كل منتج وتاجر، مع بقاء رقم الطلب الأصلي جامعًا لكل أجزائه.</p></div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.orders.admin-gate.index') }}" class="btn btn-primary">مراجعة الطلبات الجديدة</a>
            <a href="{{ route('admin.guest-orders.index') }}" class="btn btn-outline-primary">طلبات الزوار</a>
            <a href="{{ route('admin.orders.cancellations') }}" class="btn btn-outline-primary">معالجة الإلغاء والأرصدة</a>
            @if(\App\Utils\Helpers::module_permission_check('people'))
                <a href="{{ route('admin.orders.special.credit') }}" class="btn btn-outline-primary">طلبات الائتمان</a>
            @endif
            @if(\App\Utils\Helpers::module_permission_check('catalog') && \App\Utils\Helpers::module_permission_check('people'))
                <a href="{{ route('admin.orders.special.inspections') }}" class="btn btn-outline-primary">فحص الأجهزة</a>
                <a href="{{ route('admin.orders.special.device-purchases') }}" class="btn btn-outline-primary">شراء الأجهزة المتخصصة</a>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach($queues as $key => [$label, $icon])
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('admin.orders.center', ['queue'=>$key]) }}" class="card h-100 text-decoration-none {{ $queue === $key ? 'border-primary shadow-sm' : '' }}">
                    <div class="card-body d-flex justify-content-between align-items-center gap-2"><div><div class="small text-muted">{{ $label }}</div><div class="fs-3 fw-bold text-dark">{{ number_format($counts[$key] ?? 0) }}</div></div><span class="rounded-3 px-3 py-2 bg-light fs-4">{{ $icon }}</span></div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card mb-3"><div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="queue" value="{{ $queue }}">
            <div class="col-md-4"><label class="form-label">بحث</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="الطلب أو المنتج أو العميل أو التاجر"></div>
            <div class="col-md-2"><label class="form-label">رقم التاجر</label><input name="seller_id" value="{{ request('seller_id') }}" type="number" min="1" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">من تاريخ</label><input name="from" value="{{ request('from') }}" type="date" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">إلى تاريخ</label><input name="to" value="{{ request('to') }}" type="date" class="form-control"></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1">بحث</button><a class="btn btn-outline-secondary" href="{{ route('admin.orders.center', ['queue'=>$queue]) }}">مسح</a></div>
        </form>
        <div class="d-flex flex-wrap gap-2 mt-3">
            @foreach($queues as $key => [$label])
                <a class="btn btn-sm {{ $queue === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.orders.center', array_merge(['queue'=>$key], request()->only(['q','from','to','seller_id']))) }}">{{ $label }} <span class="opacity-75">{{ $counts[$key] ?? 0 }}</span></a>
            @endforeach
        </div>
    </div></div>

    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>الطلب / الجزء</th><th>المنتج</th><th>العميل</th><th>التاجر</th><th>الحالة</th><th>مهلة رد التاجر</th><th>الإجراء</th></tr></thead>
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
                <td><a class="fw-bold" href="{{ $target }}">#{{ $order?->id }}</a><div class="small text-muted">{{ $references[$item->id] ?? ('حصة المنتج #'.($line?->id ?? '—')) }}</div></td>
                <td><strong>{{ $productName }}</strong><div class="small text-muted">كمية: {{ $line?->qty ?? 0 }} · كود المنتج #{{ $item->product_id ?: '—' }}</div></td>
                <td>{{ trim(($order?->customer?->f_name ?? '').' '.($order?->customer?->l_name ?? '')) ?: ($order?->is_guest ? 'زائر' : '—') }}<div class="small text-muted">{{ $order?->customer?->phone ?? '—' }}</div></td>
                <td>{{ $seller?->shop?->name ?? ('تاجر #'.($item->seller_id ?: '—')) }}</td>
                <td><span class="badge bg-{{ in_array($displayStatus, ['accepted','completed'] , true) ? 'success' : (in_array($displayStatus, ['overdue','rejected','canceled'], true) ? 'danger' : ($displayStatus === 'awaiting_admin' ? 'primary' : 'warning')) }}">{{ $statusLabels[$displayStatus] ?? $displayStatus }}</span></td>
                <td>@if($due) <span class="{{ $late ? 'text-danger fw-bold' : 'text-muted' }}">{{ $due->format('Y-m-d H:i') }}</span>@if($late)<div class="small text-danger">تجاوزت 24 ساعة</div>@endif @else — @endif</td>
                <td><div class="d-flex flex-wrap gap-1"><a class="btn btn-sm {{ $isReview ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ $target }}">{{ $isReview ? 'مراجعة وإرسال' : 'فتح الطلب' }}</a>@if(in_array($displayStatus, ['sent_to_vendor','accepted','overdue','rejected'], true))<a class="btn btn-sm btn-outline-warning" href="{{ route('admin.orders.reassignment.show', $item->id) }}">تعيين لتاجر آخر</a>@endif</div></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center p-5 text-muted">لا توجد طلبات في هذه القائمة.</td></tr>
        @endforelse
        </tbody>
    </table></div><div class="card-footer">{{ $items->links() }}</div></div>
</div>
@endsection
