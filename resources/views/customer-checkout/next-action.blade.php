@php
    $checkoutAction = $purchaseState['next_action'] ?? null;
    $checkoutActionUrl = null;
    $checkoutActionLabel = null;
    if ($checkoutAction === 'choose_package') {
        $checkoutActionUrl = route('customer.subscription.index');
        $checkoutActionLabel = 'اختيار باقة مناسبة';
    } elseif ($checkoutAction === 'contact_support') {
        $checkoutActionUrl = route('account-tickets');
        $checkoutActionLabel = 'التواصل مع الدعم';
    }
@endphp
@if($checkoutActionUrl)<a class="{{ $actionClass ?? 'btn btn-outline-primary btn-sm' }}" href="{{ $checkoutActionUrl }}">{{ $checkoutActionLabel }}</a>@endif
