@extends('layouts.admin.app')
@section('title', 'طلبات الزوار')
@section('content')
<div class="content container-fluid" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4"><h1 class="page-header-title">طلبات الزوار</h1><a href="{{ route('admin.orders.center') }}">مركز الطلبات</a></div>
    <p class="text-muted">الطلبات الجديدة تتطلب استكمال الاسم والهاتف وإنشاء الحساب قبل الدفع. هذه القائمة تعرض طلبات الزوار القديمة للمراجعة.</p>
    <form method="get" class="card card-body mb-3"><div class="d-flex gap-2"><input name="q" value="{{ request('q') }}" class="form-control" maxlength="100" placeholder="رقم الطلب أو بيانات الاتصال"><button class="btn btn-primary">بحث</button></div></form>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>الطلب</th><th>الاسم والهاتف</th><th>المنتجات</th><th>الدفع</th><th>التاريخ</th><th>الإجراء</th></tr></thead><tbody>
    @forelse($orders as $order)
        @php($address = is_array($order->shipping_address_data) ? $order->shipping_address_data : (json_decode((string) $order->getRawOriginal('shipping_address_data'), true) ?: []))
        <tr><td>#{{ $order->id }}</td><td>{{ $address['contact_person_name'] ?? '—' }}<div class="small" dir="ltr">{{ $address['phone'] ?? '—' }}</div></td><td>{{ $order->details->count() }}</td><td>{{ $order->payment_status }}</td><td>{{ $order->created_at?->format('Y-m-d H:i') }}</td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.orders.details', $order->id) }}">فتح الطلب</a></td></tr>
    @empty<tr><td colspan="6" class="text-center p-5">لا توجد طلبات زوار.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $orders->links() }}</div></div>
</div>
@endsection
