@extends('layouts.admin.app')
@section('title', $purchaseQueue ? 'شراء الأجهزة المتخصصة' : 'فحص الأجهزة المتخصصة')
@section('content')
<div class="content container-fluid" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4"><h1 class="page-header-title">{{ $purchaseQueue ? 'طلبات شراء الأجهزة المتخصصة' : 'طلبات فحص الأجهزة المتخصصة' }}</h1><a href="{{ route('admin.orders.center') }}">مركز الطلبات</a></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="get" class="card card-body mb-3"><div class="d-flex gap-2"><input name="q" value="{{ request('q') }}" class="form-control" maxlength="100" placeholder="الجهاز أو هاتف العميل"><button class="btn btn-primary">بحث</button></div></form>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>الطلب والجهاز</th><th>العميل</th><th>الرسوم</th><th>المرحلة</th><th>الإجراء</th></tr></thead><tbody>
    @forelse($inquiries as $inquiry)
        @php($purchase = $inquiry->reportPurchase)
        <tr><td><a href="{{ route('admin.operations.medical-devices.inquiry', $inquiry) }}">#{{ $inquiry->id }} · {{ $inquiry->device_name }}</a><div class="small text-muted">{{ $inquiry->created_at?->format('Y-m-d H:i') }}</div></td>
            <td>{{ trim(($inquiry->customer?->f_name ?? '').' '.($inquiry->customer?->l_name ?? '')) }}<div class="small" dir="ltr">{{ $inquiry->customer?->phone }}</div></td><td>{{ webCurrencyConverter($purchase->amount) }}</td>
            <td>@if($purchase->purchase_requested_at)طلب شراء {{ $purchase->purchase_requested_at->format('Y-m-d H:i') }}@elseif($purchase->report_sent_at)أُرسل التقرير {{ $purchase->report_sent_at->format('Y-m-d H:i') }}@elseif($purchase->confirmed_at)جاري إعداد التقرير@elseبانتظار مراجعة الدفع@endif</td>
            <td>@if(!$purchaseQueue && !$purchase->confirmed_at)<form method="post" action="{{ route('admin.orders.special.confirm-inspection', $purchase) }}">@csrf<button class="btn btn-primary btn-sm">تأكيد الدفع</button></form>@endif<a href="{{ route('admin.support-ticket.singleTicket', $inquiry->support_ticket_id) }}" class="btn btn-outline-primary btn-sm">مراسلة العميل</a></td></tr>
    @empty<tr><td colspan="5" class="text-center p-5">لا توجد طلبات في هذه القائمة.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $inquiries->links() }}</div></div>
</div>
@endsection
