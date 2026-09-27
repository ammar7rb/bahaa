@foreach($offlinePaymentMethods as $method)
    <details class="border rounded p-3 mt-3">
        <summary class="text-primary cursor-pointer font-weight-bold">{{ translate('offline_deposit_via') }} {{ translatePaymentText($method->method_name) }}</summary>
        <div class="bg-light rounded p-2 my-3">
            @foreach($method->method_fields ?? [] as $field)
                <div><strong>{{ translatePaymentText($field['input_name'] ?? '') }}:</strong> {{ translatePaymentText($field['input_data'] ?? '') }}</div>
            @endforeach
        </div>
        <form method="post" action="{{ route('customer-balances.offline') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="wallet_type" value="{{ $walletType }}">
            <input type="hidden" name="method_id" value="{{ $method->id }}">
            <input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
            <label class="d-block">{{ translate('amount') }} ({{ session('currency_symbol') }})
                <input type="number" class="form-control" name="amount" min="0.01" step="0.01" required>
            </label>
            @foreach($method->method_informations ?? [] as $field)
                @continue(empty($field['customer_input']) || $field['customer_input'] === 'payment_screenshot')
                <label class="d-block">{{ translatePaymentText($field['customer_placeholder'] ?? $field['customer_input']) }}
                    <input class="form-control" name="method_information[{{ $field['customer_input'] ?? '' }}]" maxlength="255" {{ ($field['is_required'] ?? false) ? 'required' : '' }}>
                </label>
            @endforeach
            <label class="d-block">{{ translate('deposit_transfer_reference') }}
                <input class="form-control" name="payment_reference" maxlength="191" required>
            </label>
            <label class="d-block">{{ translate('payment_proof') }} (JPG / PNG / WebP، 5 MB)
                <input class="form-control" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required>
            </label>
            <label class="d-block">{{ translate('note') }}
                <textarea class="form-control" name="payment_note" maxlength="1000" rows="2"></textarea>
            </label>
            <p class="small text-muted">{{ translate('offline_deposit_review_notice') }}</p>
            <button class="btn btn--primary" type="submit">{{ translate('submit_deposit_for_review') }}</button>
        </form>
    </details>
@endforeach
