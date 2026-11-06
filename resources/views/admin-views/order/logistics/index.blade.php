@extends('layouts.admin.app')

@section('title', translate('logistics_center'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="h1 mb-1">{{ translate('logistics_center') }}</h2>
                <p class="text-muted mb-0">{{ translate('logistics_center_subtitle') }}</p>
            </div>
            <a class="btn btn-outline-primary" href="{{ route('admin.orders.list', ['all']) }}">{{ translate('all_Orders') }}</a>
        </div>

        <div class="row g-3 mb-3">
            @foreach(['pending_assignment' => 'warning', 'in_transit' => 'primary', 'delivered' => 'success', 'returns' => 'danger'] as $key => $color)
                <div class="col-sm-6 col-xl-3"><div class="card card-body border-start border-4 border-{{ $color }}"><small class="text-muted">{{ translate($key === 'returns' ? 'return_shipments' : $key) }}</small><h3 class="mb-0">{{ $summary[$key] }}</h3></div></div>
            @endforeach
            <div class="col-sm-6 col-xl-3"><div class="card card-body border-start border-4 border-danger"><small class="text-muted">{{ translate('seller_delay_alerts') }}</small><h3 class="mb-0">{{ $summary['seller_delay_alerts'] }}</h3></div></div>
        </div>

        <details class="card card-body mb-3">
            <summary class="fw-bold cursor-pointer">{{ translate('order_operations_settings') }}</summary>
            <form class="row g-3 align-items-end mt-1" method="post" action="{{ route('admin.orders.logistics.settings') }}">@csrf
                @foreach(['pending', 'confirmed', 'processing', 'out_for_delivery'] as $state)
                    <div class="col-md-3"><label class="form-label">{{ translate($state) }} — {{ translate('delay_minutes') }}</label><input class="form-control" type="number" min="0" max="10080" name="{{ $state }}_minutes" value="{{ $delaySettings[$state] }}" required></div>
                @endforeach
                <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="sound_enabled" value="1" @checked($soundEnabled)> <span class="form-check-label">{{ translate('seller_order_sound_alert') }}</span></label></div>
                <div class="col-md-3 d-grid"><button class="btn btn-primary" type="submit">{{ translate('save_changes') }}</button></div>
            </form>
        </details>

        <form class="card card-body mb-3" method="get">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5"><label class="form-label">{{ translate('search_shipping_or_order') }}</label><input class="form-control" name="q" value="{{ request('q') }}" placeholder="{{ translate('order_ID') }} / {{ translate('tracking_number') }} / {{ translate('phone') }}"></div>
                <div class="col-lg-3"><label class="form-label">{{ translate('shipping_status') }}</label><select class="form-select" name="shipping_status"><option value="">{{ translate('all') }}</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('shipping_status') === $status)>{{ translate($status) }}</option>@endforeach</select></div>
                <div class="col-lg-2"><label class="form-label">{{ translate('shipping_party') }}</label><select class="form-select" name="responsible_party"><option value="">{{ translate('all') }}</option>@foreach($responsibleParties as $party)<option value="{{ $party }}" @selected(request('responsible_party') === $party)>{{ translate($party) }}</option>@endforeach</select></div>
                <div class="col-lg-2 d-grid"><button class="btn btn-primary" type="submit">{{ translate('filter') }}</button></div>
            </div>
        </form>

        <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>{{ translate('order_ID') }}</th><th>{{ translate('customer') }}</th><th>{{ translate('shipping_status') }}</th><th>{{ translate('seller_queue') }}</th><th>{{ translate('shipping_party') }}</th><th>{{ translate('tracking_number') }}</th><th>{{ translate('shipping_financial_split') }}</th><th>{{ translate('return_logistics') }}</th><th></th></tr></thead>
            <tbody>@forelse($orders as $order)
                <tr>
                    <td><a class="fw-bold text-dark text-hover-primary" href="{{ route('admin.orders.details', $order->id) }}">#{{ $order->id }}</a><br><small>{{ $order->shipment_reference ?: '-' }}</small><br><small>{{ $order->delivery_service_name ?: '-' }}</small></td>
                    <td>{{ trim(($order->customer?->f_name ?? '').' '.($order->customer?->l_name ?? '')) ?: '-' }}<br><small>{{ $order->customer?->phone ?: '-' }}</small></td>
                    <td><span class="badge bg-info text-dark">{{ translate($order->shipping_operational_status ?: 'pending_assignment') }}</span></td>
                    <td>
                        <strong>{{ $order->seller_queue_position ? '#'.$order->seller_queue_position : '-' }}</strong>
                        @if($order->seller_is === 'seller' && in_array($order->order_status, \App\Services\OrderWorkflowService::ACTIVE_QUEUE_STATUSES, true))
                            <form method="post" action="{{ route('admin.orders.logistics.queue-override', $order->id) }}" class="d-grid gap-1 mt-1">@csrf
                                <input class="form-control form-control-sm" type="number" min="1" max="9999" name="priority" value="{{ $order->seller_queue_priority ?: 1000 }}" required>
                                <input class="form-control form-control-sm" name="reason" placeholder="{{ translate('override_reason') }}" required>
                                <button class="btn btn-outline-warning btn-sm" type="submit">{{ translate('override_queue') }}</button>
                            </form>
                        @endif
                    </td>
                    <td style="min-width: 245px">
                        <div class="mb-2">{{ translate($order->shipping_responsible_party ?: 'not_assigned') }}</div>
                        <form method="post" action="{{ route('admin.orders.logistics.quick-assign', $order->id) }}" class="d-flex gap-1 align-items-center">
                            @csrf
                            <select name="assignment" class="form-select form-select-sm" required aria-label="{{ translate('quick_shipping_assignment') }}">
                                <option value="">{{ translate('choose_shipping_party') }}</option>
                                <option value="seller_shipping">{{ translate('shipping_by_seller') }}</option>
                                @foreach($adminShippingMethods as $method)
                                    <option value="admin_method:{{ $method->id }}">
                                        {{ translate('shipping_by_company') }} — {{ $method->title }} ({{ webCurrencyConverter(amount: $method->cost) }})
                                    </option>
                                @endforeach
                            </select>
                            <button class="btn btn-primary btn-sm text-nowrap" type="submit" title="{{ translate('assign_shipping_now') }}">
                                {{ translate('assign') }}
                            </button>
                        </form>
                        <small class="text-muted">{{ translate('quick_assignment_uses_the_selected_company_default_cost') }}</small>
                    </td>
                    <td>{{ $order->third_party_delivery_tracking_id ?: '-' }}</td>
                    <td><small>{{ translate('customer') }}: {{ webCurrencyConverter(amount: $order->shipping_customer_cost ?? $order->shipping_cost) }}</small><br><small>{{ translate('seller') }}: {{ webCurrencyConverter(amount: $order->shipping_seller_cost ?? 0) }}</small></td>
                    <td>
                        @if($order->return_started_at)<small>{{ translate($order->return_responsible_party ?: 'not_assigned') }} · {{ $order->return_tracking_number ?: '-' }}<br>{{ webCurrencyConverter(amount: $order->return_shipping_cost ?? 0) }}</small>
                        @else
                            <form method="post" action="{{ route('admin.orders.logistics.return', $order->id) }}" class="d-grid gap-1">@csrf
                                <select name="return_responsible_party" class="form-select form-select-sm" required><option value="">{{ translate('return_responsibility') }}</option>@foreach($responsibleParties as $party)<option value="{{ $party }}">{{ translate($party) }}</option>@endforeach</select>
                                <input class="form-control form-control-sm" type="number" min="0" step="0.001" name="return_shipping_cost" placeholder="{{ translate('return_shipping_cost') }}">
                                <input class="form-control form-control-sm" name="return_tracking_number" placeholder="{{ translate('return_tracking_number') }}">
                                <input class="form-control form-control-sm" name="return_reason" required placeholder="{{ translate('return_reason') }}">
                                <button class="btn btn-outline-danger btn-sm" type="submit">{{ translate('register_return') }}</button>
                            </form>
                        @endif
                    </td>
                    <td><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.orders.details', $order->id) }}">{{ translate('details') }}</a></td>
                </tr>
            @empty<tr><td colspan="9" class="text-center py-5">{{ translate('no_data_found') }}</td></tr>@endforelse</tbody>
        </table></div>
        @if($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif
        </div>
    </div>
@endsection
