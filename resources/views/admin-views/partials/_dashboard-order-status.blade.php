@php
    $analyticsSummary = [
        ['label' => 'total_Order', 'value' => $data['order'], 'live' => 'order', 'route' => route('admin.orders.list', ['all']), 'icon' => 'fi fi-sr-shopping-cart', 'color' => '#3b82f6'],
        ['label' => 'total_Stores', 'value' => $data['store'], 'live' => 'store', 'route' => route('admin.vendors.vendor-list'), 'icon' => 'fi fi-sr-shop', 'color' => '#8b5cf6'],
        ['label' => 'Total_Products', 'value' => $data['product'], 'live' => 'product', 'route' => route('admin.products.list', ['type' => 'all']), 'icon' => 'fi fi-sr-box-open', 'color' => '#f59e0b'],
        ['label' => 'Total_Customers', 'value' => $data['customer'], 'live' => 'customer', 'route' => route('admin.customer.list'), 'icon' => 'fi fi-sr-users', 'color' => '#10b981'],
    ];

    $orderStatuses = [
        ['label' => 'Pending', 'value' => $data['pending'], 'live' => 'pending', 'status' => 'pending', 'icon' => 'fi fi-sr-time-past', 'color' => '#f59e0b'],
        ['label' => 'Confirmed', 'value' => $data['confirmed'], 'live' => 'confirmed', 'status' => 'confirmed', 'icon' => 'fi fi-sr-badge-check', 'color' => '#22c55e'],
        ['label' => 'Packaging', 'value' => $data['processing'], 'live' => 'processing', 'status' => 'processing', 'icon' => 'fi fi-sr-package', 'color' => '#8b5cf6'],
        ['label' => 'Out_For_Delivery', 'value' => $data['out_for_delivery'], 'live' => 'out_for_delivery', 'status' => 'out_for_delivery', 'icon' => 'fi fi-sr-truck-moving', 'color' => '#06b6d4'],
        ['label' => 'Delivered', 'value' => $data['delivered'], 'live' => 'delivered', 'status' => 'delivered', 'icon' => 'fi fi-sr-check-circle', 'color' => '#10b981'],
        ['label' => 'Canceled', 'value' => $data['canceled'], 'live' => 'canceled', 'status' => 'canceled', 'icon' => 'fi fi-sr-cross-circle', 'color' => '#ef4444'],
        ['label' => 'Returned', 'value' => $data['returned'], 'live' => 'returned', 'status' => 'returned', 'icon' => 'fi fi-sr-undo', 'color' => '#f97316'],
        ['label' => 'Failed_To_Delivery', 'value' => $data['failed'], 'live' => 'failed', 'status' => 'failed', 'icon' => 'fi fi-sr-circle-xmark', 'color' => '#e11d48'],
    ];
@endphp

@foreach($analyticsSummary as $summary)
    <div class="col-12 col-sm-6 col-xl-3">
        <a class="dashboard-analytics-summary" href="{{ $summary['route'] }}"
           style="--analytics-accent: {{ $summary['color'] }}">
            <span class="dashboard-analytics-summary-icon" aria-hidden="true">
                <i class="{{ $summary['icon'] }}"></i>
            </span>
            <span class="dashboard-analytics-summary-content">
                <span class="dashboard-analytics-summary-label">{{ translate($summary['label']) }}</span>
                <strong class="dashboard-analytics-summary-value" data-live-metric="{{ $summary['live'] }}">
                    {{ number_format($summary['value']) }}
                </strong>
            </span>
            <i class="fi fi-rr-arrow-small-left dashboard-analytics-card-arrow" aria-hidden="true"></i>
        </a>
    </div>
@endforeach

@foreach($orderStatuses as $orderStatus)
    <div class="col-12 col-sm-6 col-xl-3">
        <a class="dashboard-order-status-card" href="{{ route('admin.orders.list', [$orderStatus['status']]) }}"
           style="--analytics-accent: {{ $orderStatus['color'] }}">
            <span class="dashboard-order-status-icon" aria-hidden="true">
                <i class="{{ $orderStatus['icon'] }}"></i>
            </span>
            <span class="dashboard-order-status-label">{{ translate($orderStatus['label']) }}</span>
            <strong class="dashboard-order-status-value" data-live-metric="{{ $orderStatus['live'] }}">
                {{ number_format($orderStatus['value']) }}
            </strong>
        </a>
    </div>
@endforeach
