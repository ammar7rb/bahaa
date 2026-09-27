@extends('layouts.vendor.app')

@section('title', translate('seller_balance'))

@section('content')
<div class="content container-fluid">
    <div class="mb-4">
        <h2 class="h1 mb-1">{{ translate('seller_balance') }}</h2>
        <p class="text-muted">{{ translate('seller_balance_deposits_note') }}</p>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            'sales_total' => 'sales_total', 'pending' => 'pending_balance', 'available' => 'available_balance',
            'operating' => 'operating_balance', 'pending_withdraw' => 'pending_withdrawn', 'withdrawn' => 'withdrawn',
        ] as $bucket => $label)
            <div class="col-sm-6 col-lg-4 col-xl-2"><div class="card card-body h-100"><small class="text-muted">{{ translate($label) }}</small><h3 class="mb-0">{{ number_format($summary[$bucket] ?? 0, 2) }}</h3></div></div>
        @endforeach
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ translate('seller_financial_tabs') }}</h5></div>
        <div class="card-body border-bottom">
            <ul class="nav nav-pills gap-2" role="tablist">
                @foreach(['balance' => 'balance', 'insurance' => 'order_insurance', 'shipping' => 'shipping'] as $tab => $label)
                    <li class="nav-item"><button class="nav-link @if($loop->first) active @endif" data-bs-toggle="pill" data-bs-target="#finance-{{ $tab }}" type="button">{{ translate($label) }} ({{ count($financeOverview['tabs'][$tab] ?? []) }})</button></li>
                @endforeach
            </ul>
        </div>
        <div class="tab-content">
            @foreach(['balance','insurance','shipping'] as $tab)
                <div class="tab-pane fade @if($loop->first) show active @endif" id="finance-{{ $tab }}">
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>{{ translate('date') }}</th><th>{{ translate('event_type') }}</th><th>{{ translate('reference') }}</th><th>{{ translate('order') }}/{{ translate('shipping') }}</th><th>{{ translate('due_date') }}</th><th>{{ translate('amount') }}</th></tr></thead><tbody>
                    @forelse($financeOverview['tabs'][$tab] ?? [] as $record)
                        <tr><td>{{ $record['created_at'] ? \Carbon\Carbon::parse($record['created_at'])->format('Y-m-d H:i') : '—' }}</td><td>{{ translate(str_replace('_', ' ', $record['event_type'])) }}</td><td><code>{{ $record['reference_code'] }}</code></td><td>{{ $record['order_id'] ? '#'.$record['order_id'] : '—' }} @if($record['shipment_reference'])<br><small>{{ $record['shipment_reference'] }}</small>@endif</td><td>{{ $record['due_at'] ? \Carbon\Carbon::parse($record['due_at'])->format('Y-m-d H:i') : '—' }}</td><td class="{{ $record['direction'] === 'debit' ? 'text-danger' : 'text-success' }}">{{ $record['direction'] === 'debit' ? '-' : '+' }}{{ number_format($record['amount'], 2) }}</td></tr>
                    @empty <tr><td colspan="6" class="text-center text-muted py-4">{{ translate('no_data_found') }}</td></tr>@endforelse
                    </tbody></table></div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="alert alert-info mb-4">
        <strong>{{ translate('deposit_limits') }}:</strong>
        {{ number_format($fundingSettings['min_amount'], 2) }} —
        {{ $fundingSettings['max_amount'] > 0 ? number_format($fundingSettings['max_amount'], 2) : translate('zero_means_unlimited') }}
    </div>

    @if($fundingSettings['company_accounts'])
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ translate('company_receiving_accounts') }}</h5></div>
            <div class="card-body"><div class="row g-3">
                @foreach($fundingSettings['company_accounts'] as $account)
                    <div class="col-md-6"><div class="border rounded p-3">
                        <div class="fw-semibold">{{ $account['bank_name'] ?? '' }}</div>
                        <div>{{ $account['account_name'] ?? '' }}</div>
                        <div>{{ $account['account_number'] ?? '' }}</div>
                        <div>{{ $account['iban'] ?? '' }}</div>
                        @if(!empty($account['notes']))<small class="text-muted">{{ $account['notes'] }}</small>@endif
                    </div></div>
                @endforeach
            </div></div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        @if($digitalPaymentAvailable)
            <div class="col-lg-6"><div class="card card-body h-100">
                <h5>{{ translate('digital_deposit') }}</h5>
                <form method="post" action="{{ route('vendor.balance.pay') }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">{{ translate('amount') }}</label><input class="form-control" type="number" step="0.01" min="{{ $fundingSettings['min_amount'] }}" max="{{ $fundingSettings['max_amount'] > 0 ? $fundingSettings['max_amount'] : '' }}" name="amount" required></div>
                    <div class="mb-2"><label class="form-label">{{ translate('payment_method') }}</label><select class="form-select" name="payment_method" required>@foreach($paymentGatewayList as $gateway)<option value="{{ $gateway->key_name }}">{{ data_get(json_decode($gateway->additional_data ?: '{}', true), 'gateway_title') ?: ucwords(str_replace('_', ' ', $gateway->key_name)) }}</option>@endforeach</select></div>
                    <button class="btn btn--primary">{{ translate('continue_to_payment') }}</button>
                </form>
            </div></div>
        @endif

        @if($offlinePaymentAvailable)
            <div class="col-lg-6"><div class="card card-body h-100">
                <h5>{{ translate('offline_deposit') }}</h5>
                <p class="text-muted fs-12">{{ translate('offline_deposit_requires_admin_review') }}</p>
                @forelse($offlinePaymentMethods as $method)
                    <div class="border rounded p-3 mb-3">
                        <div class="fw-semibold mb-2">{{ translatePaymentText($method->method_name) }}</div>
                        @if(!empty($method->method_fields))
                            <div class="bg-light rounded p-2 mb-2 fs-12">
                                @foreach($method->method_fields as $field)
                                    <div><strong>{{ translatePaymentText($field['input_name'] ?? '') }}:</strong> {{ translatePaymentText($field['input_data'] ?? '') }}</div>
                                @endforeach
                            </div>
                        @endif
                        <form method="post" action="{{ route('vendor.balance.offline-payment') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="method_id" value="{{ $method->id }}">
                            <div class="mb-2"><label class="form-label">{{ translate('amount') }}</label><input class="form-control" type="number" step="0.01" min="{{ $fundingSettings['min_amount'] }}" max="{{ $fundingSettings['max_amount'] > 0 ? $fundingSettings['max_amount'] : '' }}" name="amount" required></div>
                            @foreach(($method->method_informations ?? []) as $field)
                                @php($inputName = $field['customer_input'] ?? '')
                                @continue(!$inputName || $inputName === 'payment_screenshot')
                                <div class="mb-2"><label class="form-label">{{ translatePaymentText($field['customer_placeholder'] ?? $inputName) }} @if($field['is_required'] ?? 0)<span class="text-danger">*</span>@endif</label><input class="form-control" type="text" name="method_information[{{ $inputName }}]" placeholder="{{ translatePaymentText($field['customer_placeholder'] ?? '') }}" {{ ($field['is_required'] ?? 0) ? 'required' : '' }}></div>
                            @endforeach
                            <div class="mb-2"><label class="form-label">{{ translate('payment_proof') }} <span class="text-danger">*</span></label><input class="form-control" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required></div>
                            <div class="mb-2"><label class="form-label">{{ translate('payment_note') }}</label><textarea class="form-control" name="payment_note" maxlength="1000"></textarea></div>
                            <button class="btn btn--primary w-100">{{ translate('submit_for_review') }}</button>
                        </form>
                    </div>
                @empty
                    <div class="alert alert-soft-secondary mb-0">{{ translate('offline_payment_method_not_found') }}</div>
                @endforelse
            </div></div>
        @endif
    </div>

    <div class="card"><div class="card-header"><h5 class="mb-0">{{ translate('deposit_history') }}</h5></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>{{ translate('amount') }}</th><th>{{ translate('source') }}</th><th>{{ translate('status') }}</th><th>{{ translate('reference') }}</th><th>{{ translate('date') }}</th></tr></thead><tbody>
        @forelse($deposits as $deposit)
            <tr><td>{{ $deposit->amount }} {{ $deposit->currency_code }}</td><td>{{ translate($deposit->source) }}</td><td><span class="badge">{{ translate($deposit->status) }}</span>@if($deposit->rejection_reason)<br><small>{{ $deposit->rejection_reason }}</small>@endif</td><td>{{ $deposit->transaction_reference ?: $deposit->payment_request_id ?: '—' }}</td><td>{{ $deposit->created_at?->format('Y-m-d H:i') }}</td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted">{{ translate('no_seller_balance_deposits_found') }}</td></tr>
        @endforelse
    </tbody></table></div><div class="card-body d-flex justify-content-end">{{ $deposits->links() }}</div></div>
</div>
@endsection
