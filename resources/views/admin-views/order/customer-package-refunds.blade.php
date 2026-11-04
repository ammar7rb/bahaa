@extends('layouts.admin.app')
@section('title', 'طلبات رد المشتريات')
@section('content')
<div class="content container-fluid" dir="rtl">
    <h1 class="h2">طلبات رد المشتريات بعد إلغاء الطلب</h1>
    <p>تبدأ مهلة الرد من اعتماد الإدارة. نفّذ الرد عبر وسيلة الدفع الأصلية ثم سجّل مرجع العملية.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if(\App\Utils\Helpers::module_permission_check('business_settings'))
        <form class="card card-body mb-3" method="post" action="{{ route('admin.orders.package-refunds.settings') }}">
            @csrf
            <label for="refund-days">مهلة رد مدفوعات العملاء من تاريخ الاعتماد</label>
            <input class="form-control" id="refund-days" type="number" name="days" min="15" max="30" value="{{ $refundDays }}" required>
            <button class="btn btn-outline-primary mt-2" type="submit">حفظ مهلة الرد</button>
        </form>
    @endif
    <div class="card"><div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>الطلب والعميل</th><th>المبلغ المدفوع</th><th>تاريخ الطلب وآخر موعد</th><th>حالة الرد</th><th>قرار الإدارة</th></tr></thead>
        <tbody>@forelse($refunds as $refund)
            <tr>
                <td><a href="{{ route('admin.orders.details', $refund->order_id) }}">#{{ $refund->order_id }}</a><br>العميل #{{ $refund->customer_id }}<br><small>طريقة الدفع: {{ $refund->order?->payment_method }}</small></td>
                <td>{{ $refund->amount }}</td>
                <td>{{ $refund->created_at?->format('Y-m-d H:i') }}
                    @if($refund->status !== 'customer_requested')<br>{{ $refund->due_at?->format('Y-m-d H:i') }}@endif
                    @if($refund->status === 'external_approved' && $refund->due_at?->isPast())<strong class="text-danger">متأخر</strong>@endif
                </td>
                <td>{{ ['customer_requested' => 'بانتظار قرار الإدارة', 'external_approved' => 'معتمد وبانتظار التحويل', 'completed' => 'تم تسجيل الرد'][$refund->status] ?? $refund->status }}</td>
                <td>
                    @if($refund->status === 'customer_requested')
                        <form method="post" action="{{ route('admin.orders.package-refunds.approve', $refund->id) }}">
                            @csrf
                            <p>مهلة الرد بعد الاعتماد: {{ $refundDays }} يومًا.</p>
                            <button class="btn btn-primary mt-2" type="submit">اعتماد الرد عبر وسيلة الدفع</button>
                        </form>
                    @elseif($refund->status === 'external_approved')
                        <form method="post" action="{{ route('admin.orders.package-refunds.complete', $refund->id) }}">
                            @csrf
                            <input type="hidden" name="method" value="external">
                            <label>مرجع التحويل المنفذ<input class="form-control" name="reference" maxlength="191" required></label>
                            <label>ملاحظة<input class="form-control" name="note" maxlength="2000"></label>
                            <button class="btn btn-primary mt-2" type="submit">تسجيل تنفيذ الرد</button>
                        </form>
                    @elseif($refund->completed_at)
                        {{ $refund->completed_at->format('Y-m-d H:i') }}
                    @endif
                </td>
            </tr>
        @empty<tr><td colspan="5">لا توجد طلبات رد.</td></tr>@endforelse</tbody>
    </table></div><div class="card-body">{{ $refunds->links() }}</div></div>
</div>
@endsection
