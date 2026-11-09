@php
    $orderQueues = \App\Support\OrderCenterFilters::OPTIONS;
@endphp
<div class="v2-nav-item v2-has-children {{ Request::is('admin/orders/*') ? 'v2-is-active' : '' }}" data-item="orders-all">
    <a class="v2-nav-btn" href="{{ route('admin.orders.center') }}"><span class="v2-nav-label">مركز الطلبات</span></a>
    <div class="v2-nav-right"><span class="v2-nav-chev" aria-label="عرض أنواع الطلبات"><svg width="10" height="10" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m7.5 5 5 5-5 5"/></svg></span></div>
</div>
<div class="v2-nav-children {{ Request::is('admin/orders/*') ? '' : 'v2-is-collapsed' }}" data-children-for="orders-all">
    @foreach($orderQueues as $key=>[$label,$icon])
    <a class="v2-nav-child {{ Request::routeIs('admin.orders.center') && \App\Support\OrderCenterFilters::normalize(request('queue')) === $key ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.center',['queue'=>$key]) }}"><i class="fi {{ $icon }}" aria-hidden="true"></i><span class="v2-nav-child-label">{{ $label }}</span></a>
    @endforeach
    <a class="v2-nav-child" href="{{ route('admin.orders.center') }}#order-services"><i class="fi fi-rr-apps" aria-hidden="true"></i><span class="v2-nav-child-label">خدمات الطلبات</span></a>
</div>
