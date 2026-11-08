@extends('layouts.admin.app')
@section('title', 'طلبات الائتمان')
@section('content')
<div class="content container-fluid" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4"><h1 class="page-header-title">طلبات الائتمان</h1><a href="{{ route('admin.orders.center') }}">مركز الطلبات</a></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="get" class="card card-body mb-3"><div class="d-flex gap-2"><input name="q" value="{{ request('q') }}" class="form-control" maxlength="100" placeholder="اسم العميل أو هاتفه"><button class="btn btn-primary">بحث</button></div></form>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>الطلب</th><th>العميل</th><th>تاريخ الطلب</th><th>الحالة والقرار</th><th>الرد</th></tr></thead><tbody>
    @forelse($tickets as $ticket)
        <tr><td>#{{ $ticket->id }}</td><td>{{ trim(($ticket->customer?->f_name ?? '').' '.($ticket->customer?->l_name ?? '')) }}<div class="small" dir="ltr">{{ $ticket->customer?->phone }}</div></td><td>{{ $ticket->created_at?->format('Y-m-d H:i') }}</td>
            <td>{{ ['approved'=>'مقبول','rejected'=>'مرفوض','needs_information'=>'مطلوب استكمال'][$ticket->review_status] ?? 'بانتظار قرار' }}@if($ticket->reviewed_at)<div class="small text-muted">{{ $ticket->reviewed_at->format('Y-m-d H:i') }}</div>@endif</td>
            <td>@if($ticket->reviewed_at)<div>{{ $ticket->review_note }}</div>@endif @if(!$ticket->reviewed_at || $ticket->review_status === 'needs_information')<form method="post" action="{{ route('admin.orders.special.credit-decision', $ticket->id) }}">@csrf<div class="d-flex flex-column gap-2"><select name="decision" class="form-control" required><option value="approved">مقبول</option><option value="rejected">مرفوض</option><option value="needs_information">مطلوب استكمال</option></select><textarea name="message" class="form-control" maxlength="2000" required placeholder="رسالة القرار للعميل"></textarea><button class="btn btn-primary btn-sm">إرسال وتسجيل القرار</button></div></form>@endif<a href="{{ route('admin.support-ticket.singleTicket', $ticket->id) }}" class="small">المحادثة</a></td>
        </tr>
    @empty<tr><td colspan="5" class="text-center p-5">لا توجد طلبات ائتمان.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $tickets->links() }}</div></div>
</div>
@endsection
