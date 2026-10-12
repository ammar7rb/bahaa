@extends('layouts.vendor.app')
@section('title',translate($walletSection === 'insurance' ? 'security_deposit_balance' : 'payments_balance'))
@section('content')
<div class="content container-fluid vendor-finance-page">
<div class="vendor-page-intro"><div><h1>{{ translate($walletSection === 'insurance' ? 'security_deposit_balance' : 'payments_balance') }}</h1><p>{{ translate($walletSection === 'insurance' ? 'order_insurance_credit_purpose_hint' : 'operating_purpose_hint') }}</p></div><a class="btn btn-outline-primary" href="{{ route('vendor.balance.index') }}">{{ translate('finance_my_wallet') }}</a></div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="vendor-finance-summary">
<div>{{ translate($walletSection === 'insurance' ? 'security_deposit_balance' : 'payments_balance') }}<strong>{{ webCurrencyConverter(amount:$summary[$walletSection === 'insurance' ? 'order_insurance_credit' : 'operating']) }}</strong></div>
@if($walletSection === 'insurance')<div>{{ translate('held_security_total') }}<strong>{{ webCurrencyConverter(amount:$securityDeposits['summary']['held_total']) }}</strong></div><div>{{ translate('total_security_paid') }}<strong>{{ webCurrencyConverter(amount:$securityDeposits['summary']['total_paid']) }}</strong></div>@endif
</div>
<section class="vendor-funding-section"><h2>{{ translate('fund_wallet') }}</h2><p>{{ translate('funding_verification_hint') }}</p>@include('vendor-views.balance._funding')</section>
@include('vendor-views.balance._transactions')
@if($walletSection === 'insurance')
    <section class="card card-body mb-4" id="security-history"><h2>{{ translate('paid_security_deposits') }}</h2><p>{{ translate('security_returns_hint') }}</p><div class="vendor-finance-summary"><div>{{ translate('total_security_paid') }}<strong>{{ webCurrencyConverter(amount:$securityDeposits['summary']['total_paid']) }}</strong></div><div>{{ translate('nearest_return_date') }}<strong>{{ $securityDeposits['summary']['next_return_at'] ? \Carbon\Carbon::parse($securityDeposits['summary']['next_return_at'])->format('Y-m-d') : '—' }}</strong></div><div>{{ translate('expected_return_amount') }}<strong>{{ webCurrencyConverter(amount:$securityDeposits['summary']['next_return_amount']) }}</strong></div></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>{{ translate('payment_reference') }}</th><th>{{ translate('paid_amount') }}</th><th>{{ translate('invoice') }}</th><th>{{ translate('nearest_return_date') }}</th><th>{{ translate('status') }}</th></tr></thead><tbody>@forelse($securityDeposits['records'] as $item)<tr><td>{{ $item['reference'] }}</td><td>{{ webCurrencyConverter(amount:$item['amount']) }}</td><td>@if($item['invoice_url'])<a href="{{ $item['invoice_url'] }}">{{ translate('view_invoice') }} #{{ $item['invoice_number'] }}</a>@else — @endif</td><td>{{ $item['return_at'] ? \Carbon\Carbon::parse($item['return_at'])->format('Y-m-d') : '—' }}</td><td>{{ translate($item['status']) }}</td></tr>@empty<tr><td colspan="5">{{ translate('no_data_found') }}</td></tr>@endforelse</tbody></table></div>{{ $securityDeposits['records']->links() }}
    </section>
@endif
</div>
@endsection
