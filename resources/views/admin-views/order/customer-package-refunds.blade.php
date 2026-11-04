@extends('layouts.admin.app')
@section('title', 'طلبات رد المشتريات')
@section('content')
<div class="content container-fluid" dir="rtl">
    <h1 class="h2">طلبات رد المشتريات بعد إلغاء الطلب</h1>
    <p>تبدأ مهلة الرد من تاريخ طلب الإلغاء. عند اختيار رد خارجي، سجّل المرجع بعد تنفيذ التحويل فعليًا.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="card"><div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>الطلب والعميل</th><th>المبلغ المدفوع</th><th>تاريخ الطلب وآخر موعد</th><th>حالة الرد</th><th>قرار الإدارة</th></tr></thead>
        <tbody>@forelse($refunds as $refund)
            <tr>
                <td><a href="{{ route('admin.orders.details', $refund->order_id) }}">#{{ $refund->order_id }}</a><br>العميل #{{ $refund->customer_id }}<br><small>طريقة الدفع: {{ $refund->order?->payment_method }}</small></td>
                <td>{{ $refund->amount }}</td>
                <td>{{ $refund->created_at?->format('Y-m-d H:i') }}<br>{{ $refund->due_at?->format('Y-m-d H:i') }} @if($refund->status === 'customer_requested' && $refund->due_at?->isPast())<strong class="text-danger">متأخر</strong>@endif</td>
                <td>{{ $refund->status === 'customer_requested' ? 'بانتظار قرار الإدارة' : 'تم تسجيل الرد' }} @if($refund->completed_at)<br>{{ $refund->completed_at->format('Y-m-d H:i') }}@endif</td>
                <td>@if($refund->status === 'customer_requested')
                    <form method="post" action="{{ route('admin.orders.package-refunds.complete', $refund->id) }}" onsubmit="return confirm('تأكيد تنفيذ الرد؟ اختيار المحفظة يضيف المبلغ للمحفظة فورًا، والرد الخارجي يجب أن يكون قد تم بالفعل.')">
                        @csrf
                        <label>طريقة الرد<select class="form-control" name="method" required><option value="">اختر الطريقة</option><option value="purchase_wallet">إضافة إلى محفظة المشتريات</option><option value="external">رد خارجي تم تنفيذه</option></select></label>
                        <label>مرجع الرد الخارجي<input class="form-control" name="reference" maxlength="191"></label>
                        <label>ملاحظة<input class="form-control" name="note" maxlength="2000"></label>
                        <button class="btn btn-primary mt-2" type="submit">تأكيد تنفيذ الرد</button>
                    </form>
                @else <span>{{ $refund->refund_method === 'purchase_wallet' ? 'محفظة المشتريات' : 'رد خارجي' }}</span> @endif</td>
            </tr>
        @empty<tr><td colspan="5">لا توجد طلبات رد.</td></tr>@endforelse</tbody>
    </table></div><div class="card-body">{{ $refunds->links() }}</div></div>
</div>
@endsection
