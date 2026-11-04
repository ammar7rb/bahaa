@extends('layouts.admin.app')
@section('title', 'صرف واسترداد أموال التجار')
@section('content')
<main class="content container-fluid" dir="rtl">
    <h1 class="h2">صرف واسترداد أموال التجار</h1>
    <a href="{{ route('admin.customer-withdrawals.index') }}">تعديل النسب والمدد</a>
    @if(session('success'))<p class="alert alert-success" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="alert alert-danger" role="alert">{{ $errors->first() }}</p>@endif
    <p>تبدأ مهلة الصرف من اعتماد الإدارة. سجل مرجع التحويل بعد صرف المبلغ فعليًا للتاجر.</p>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>المرجع والتاجر</th><th>النوع والمبلغ</th><th>الحالة والموعد</th><th>الإجراء</th></tr></thead>
        <tbody>@foreach($withdrawals as $row)<tr>
            <td>{{ $row->transaction_number }} · {{ \App\Services\AdministrativeReferenceService::format('seller', $row->seller_id) }}</td>
            <td>{{ $row->source_bucket === 'operating' ? 'استرداد رصيد التشغيل' : 'صرف حصيلة المبيعات' }}<br>{{ webCurrencyConverter($row->amount) }}</td>
            <td>{{ $row->approved === 0 ? ($row->reviewed_at ? 'معتمد بانتظار الصرف' : 'قيد المراجعة') : ($row->approved === 1 ? 'تم الصرف' : 'مرفوض أو ملغي') }}<br>{{ $row->payout_due_at }}<br>{{ $row->payout_reference }}</td>
            <td>@if($row->approved === 0)<form method="post" action="{{ route('admin.seller-payouts.review', $row->id) }}">
                @csrf
                <label>ملاحظة أو سبب الرفض<input class="form-control" name="note" maxlength="1000"></label>
                @if($row->reviewed_at)
                    <label>مرجع الصرف المنفذ<input class="form-control" name="reference" maxlength="191"></label>
                    <button class="btn btn-primary" name="decision" value="complete">تسجيل الصرف الفعلي</button>
                @else
                    <button class="btn btn-success" name="decision" value="approve">اعتماد وتحديد الموعد</button>
                @endif
                <button class="btn btn-outline-danger" name="decision" value="reject">رفض وإعادة الرصيد</button>
            </form>@endif</td>
        </tr>@endforeach</tbody>
    </table></div>{{ $withdrawals->links() }}
</main>
@endsection
