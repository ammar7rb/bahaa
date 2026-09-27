@extends('layouts.admin.app')
@section('title', 'طلبات إيداع رصيد التجار')
@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div><h1 class="h2 mb-1">طلبات إيداع رصيد التجار</h1><p class="text-muted mb-0">راجع إثبات التحويل قبل إضافة المبلغ إلى رصيد المشتريات أو التأمين.</p></div>
        <a class="btn btn-outline-primary" href="{{ route('admin.vendors.balance-funding-settings.index') }}">إعدادات الإيداع</a>
    </div>
    <div class="card"><div class="card-body">
        <form method="get" class="row g-2 mb-4">
            <div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="البحث بالبريد أو الهاتف أو المرجع"></div>
            <div class="col-md-3"><select class="form-select" name="status"><option value="">كل الحالات</option>@foreach(['pending','paid','rejected','reversed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ translate($status) }}</option>@endforeach</select></div>
            <div class="col-md-2"><select class="form-select" name="source"><option value="">كل طرق الدفع</option><option value="offline" @selected(request('source') === 'offline')>تحويل يدوي</option><option value="digital" @selected(request('source') === 'digital')>دفع إلكتروني</option></select></div>
            <div class="col-md-2"><select class="form-select" name="target"><option value="">كل الأرصدة</option><option value="operating" @selected(request('target') === 'operating')>المشتريات</option><option value="insurance" @selected(request('target') === 'insurance')>التأمين</option></select></div>
            <div class="col-md-1"><button class="btn btn-primary w-100">تصفية</button></div>
        </form>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>التاجر</th><th>الإيداع</th><th>طريقة الدفع والإثبات</th><th>الحالة</th><th>الإجراء</th></tr></thead>
            <tbody>
            @forelse($deposits as $deposit)
                @php
                    $target = data_get($deposit->metadata, 'wallet_target') === 'insurance' ? 'رصيد التأمين' : 'رصيد المشتريات';
                    $proofUrl = getOfflinePaymentProofUrl($deposit->payment_proof, ['seller-balance/payment-proof']);
                @endphp
                <tr>
                    <td><strong>{{ trim(($deposit->seller?->f_name ?? '').' '.($deposit->seller?->l_name ?? '')) }}</strong><br><bdi>{{ $deposit->seller?->email ?? '#'.$deposit->seller_id }}</bdi><br><small>{{ $deposit->seller?->phone }}</small></td>
                    <td><strong>{{ $deposit->amount }} {{ $deposit->currency_code }}</strong><br><span class="badge badge-soft-primary">{{ $target }}</span><br><small>{{ $deposit->created_at?->format('Y-m-d H:i') }}</small></td>
                    <td>
                        {{ $deposit->offlinePaymentMethod?->method_name ?? translate($deposit->source) }}
                        @foreach(($deposit->offline_information ?: []) as $key => $value)
                            @if(is_scalar($value) && trim((string) $value) !== '')<div class="small text-break">{{ translatePaymentText($key) }}: {{ $value }}</div>@endif
                        @endforeach
                        @if($proofUrl)<a href="{{ $proofUrl }}" target="_blank" rel="noopener">عرض إثبات التحويل</a>@endif
                        @if($deposit->payment_note)<div class="small text-muted">{{ $deposit->payment_note }}</div>@endif
                    </td>
                    <td><span class="badge {{ $deposit->status === 'paid' ? 'bg-success' : ($deposit->status === 'pending' ? 'bg-warning text-dark' : 'bg-danger') }}">{{ translate($deposit->status) }}</span><br><small>{{ $deposit->transaction_reference ?: $deposit->payment_request_id ?: 'DEP-'.$deposit->id }}</small>@if($deposit->rejection_reason)<div class="small text-danger">{{ $deposit->rejection_reason }}</div>@endif</td>
                    <td>
                        @if($deposit->status === 'pending' && $deposit->source === 'offline')
                            <div class="d-flex flex-wrap gap-2">
                                <form method="post" action="{{ route('admin.vendors.balance-deposits.approve', $deposit->id) }}">@csrf<input class="form-control form-control-sm mb-1" name="payment_reference" placeholder="مرجع التحويل (اختياري)"><button class="btn btn-sm btn-success">تأكيد الإيداع</button></form>
                                <form method="post" action="{{ route('admin.vendors.balance-deposits.reject', $deposit->id) }}">@csrf<input class="form-control form-control-sm mb-1" name="review_note" required placeholder="سبب الرفض"><button class="btn btn-sm btn-outline-danger">رفض</button></form>
                            </div>
                        @elseif($deposit->status === 'paid')
                            <form method="post" action="{{ route('admin.vendors.balance-deposits.reverse', $deposit->id) }}">@csrf<input class="form-control form-control-sm mb-1" name="reason" required placeholder="سبب عكس الإيداع"><button class="btn btn-sm btn-outline-danger">عكس الإيداع</button></form>
                        @else — @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">لا توجد طلبات إيداع.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="d-flex justify-content-end">{{ $deposits->links() }}</div>
    </div></div>
</div>
@endsection
