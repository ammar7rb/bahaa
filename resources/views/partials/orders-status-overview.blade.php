<section class="orders-overview mb-3" aria-label="{{ translate('Current_Order_Summary') }}">
    @foreach(['pending'=>['pending','clock'], 'confirmed'=>['confirmed','check'], 'processing'=>['packaging','box'], 'out_for_delivery'=>['Out_for_Delivery','truck-side'], 'delivered'=>['Delivered','check-circle'], 'canceled'=>['Canceled','cross-circle'], 'returned'=>['returned','undo'], 'failed'=>['Failed_to_Deliver','exclamation']] as $orderState => [$stateLabel, $stateIcon])
    <a class="orders-metric" href="{{ route($ordersRole.'.orders.list', array_merge(['status' => $orderState], request()->except(['page', 'status', 'order_current_status']))) }}">
        <i class="fi fi-rr-{{ $stateIcon }}" aria-hidden="true"></i>
        <span>{{ translate($stateLabel) }}</span><strong>{{ $allOrdersInfo[$orderState.'_order'] ?? 0 }}</strong>
    </a>
    @endforeach
</section>
