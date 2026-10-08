@extends('layouts.vendor.app')
@section('title',translate('finance_my_wallet'))
@section('content')
<div class="content container-fluid vendor-finance-page">
    <div class="vendor-page-intro"><div><h1>{{ translate('finance_my_wallet') }}</h1><p>{{ translate('wallet_purposes_hint') }}</p></div></div>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="vendor-balance-grid">
        @foreach(['available'=>'available_balance','operating'=>'payments_balance','order_insurance_credit'=>'security_deposit_balance'] as $key=>$label)
        <a class="vendor-balance-panel" href="{{ $key === 'available' ? '#available' : route($key === 'operating' ? 'vendor.balance.payments' : 'vendor.balance.insurance') }}"><span>{{ translate($label) }}</span><strong>{{ webCurrencyConverter(amount:$summary[$key]) }}</strong><small>{{ translate($key.'_purpose_hint') }}</small></a>
        @endforeach
    </div>
    <section class="card card-body mb-4" id="available"><h2>{{ translate('withdraw_request') }}</h2><p>{{ translate('withdraw_reserved_hint') }} {{ translate('pending_withdraw') }}: {{ webCurrencyConverter(amount:$summary['pending_withdraw']) }}</p>
        <form method="post" action="{{ route('vendor.balance.withdraw') }}" class="vendor-withdraw-form">@csrf<input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div><label for="saved_method_id">{{ translate('saved_withdrawal_method') }}</label><select class="form-control" name="saved_method_id" id="saved_method_id" required><option value="">{{ translate('select') }}</option>@foreach($withdrawalMethods as $method)<option value="{{ $method->id }}" @selected((string) old('saved_method_id') === (string) $method->id)>{{ $method->method_name }}</option>@endforeach</select></div>
            <div><label for="withdraw_amount">{{ translate('amount') }}</label><input class="form-control" id="withdraw_amount" value="{{ old('amount') }}" type="number" name="amount" min="0.01" max="{{ usdToDefaultCurrency(amount:$summary['available']) }}" step="0.01" required></div>
            <button class="btn btn--primary" @disabled($withdrawalMethods->isEmpty() || $summary['available'] <= 0)>{{ translate('submit_withdrawal') }}</button><a href="{{ route('vendor.account.withdrawal-methods') }}">{{ translate('manage_withdrawal_methods') }}</a>
        </form>
        <div class="table-responsive mt-4"><table class="table"><thead><tr><th>{{ translate('reference') }}</th><th>{{ translate('amount') }}</th><th>{{ translate('saved_withdrawal_method') }}</th><th>{{ translate('status') }}</th><th>{{ translate('actions') }}</th></tr></thead><tbody>@forelse($withdrawals as $withdrawal)<tr><td>{{ $withdrawal->transaction_number }}</td><td>{{ webCurrencyConverter(amount:$withdrawal->amount) }}</td><td>{{ data_get($withdrawal->withdrawal_method_fields,'method_name','—') }}</td><td>{{ translate(['pending','approved','denied','cancelled'][$withdrawal->approved] ?? 'pending') }}</td><td>@if($withdrawal->approved === 0)<form method="post" action="{{ route('vendor.business-settings.withdraw.close',$withdrawal->id) }}">@csrf<button class="btn btn-outline-primary">{{ translate('cancel') }}</button></form>@endif</td></tr>@empty<tr><td colspan="5">{{ translate('no_data_found') }}</td></tr>@endforelse</tbody></table></div>{{ $withdrawals->links() }}
    </section>
    <section class="card card-body mb-4"><h2>{{ translate('wallet_transactions') }}</h2><div class="table-responsive"><table class="table"><thead><tr><th>{{ translate('reference') }}</th><th>{{ translate('balance') }}</th><th>{{ translate('transaction_type') }}</th><th>{{ translate('amount') }}</th><th>{{ translate('date') }}</th></tr></thead><tbody>@forelse($transactions as $entry)<tr><td>{{ $entry->transaction_number }}</td><td>{{ translate(['available'=>'available_balance','operating'=>'payments_balance','order_insurance_credit'=>'security_deposit_balance'][$entry->bucket]) }}</td><td>{{ translate('wallet_'.$entry->direction) }}</td><td>{{ webCurrencyConverter(amount:$entry->amount) }}</td><td>{{ $entry->created_at->format('Y-m-d H:i') }}</td></tr>@empty<tr><td colspan="5">{{ translate('no_data_found') }}</td></tr>@endforelse</tbody></table></div>{{ $transactions->links() }}</section>
</div>
@endsection
