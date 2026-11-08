@extends('layouts.admin.app')
@section('title', 'مديونيات إلغاء التجار')
@section('content')
<div class="content container-fluid" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4"><h1 class="page-header-title">مديونيات إلغاء التجار</h1><a href="{{ route('admin.orders.cancellations') }}">معالجة الإلغاء</a></div>
    <form method="get" class="card card-body mb-3"><div class="d-flex gap-2"><input name="seller_id" type="number" min="1" value="{{ request('seller_id') }}" class="form-control" placeholder="رقم التاجر"><button class="btn btn-primary">بحث</button></div></form>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>التاجر</th><th>الطلب والحصة</th><th>رسوم الإلغاء</th><th>خُصم من المتاح</th><th>المديونية</th><th>السبب والتاريخ</th></tr></thead><tbody>
    @forelse($debts as $debt)
        <tr><td>{{ $sellers->get($debt->seller_id)?->shop?->name ?? '#'.$debt->seller_id }}</td><td><a href="{{ route('admin.orders.details', $debt->order_id) }}">#{{ $debt->order_id }}</a> · #{{ $debt->order_work_item_id }}</td><td>{{ webCurrencyConverter($debt->fee_amount) }}</td><td>{{ webCurrencyConverter($debt->seller_debited_amount) }}</td><td><strong>{{ webCurrencyConverter($debt->seller_debt_amount) }}</strong></td><td>{{ $debt->reason }}<div class="small text-muted">{{ $debt->created_at }}</div></td></tr>
    @empty<tr><td colspan="6" class="text-center p-5">لا توجد مديونيات إلغاء مسجلة.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $debts->links() }}</div></div>
</div>
@endsection
