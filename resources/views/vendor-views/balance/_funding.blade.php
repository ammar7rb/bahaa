    <div class="row g-3 mb-4">
        @if($offlinePaymentAvailable)
            <div class="col-12"><div class="card card-body h-100">
                <h3>{{ translate('choose_deposit_method') }}</h3>
                <p class="text-muted fs-12">{{ translate('offline_deposit_requires_admin_review') }}</p>
                @forelse($offlinePaymentMethods as $method)
                    <details name="funding-method" class="vendor-transfer-choice" @if((string) old('method_id') === (string) $method->id) open @endif><summary>{{ translate($method->payment_channel === 'instapay' ? 'instapay_payment' : 'electronic_wallet_payment') }}</summary><div class="p-3">
                        <div class="fw-semibold mb-2">{{ translate($method->payment_channel === 'instapay' ? 'instapay_payment' : 'electronic_wallet_payment') }}</div>
                        @if(!empty($method->method_fields))
                            <div class="bg-light rounded p-2 mb-2 fs-12">
                                @foreach($method->method_fields as $field)
                                    <div><strong>{{ translatePaymentText($field['input_name'] ?? '') }}:</strong> {{ translatePaymentText($field['input_data'] ?? '') }}</div>
                                @endforeach
                            </div>
                        @endif
                        <form method="post" action="{{ route('vendor.balance.offline-payment') }}" enctype="multipart/form-data">
                            @csrf
                    <input type="hidden" name="wallet_target" value="{{ $walletSection }}">
                            <input type="hidden" name="method_id" value="{{ $method->id }}">
                            <div class="mb-2"><label class="form-label">{{ translate('amount') }}</label><input class="form-control" type="number" step="0.01" min="{{ usdToDefaultCurrency(amount:$fundingSettings['min_amount']) }}" max="{{ $fundingSettings['max_amount'] > 0 ? usdToDefaultCurrency(amount:$fundingSettings['max_amount']) : '' }}" name="amount" value="{{ (string) old('method_id') === (string) $method->id ? old('amount') : '' }}" required></div>
                            @foreach(($method->method_informations ?? []) as $field)
                                @php($inputName = $field['customer_input'] ?? '')
                                @continue(!$inputName || $inputName === 'payment_screenshot')
                                <div class="mb-2"><label class="form-label">{{ translatePaymentText($field['customer_placeholder'] ?? $inputName) }} @if($field['is_required'] ?? 0)<span class="text-danger">*</span>@endif</label><input class="form-control" type="text" name="method_information[{{ $inputName }}]" value="{{ (string) old('method_id') === (string) $method->id ? old('method_information.'.$inputName) : '' }}" placeholder="{{ translatePaymentText($field['customer_placeholder'] ?? '') }}" {{ ($field['is_required'] ?? 0) ? 'required' : '' }}></div>
                            @endforeach
                            <div class="mb-2"><label class="form-label">{{ translate('payment_proof') }} <span class="text-danger">*</span></label><input class="form-control" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required>@if((string) old('method_id') === (string) $method->id)<small>{{ translate('reselect_payment_proof') }}</small>@endif</div>
                            <div class="mb-2"><label class="form-label">{{ translate('payment_note') }}</label><textarea class="form-control" name="payment_note" maxlength="1000">{{ (string) old('method_id') === (string) $method->id ? old('payment_note') : '' }}</textarea></div>
                            <button class="btn btn--primary w-100">{{ translate('submit_for_review') }}</button>
                        </form>
                    </div></details>
                @empty
                    <div class="alert alert-soft-secondary mb-0">{{ translate('offline_payment_method_not_found') }}</div>
                @endforelse
            </div></div>
        @else<div class="col-12"><div class="alert alert-info">{{ translate('offline_payment_method_not_found') }}</div></div>
        @endif
    </div>

    <div class="card"><div class="card-header"><h5 class="mb-0">{{ translate('deposit_history') }}</h5></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>{{ translate('amount') }}</th><th>{{ translate('source') }}</th><th>{{ translate('status') }}</th><th>{{ translate('reference') }}</th><th>{{ translate('date') }}</th></tr></thead><tbody>
        @forelse($deposits as $deposit)
            <tr><td>{{ webCurrencyConverter(amount:$deposit->amount) }}</td><td>{{ translate($deposit->source) }}</td><td><span class="badge">{{ translate($deposit->status) }}</span>@if($deposit->rejection_reason)<br><small>{{ $deposit->rejection_reason }}</small>@endif</td><td>{{ $deposit->transaction_reference ?: $deposit->payment_request_id ?: '—' }}</td><td>{{ $deposit->created_at?->format('Y-m-d H:i') }}</td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted">{{ translate('no_seller_balance_deposits_found') }}</td></tr>
        @endforelse
    </tbody></table></div><div class="card-body d-flex justify-content-end">{{ $deposits->links() }}</div></div>
