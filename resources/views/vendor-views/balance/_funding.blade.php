    <div class="row g-3 mb-4">
        @if($digitalPaymentAvailable)
            <div class="col-lg-6"><div class="card card-body h-100">
                <h5>{{ translate('digital_deposit') }}</h5>
                <form method="post" action="{{ route('vendor.balance.pay') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">{{ translate('funding_destination') }}</label><select class="form-control" name="wallet_target" required><option value="operating">{{ translate('payments_balance') }}</option><option value="insurance">{{ translate('security_deposit_balance') }}</option></select></div>
                    <div class="mb-2"><label class="form-label">{{ translate('amount') }}</label><input class="form-control" type="number" step="0.01" min="{{ usdToDefaultCurrency(amount:$fundingSettings['min_amount']) }}" max="{{ $fundingSettings['max_amount'] > 0 ? usdToDefaultCurrency(amount:$fundingSettings['max_amount']) : '' }}" name="amount" required></div>
                    <div class="mb-2"><label class="form-label">{{ translate('payment_method') }}</label><select class="form-control" name="payment_method" required>@foreach($paymentGatewayList as $gateway)<option value="{{ $gateway->key_name }}">{{ data_get(json_decode($gateway->additional_data ?: '{}', true), 'gateway_title') ?: ucwords(str_replace('_', ' ', $gateway->key_name)) }}</option>@endforeach</select></div>
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
                    <div class="mb-3"><label class="form-label">{{ translate('funding_destination') }}</label><select class="form-control" name="wallet_target" required><option value="operating">{{ translate('payments_balance') }}</option><option value="insurance">{{ translate('security_deposit_balance') }}</option></select></div>
                            <input type="hidden" name="method_id" value="{{ $method->id }}">
                            <div class="mb-2"><label class="form-label">{{ translate('amount') }}</label><input class="form-control" type="number" step="0.01" min="{{ usdToDefaultCurrency(amount:$fundingSettings['min_amount']) }}" max="{{ $fundingSettings['max_amount'] > 0 ? usdToDefaultCurrency(amount:$fundingSettings['max_amount']) : '' }}" name="amount" required></div>
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
