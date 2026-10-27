@php
    $checkoutAction = $purchaseState['next_action'] ?? null;
    $checkoutActionUrl = null;
    $checkoutActionLabel = null;
    if ($checkoutAction === 'activate_account') {
        $checkoutActivation = app(\App\Services\CustomerActivationService::class)->payload(auth('customer')->user());
        $checkoutActionUrl = !empty($checkoutActivation['ticket_id']) ? route('support-ticket.index', ['id' => $checkoutActivation['ticket_id']]) : route('account-tickets');
        $checkoutActionLabel = 'تواصل لتفعيل الحساب';
    } elseif ($checkoutAction === 'choose_package') {
        $checkoutActionUrl = route('customer.subscription.index');
        $checkoutActionLabel = 'اختيار باقة مناسبة';
    } elseif ($checkoutAction === 'contact_support') {
        $checkoutActionUrl = route('account-tickets');
        $checkoutActionLabel = 'التواصل مع الدعم';
    }
@endphp
@if($checkoutActionUrl)<a class="{{ $actionClass ?? 'btn btn-outline-primary btn-sm' }}" href="{{ $checkoutActionUrl }}">{{ $checkoutActionLabel }}</a>@endif
