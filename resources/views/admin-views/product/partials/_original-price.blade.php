@if($product->added_by === 'seller' && isset($original['unit_price']))
    {{ webCurrencyConverter($original['unit_price']) }}
    @if(!$submission || $submission->baseline_source === 'current_data_at_rollout')
        <small class="text-warning d-block">غير موثّق تاريخيًا</small>
    @endif
@elseif($product->added_by === 'seller')
    <span class="text-muted">غير موثّق</span>
@else
    —
@endif
