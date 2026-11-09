@php
    $orderQueues = ['all'=>['كل الطلبات','fi-rr-shopping-cart'], 'awaiting_admin'=>['لم تتم الموافقة','fi-rr-time-past'], 'sent_to_vendor'=>['مرسلة للتاجر','fi-rr-paper-plane'], 'accepted'=>['مقبولة','fi-rr-check-circle'], 'overdue'=>['معلقة / متأخرة','fi-rr-hourglass-end'], 'rejected'=>['مرفوضة','fi-rr-cross-circle'], 'canceled'=>['ملغاة','fi-rr-ban'], 'completed'=>['مكتملة','fi-rr-box-check'], 'guests'=>['طلبات الزوار','fi-rr-user'], 'history'=>['سجل الطلبات الأقدم','fi-rr-clock']];
@endphp
<div class="v2-nav-item v2-has-children {{ Request::is('admin/orders/*') ? 'v2-is-active' : '' }}" data-item="orders-all">
    <a class="v2-nav-btn" href="{{ route('admin.orders.center') }}"><span class="v2-nav-label">مركز الطلبات</span></a>
    <div class="v2-nav-right"><span class="v2-nav-chev" aria-label="عرض أنواع الطلبات"><svg width="10" height="10" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m7.5 5 5 5-5 5"/></svg></span></div>
</div>
<div class="v2-nav-children {{ Request::is('admin/orders/*') ? '' : 'v2-is-collapsed' }}" data-children-for="orders-all">
    @foreach($orderQueues as $key=>[$label,$icon])
    <a class="v2-nav-child {{ Request::routeIs('admin.orders.center') && request('queue','all') === $key ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.center',['queue'=>$key]) }}"><i class="fi {{ $icon }}" aria-hidden="true"></i><span class="v2-nav-child-label">{{ $label }}</span></a>
    @endforeach
    @foreach(['processing'=>'قيد التجهيز','out_for_delivery'=>'خرج للتسليم','returned'=>'مرتجعة','failed'=>'فشل التسليم'] as $key=>$label)
    <a class="v2-nav-child {{ request('queue') === $key ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.center',['queue'=>$key]) }}"><i class="fi fi-rr-truck-side" aria-hidden="true"></i><span class="v2-nav-child-label">{{ $label }}</span></a>
    @endforeach
    <a class="v2-nav-child" href="{{ route('admin.guest-orders.index') }}"><i class="fi fi-rr-user-add" aria-hidden="true"></i><span class="v2-nav-child-label">متابعة الزوار والتواصل</span></a>
    <a class="v2-nav-child" href="{{ route('admin.orders.cancellations') }}"><i class="fi fi-rr-undo" aria-hidden="true"></i><span class="v2-nav-child-label">معالجة الإلغاء والأرصدة</span></a>
    @if(Helpers::module_permission_check('people'))
    <a class="v2-nav-child" href="{{ route('admin.orders.special.credit') }}"><i class="fi fi-rr-credit-card" aria-hidden="true"></i><span class="v2-nav-child-label">طلبات الائتمان</span></a>
    @if(Helpers::module_permission_check('catalog'))
    <a class="v2-nav-child" href="{{ route('admin.orders.special.inspections') }}"><i class="fi fi-rr-search" aria-hidden="true"></i><span class="v2-nav-child-label">فحص الأجهزة المتخصصة</span></a>
    <a class="v2-nav-child" href="{{ route('admin.orders.special.device-purchases') }}"><i class="fi fi-rr-shopping-bag" aria-hidden="true"></i><span class="v2-nav-child-label">شراء الأجهزة المتخصصة</span></a>
    @endif
    @endif
    <a class="v2-nav-child" href="{{ route('admin.orders.package-refunds.index') }}"><span class="v2-nav-child-label">طلبات رد المشتريات</span></a>
    <a class="v2-nav-child" href="{{ route('admin.orders.post-purchase-invoices.index') }}"><span class="v2-nav-child-label">فواتير ما بعد الشراء</span></a>
    <a class="v2-nav-child" href="{{ route('admin.orders.delivery-proofs') }}"><span class="v2-nav-child-label">إثبات التسليم</span></a>
</div>
