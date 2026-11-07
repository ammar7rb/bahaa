@extends('layouts.admin.app')
@section('title', 'طلبات رد المشتريات')
@section('content')
<div class="content container-fluid" dir="rtl">
    <h1 class="h2">طلبات إلغاء المشتريات ورد قيمتها</h1>
    <p>تبدأ مهلة الرد من اعتماد الإدارة. يرد المبلغ إلى محفظة الحساب عند حلول الموعد.</p>
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
                    @if(in_array($refund->status, ['wallet_approved', 'completed'], true))<br>{{ $refund->due_at?->format('Y-m-d H:i') }}@endif
                    @if($refund->status === 'wallet_approved' && $refund->due_at?->isPast())<strong class="text-danger">متأخر</strong>@endif
                </td>
                <td>{{ ['customer_requested' => 'بانتظار قرار الإدارة', 'rejected' => 'مرفوض — يستمر الطلب', 'wallet_approved' => 'معتمد وبانتظار الإضافة للمحفظة', 'completed' => 'تم تسجيل الرد'][$refund->status] ?? $refund->status }}<br>{{ $refund->admin_note }}</td>
                <td>
                    @if($refund->status === 'customer_requested')
                        <form method="post" action="{{ route('admin.orders.package-refunds.approve', $refund->id) }}">
                            @csrf
                            <p>مهلة الرد بعد الاعتماد: {{ $refundDays }} يومًا.</p>
                            <button class="btn btn-primary mt-2" type="submit">اعتماد الإلغاء والرد إلى المحفظة</button>
                        </form>
                        @if($refund->order?->order_status !== 'canceled')
                            <form method="post" action="{{ route('admin.orders.package-refunds.reject', $refund->id) }}" class="mt-2">
                                @csrf
                                <label>سبب الرفض<input class="form-control" name="reason" maxlength="2000" required></label>
                                <button class="btn btn-outline-danger mt-2" type="submit">رفض طلب الإلغاء</button>
                            </form>
                        @endif
                    @elseif($refund->status === 'wallet_approved')
                        <form method="post" action="{{ route('admin.orders.package-refunds.complete', $refund->id) }}">
                            @csrf
                            <input type="hidden" name="method" value="purchase_wallet">
                            <label>ملاحظة<input class="form-control" name="note" maxlength="2000"></label>
                            <button class="btn btn-primary mt-2" type="submit">إضافة المبلغ إلى المحفظة</button>
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
