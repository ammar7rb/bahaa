@extends('layouts.admin.app')
@section('title', 'نسب ومدد صرف الأموال')
@section('content')
<main class="content container-fluid" dir="rtl">
    <h1 class="h2">نسب ومدد صرف الأموال</h1>
    <p><a href="{{ route('admin.seller-payouts.index') }}">طلبات صرف واسترداد أموال التجار</a> · <a href="{{ route('admin.business-settings.vendor-settings.index') }}#seller-settlement-hold-days">مدة تسوية حصيلة مبيعات التجار وأيام العمل</a></p>
    @if(session('success'))<p role="status" class="alert alert-success">{{ session('success') }}</p>@endif
    @if($errors->any())<p role="alert" class="alert alert-danger">{{ $errors->first() }}</p>@endif
    @if(\App\Utils\Helpers::module_permission_check('business_settings'))
        <form class="card card-body mb-4" method="post" action="{{ route('admin.customer-withdrawals.settings') }}">
            @csrf
            <label for="withdrawal-percent">رسوم الإدارة والتشغيل %</label>
            <input class="form-control mb-3" id="withdrawal-percent" name="fee_percent" type="number" min="0" max="100" step="0.01" value="{{ $feePercent }}" required>
            <label for="withdrawal-days">مدة الصرف بالأيام من اعتماد الإدارة</label>
            <input class="form-control mb-3" id="withdrawal-days" name="days" type="number" min="15" max="30" value="{{ $withdrawalDays }}" required>
            <label for="seller-operating-days">مدة استرداد رصيد تشغيل التاجر من اعتماد الإدارة</label>
            <input class="form-control mb-3" id="seller-operating-days" name="seller_operating_days" type="number" min="15" max="30" value="{{ $sellerOperatingDays }}" required>
            <label for="seller-sales-days">مدة صرف حصيلة مبيعات التاجر من اعتماد طلب السحب</label>
            <input class="form-control mb-3" id="seller-sales-days" name="seller_sales_days" type="number" min="1" max="30" value="{{ $sellerSalesDays }}" required>
            <p>تُحفظ النسبة مع كل طلب جديد، والمدة عند الاعتماد.</p>
            <button class="btn btn-primary" type="submit">حفظ النسب والمدة</button>
        </form>
    @endif
    <div class="table-responsive"><table class="table">
        <thead><tr><th>الطلب والعميل</th><th>المبلغ والرسوم والصافي</th><th>الحالة والموعد</th><th>الإجراء</th></tr></thead>
        <tbody>@foreach($withdrawals as $row)<tr>
            <td>#{{ $row->id }} · {{ \App\Services\AdministrativeReferenceService::format('customer', $row->customer_id) }}</td>
            <td>{{ webCurrencyConverter($row->amount) }}<br>{{ $row->fee_percent }}% · {{ webCurrencyConverter($row->fee_amount) }}<br>الصافي {{ webCurrencyConverter($row->net_amount) }}</td>
            <td>{{ $row->status }}<br>{{ $row->due_at }}<br>{{ $row->reference }}</td>
            <td>@if(in_array($row->status, ['pending', 'approved'], true))
                <form method="post" action="{{ route('admin.customer-withdrawals.review', $row->id) }}">
                    @csrf
                    <label>ملاحظة أو سبب الرفض<input class="form-control" name="note" maxlength="1000"></label>
                    @if($row->status === 'pending')
                        <button class="btn btn-success" name="decision" value="approve">اعتماد وتحديد الموعد</button>
                    @else
                        <label>مرجع التحويل المنفذ<input class="form-control" name="reference" maxlength="191"></label>
                        <button class="btn btn-primary" name="decision" value="complete">تسجيل صرف الصافي</button>
                    @endif
                    <button class="btn btn-outline-danger" name="decision" value="reject">رفض وإعادة المبلغ للمحفظة</button>
                </form>
            @endif</td>
        </tr>@endforeach</tbody>
    </table></div>
    {{ $withdrawals->links() }}
</main>
@endsection
