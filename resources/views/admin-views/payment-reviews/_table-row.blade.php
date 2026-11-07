@php
    $kindIcon = ['order'=>'shopping-cart','subscription'=>'user','package'=>'box-open','balance_deposit'=>'wallet'][$review['kind']] ?? 'document';
    $statusIcon = ['pending'=>'clock','approved'=>'check-circle','rejected'=>'cross-circle','reconciliation'=>'info','reversed'=>'rotate-left'][$review['status']] ?? 'info';
    $statusTone = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$review['status']] ?? 'info';
    $reference = trim((string)($review['reference'] ?? ''));
    $internalReference = preg_match('/^(receipt-sha256:|deposit-transfer-|subscription-transfer-|customer-package-activation-|order-transfer-|ORD-|DEP-|offline-review-|unified-review-)/i', $reference);
    $purpose = explode(' · ', (string)$review['label'])[0];
    $methodIcon = preg_match('/insta|إنستا|انستا/iu', $review['method']) ? 'exchange' : (preg_match('/wallet|محفظ/iu', $review['method']) ? 'wallet' : 'credit-card');
    $date = \Carbon\Carbon::parse($review['created_at']);
@endphp
<tr>
    <td class="pr-cell-request" data-label="{{ __('payments.number') }}"><strong class="pr-request-number"><bdi>{{ $review['request_number'] }}</bdi></strong>@if($reference !== '' && !$internalReference && $reference !== $review['request_number'])<small class="pr-reference" title="{{ $reference }}"><bdi>{{ \Illuminate\Support\Str::limit($reference,22) }}</bdi></small>@endif</td>
    <td class="pr-cell-account" data-label="{{ __('payments.account') }}"><strong class="pr-account-name">{{ trim(($review['actor']?->f_name ?? '').' '.($review['actor']?->l_name ?? '')) ?: '—' }}</strong><small class="pr-account-meta"><i class="fi fi-rr-{{ $review['audience'] === 'vendor' ? 'shop' : 'user' }}" aria-hidden="true"></i> {{ __('payments.'.$review['audience']) }} <bdi>{{ $review['audience'] === 'vendor' ? 'M' : 'C' }}{{ $review['actor']?->id }}</bdi></small><small class="pr-phone"><bdi>{{ $review['actor']?->phone }}</bdi></small></td>
    <td class="pr-cell-purpose" data-label="{{ __('payments.request_type') }}"><span class="pr-type"><i class="fi fi-rr-{{ $kindIcon }}" aria-hidden="true"></i><span>{{ __('payments.type_'.$review['type']) }}</span></span><small class="pr-purpose">{{ $purpose }}</small>@if(isset($review['customer_state'])) @include('admin-views.order.partials._checkout-origin', ['checkoutState' => $review['customer_state']]) @endif</td>
    <td class="pr-money" data-label="{{ __('payments.amount') }}"><bdi>{{ in_array($review['type'], ['customer_subscription','customer_legacy_receipt','vendor_extra_ad'], true) ? number_format($review['amount'],2).' '.$review['currency'] : setCurrencySymbol(amount: usdToDefaultCurrency(amount: $review['amount'])) }}</bdi></td>
    <td class="pr-cell-method" data-label="{{ __('payments.method') }}"><span class="pr-method"><i class="fi fi-rr-{{ $methodIcon }}" aria-hidden="true"></i><span>{{ translatePaymentText($review['method']) }}</span></span></td>
    <td class="pr-cell-date" data-label="{{ __('payments.date') }}"><bdi>{{ $date->format('Y-m-d') }}</bdi><small><bdi>{{ $date->format('H:i') }}</bdi></small></td>
    <td class="pr-cell-status" data-label="{{ __('payments.status') }}"><span class="badge badge-soft-{{ $statusTone }}"><i class="fi fi-rr-{{ $statusIcon }}" aria-hidden="true"></i><span>{{ __('payments.'.$review['status']) }}</span></span></td>
    <td class="pr-cell-action" data-label="{{ __('payments.actions') }}"><button type="button" class="btn btn-outline-primary pr-view-button" data-pr-open="pr-{{ $review['type'] }}-{{ $review['id'] }}" aria-label="{{ __('payments.view_request') }} {{ $review['request_number'] }}" title="{{ __('payments.view_request') }}"><i class="fi fi-rr-eye" aria-hidden="true"></i><span class="pr-view-label">{{ __('payments.view_request') }}</span></button></td>
</tr>
