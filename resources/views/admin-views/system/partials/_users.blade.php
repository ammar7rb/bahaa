<section class="dashboard-list-shell">
    <div class="dashboard-panel-header">
        <div>
            <h3 class="mb-1 fw-bold fs-16"><i class="fi fi-sr-users text-primary me-2"></i>{{ translate('Users') }}</h3>
            <p class="mb-0 text-muted fs-12">{{ translate('top_customers_by_orders') }}</p>
        </div>
        @if($top_customer && count($top_customer) > 0)
            <a href="{{ route('admin.customer.list', ['sort_by' => 'order_amount']) }}" class="btn btn-outline-primary btn-sm">{{ translate('View_All') }}</a>
        @endif
    </div>
    <div class="p-3">
        @if($top_customer && count($top_customer) > 0)
            <div class="row g-2">
                @foreach($top_customer as $key => $customer)
                    <div class="col-md-6 col-xl-4">
                        <a href="{{ route('admin.customer.view', [$customer['id']]) }}" class="dashboard-list-item text-decoration-none text-body">
                            <span class="dashboard-rank">{{ $key + 1 }}</span>
                            <img width="46" class="aspect-1 rounded-circle border" src="{{ getStorageImages(path: $customer->image_full_url, type:'backend-profile') }}" alt="">
                            <span class="flex-grow-1 min-w-0">
                                <strong class="d-block line-1 text-hover-primary">{{ trim(($customer['f_name'] ?? '').' '.($customer['l_name'] ?? '')) ?: translate('not_exist') }}</strong>
                                <span class="d-block text-muted fs-11 line-1">{{ $customer['email'] ?? translate('not_exist') }}</span>
                            </span>
                            <span class="badge text-bg-info badge-info">{{ count($customer['orders']) }} {{ translate('orders') }}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <div class="py-5">@include('layouts.admin.partials._empty-state', ['text' => 'No_user_available', 'image' => 'user', 'width' => 45])</div>
        @endif
    </div>
</section>
