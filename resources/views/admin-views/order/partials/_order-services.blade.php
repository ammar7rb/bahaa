<details class="card mt-3" id="order-services" dir="rtl">
    <summary class="card-header"><strong><i class="fi fi-rr-apps" aria-hidden="true"></i> خدمات الطلبات</strong><span class="text-muted ms-2">الزوار، الطلبات الخاصة والمستندات</span></summary>
    <div class="card-body d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="{{ route('admin.guest-orders.index') }}">طلبات الزوار</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.orders.cancellations') }}">الإلغاء والأرصدة</a>
        @if(\App\Utils\Helpers::module_permission_check('people'))
        <a class="btn btn-outline-primary" href="{{ route('admin.orders.special.credit') }}">طلبات الائتمان</a>
        @if(\App\Utils\Helpers::module_permission_check('catalog'))
        <a class="btn btn-outline-primary" href="{{ route('admin.orders.special.inspections') }}">فحص الأجهزة</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.orders.special.device-purchases') }}">شراء الأجهزة</a>
        @endif
        @endif
        <a class="btn btn-outline-secondary" href="{{ route('admin.orders.package-refunds.index') }}">رد المشتريات</a>
        <a class="btn btn-outline-secondary" href="{{ route('admin.orders.post-purchase-invoices.index') }}">فواتير ما بعد الشراء</a>
        <a class="btn btn-outline-secondary" href="{{ route('admin.orders.delivery-proofs') }}">إثبات التسليم</a>
    </div>
</details>
