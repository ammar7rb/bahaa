@if(in_array($order->order_status, ['pending', 'confirmed', 'processing', 'out_for_delivery'], true))
    @if($order->customer_cancel_request_status === 'pending')
        <p role="status">طلب الإلغاء قيد مراجعة الإدارة.</p>
    @elseif($order->customer_cancel_request_status === 'rejected')
        <p>تم رفض طلب الإلغاء. يجري استكمال الطلب.</p>
    @elseif(!($state['can_cancel_for_package'] ?? false))
        <form method="post" action="{{ route('customer.order.cancellation', $order->id) }}">
            @csrf
            <button class="btn btn-outline-danger" type="submit">طلب إلغاء الطلب</button>
        </form>
    @endif
@endif
