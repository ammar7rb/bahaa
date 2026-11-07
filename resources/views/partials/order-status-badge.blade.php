@php
    $statusTone = match ($order->order_status) {
        'delivered' => 'success',
        'confirmed', 'processing', 'out_for_delivery', 'pending' => 'primary',
        'failed' => 'danger',
        default => 'secondary',
    };
    $statusLabel = match ($order->order_status) {
        'processing' => 'packaging',
        'failed' => 'failed_to_deliver',
        default => $order->order_status,
    };
@endphp
<span class="badge badge-soft-{{ $statusTone }}">{{ translate($statusLabel) }}</span>
