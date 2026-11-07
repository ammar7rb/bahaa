<input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="operation" value="{{ $operation ?? 'purchase' }}">
<input type="hidden" name="expected_revision" value="{{ $subscription['limit_revision'] ?? 0 }}">
<input type="hidden" name="expected_subscription_id" value="{{ $subscription['active'] ? ($subscription['allowance']['subscription_id'] ?? '') : '' }}">
<input type="hidden" name="quote_token" value="{{ $quoteToken }}">
<input type="hidden" name="expected_amount" value="{{ number_format($quoteAmount,3,'.','') }}">
