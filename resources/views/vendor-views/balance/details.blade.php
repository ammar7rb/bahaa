@extends('layouts.vendor.app')
@section('title',translate($walletSection === 'insurance' ? 'security_deposit_balance' : 'payments_balance'))
@section('content')
<div class="content container-fluid vendor-finance-page">
@include('vendor-views.balance._debt')
<div class="vendor-page-intro"><div><h1>{{ translate($walletSection === 'insurance' ? 'security_deposit_balance' : 'payments_balance') }}</h1><p>{{ translate($walletSection === 'insurance' ? 'order_insurance_credit_purpose_hint' : 'operating_purpose_hint') }}</p></div><a class="btn btn-outline-primary" href="{{ route('vendor.balance.index') }}">{{ translate('finance_my_wallet') }}</a></div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="vendor-finance-summary">
<div>{{ translate($walletSection === 'insurance' ? 'security_deposit_balance' : 'payments_balance') }}<strong>{{ webCurrencyConverter(amount:$summary[$walletSection === 'insurance' ? 'order_insurance_credit' : 'operating']) }}</strong></div>
@if($walletSection === 'insurance')<div>{{ translate('held_security_total') }}<strong>{{ webCurrencyConverter(amount:$securityDeposits['summary']['held_total']) }}</strong></div><div>{{ translate('total_security_paid') }}<strong>{{ webCurrencyConverter(amount:$securityDeposits['summary']['total_paid']) }}</strong></div>@endif
</div>
<section class="vendor-funding-section"><h2>{{ translate('fund_wallet') }}</h2><p>{{ translate('funding_verification_hint') }}</p>@include('vendor-views.balance._funding')</section>
@include('vendor-views.balance._transactions')
@if($walletSection === 'operating')
    <section class="card card-body mb-4" dir="rtl">
        <h2>استرداد رصيد التشغيل</h2>
        <p>يُحجز المبلغ من رصيد التشغيل عند إرسال الطلب. تبدأ مهلة الصرف من اعتماد الإدارة، وتتواصل الإدارة معك لتنسيق وسيلة الصرف.</p>
        <form method="post" action="{{ route('vendor.balance.operating-refund') }}">
            @csrf
            <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <input type="hidden" name="currency_code" value="{{ session('currency_code') ?: getCurrencyCode(type: 'default') }}">
            <label for="operating-refund-amount">مبلغ الاسترداد *</label>
            <input class="form-control mb-3" id="operating-refund-amount" name="amount" type="number" min="0.01" max="{{ usdToDefaultCurrency($summary['operating']) }}" step="0.01" required>
            <button class="btn btn--primary" @disabled($summary['operating'] <= 0)>طلب استرداد</button>
        </form>
        <div class="table-responsive mt-3"><table class="table">
            <thead><tr><th>المرجع</th><th>المبلغ</th><th>الحالة</th><th>موعد الصرف</th></tr></thead>
            <tbody>@foreach($withdrawals as $row)@if($row->source_bucket === 'operating')<tr>
                <td>{{ $row->transaction_number }}</td><td>{{ webCurrencyConverter($row->amount) }}</td>
                <td>{{ $row->approved === 0 ? ($row->reviewed_at ? 'معتمد بانتظار الصرف' : 'قيد المراجعة') : ($row->approved === 1 ? 'تم الصرف' : 'مرفوض أو ملغي') }}</td><td>{{ $row->payout_due_at }}</td>
            </tr>@endif
            @endforeach</tbody>
        </table></div>{{ $withdrawals->links() }}
    </section>
@endif
@if($walletSection === 'insurance')
    <section class="card card-body mb-4" id="security-history"><h2>{{ translate('paid_security_deposits') }}</h2><p>{{ translate('security_returns_hint') }}</p><div class="vendor-finance-summary"><div>{{ translate('total_security_paid') }}<strong>{{ webCurrencyConverter(amount:$securityDeposits['summary']['total_paid']) }}</strong></div><div>{{ translate('nearest_return_date') }}<strong>{{ $securityDeposits['summary']['next_return_at'] ? \Carbon\Carbon::parse($securityDeposits['summary']['next_return_at'])->format('Y-m-d') : '—' }}</strong></div><div>{{ translate('expected_return_amount') }}<strong>{{ webCurrencyConverter(amount:$securityDeposits['summary']['next_return_amount']) }}</strong></div></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>{{ translate('payment_reference') }}</th><th>{{ translate('paid_amount') }}</th><th>{{ translate('invoice') }}</th><th>{{ translate('nearest_return_date') }}</th><th>{{ translate('status') }}</th></tr></thead><tbody>@forelse($securityDeposits['records'] as $item)<tr><td>{{ $item['reference'] }}</td><td>{{ webCurrencyConverter(amount:$item['amount']) }}</td><td>@if($item['invoice_url'])<a href="{{ $item['invoice_url'] }}">{{ translate('view_invoice') }} #{{ $item['invoice_number'] }}</a>@else — @endif</td><td>{{ $item['return_at'] ? \Carbon\Carbon::parse($item['return_at'])->format('Y-m-d') : '—' }}</td><td>{{ translate($item['status']) }}</td></tr>@empty<tr><td colspan="5">{{ translate('no_data_found') }}</td></tr>@endforelse</tbody></table></div>{{ $securityDeposits['records']->links() }}
    </section>
@endif
</div>
@endsection
