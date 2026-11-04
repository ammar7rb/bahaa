@php
    $account = $vendorProfile['overview'];
    $subscription = $account['subscription'];
    $capacity = $account['capacity'];
    $sellerStatus = (string) $seller->status;
    $money = static fn ($value) => webCurrencyConverter(amount: (float) $value);
    $labels = ['active'=>'سارٍ','scheduled'=>'مجدول','expired'=>'منتهٍ','pending'=>'بانتظار الدفع','pending_review'=>'بانتظار مراجعة الدفع','rejected'=>'مرفوض','cancelled'=>'ملغى','replaced'=>'مستبدل','none'=>'لا يوجد اشتراك','approved'=>'معتمد','suspended'=>'معلّق إداريًا','paid'=>'مدفوع','unpaid'=>'غير مدفوع'];
@endphp
<section class="card customer-profile-overview">
    <header class="customer-profile-identity">
        <img src="{{ getStorageImages(path: $seller->shop?->image_full_url, type: 'shop') }}" width="64" height="64" alt="{{ translate('store') }}">
        <div class="customer-profile-name">
            <h2>{{ trim($seller->f_name.' '.$seller->l_name) ?: $seller->administrative_reference }}</h2>
            <div class="customer-profile-reference"><strong>{{ $seller->shop?->name ?: 'متجر دون اسم' }}</strong><bdi>{{ $seller->administrative_reference }}</bdi><span>تاريخ الانضمام: {{ $seller->created_at?->format('Y/m/d') }}</span></div>
            <div class="customer-profile-contact"><a @if($seller->phone) href="tel:{{ $seller->phone }}" @endif><bdi>{{ $seller->phone ?: '—' }}</bdi></a><a @if($seller->email) href="mailto:{{ $seller->email }}" @endif><bdi>{{ $seller->email ?: '—' }}</bdi></a></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @include('admin-views.vendor.profile.account-actions')
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#resetVendorPasswordModal" aria-label="{{ translate('reset_password') }}" title="{{ translate('reset_password') }}"><i class="fi fi-rr-key" aria-hidden="true"></i></button>
            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.vendors.view', ['id'=>$seller->id,'tab'=>'setting']) }}">{{ translate('setting') }}</a>
        </div>
    </header>
    <div class="customer-profile-eligibility">
        <h3>حالة حساب التاجر</h3>
        <dl class="customer-profile-statuses">
            <div><dt>الحالة الإدارية</dt><dd><span class="profile-badge {{ $sellerStatus === 'approved' ? 'is-success' : ($sellerStatus === 'suspended' ? 'is-danger' : 'is-warning') }}">{{ $sellerStatus === 'pending' ? 'قيد مراجعة التسجيل' : ($labels[$sellerStatus] ?? translate($sellerStatus)) }}</span></dd></div>
            <div><dt>صلاحية الاشتراك</dt><dd><span class="profile-badge {{ $subscription['active'] ? 'is-success' : 'is-warning' }}">{{ $labels[$subscription['status']] ?? translate($subscription['status']) }}</span></dd></div>
            <div><dt>شرط الحساب والاشتراك للإضافة</dt><dd><span class="profile-badge {{ $vendorProfile['canCreate'] ? 'is-success' : 'is-warning' }}">{{ $vendorProfile['canCreate'] ? 'مستوفى — راجع توفر المساحات' : 'غير مستوفى حاليًا' }}</span></dd></div>
        </dl>
        <div class="customer-profile-package"><div><span class="profile-label">{{ $subscription['active'] ? 'الباقة الحالية' : 'آخر باقة مدفوعة' }}</span><strong>{{ $subscription['package_name'] ?: 'لا توجد باقة' }}</strong></div><div><span class="profile-label">انتهاء الاشتراك</span><time>{{ $subscription['expires_at'] ? \Carbon\Carbon::parse($subscription['expires_at'])->timezone(config('app.timezone'))->format('Y/m/d H:i') : '—' }}</time></div></div>
        <div class="customer-profile-allowance vendor-profile-capacity"><div class="customer-profile-allowance-title"><strong>المساحات الإعلانية</strong><small>{{ $subscription['active'] ? 'ارتباط المنتج بمساحة لا يعني ظهوره قبل استيفاء المراجعة والنشر.' : 'سعة آخر فترة مسجلة؛ لا تمنح صلاحية إعلان حالية.' }}</small></div>@foreach(['total'=>'الإجمالي','used'=>'مرتبطة بمنتجات','available'=>'المتاح الآن','reserved'=>'محجوزة للإضافة'] as $key=>$title)<div><span class="profile-label">{{ $title }}</span><strong>{{ $capacity[$key] }}</strong></div>@endforeach</div>
        @if(!$vendorProfile['canCreate'])<p class="small text-muted mt-3 mb-0">{{ $sellerStatus !== 'approved' ? 'راجع الحالة الإدارية للحساب أولًا.' : 'يلزم اشتراك سارٍ وفق إعدادات النظام الحالية.' }} حالة نشر كل منتج تُراجع بصورة مستقلة.</p>@endif
        
    </div>
</section>
<section class="customer-profile-summary my-3" aria-label="ملخص التاجر">
    @foreach(['available'=>['available_balance','wallet'],'pending_from_platform'=>['pending_from_platform','clock'],'operating'=>['رصيد المدفوعات','credit-card'],'pending_withdrawal'=>['pending_Withdraw','receipt']] as $key=>[$title,$icon])<div class="customer-profile-metric customer-profile-wallet"><i class="fi fi-rr-{{ $icon }}" aria-hidden="true"></i><div><span>{{ translate($title) }}</span><strong><bdi>{{ $money($sellerOverview['balances'][$key]) }}</bdi></strong></div></div>@endforeach
    <div class="customer-profile-metric"><i class="fi fi-rr-box" aria-hidden="true"></i><div><span>إجمالي المنتجات</span><strong>{{ $sellerOverview['products']['total'] }}</strong></div></div>
    <div class="customer-profile-metric customer-profile-wallet"><i class="fi fi-rr-shopping-bag" aria-hidden="true"></i><div><span>مستحقات المبيعات المسجلة</span><strong><bdi>{{ $money($sellerOverview['sales']['total_amount']) }}</bdi></strong></div></div>
</section>
@include('admin-views.vendor.profile.launchers')
<section class="card vendor-profile-section" id="vendor-profile-overview"><h3>نظرة عامة</h3><div class="vendor-profile-inline-stats">@foreach(['active'=>['check-circle','منتجات مفعّلة ومعتمدة'],'under_review'=>['clock','بانتظار مراجعة أولية'],'suspended'=>['pause-circle','منتجات موقوفة'],'rejected'=>['cross-circle','منتجات مرفوضة']] as $key=>[$icon,$title])<div class="vendor-overview-tile"><i class="fi fi-rr-{{ $icon }}" aria-hidden="true"></i><span>{{ $title }}</span><strong>{{ $sellerOverview['products'][$key] }}</strong></div>@endforeach</div><p class="text-muted small mb-0">هذه إحصاءات الاعتماد والتشغيل؛ قد يتوقف الظهور الفعلي على الاشتراك والمساحات ومراجعة التعديلات. <a href="{{ route('admin.vendors.view',['id'=>$seller->id,'tab'=>'product']) }}">إدارة المنتجات وحالاتها</a></p></section>
@include('admin-views.vendor.profile.packages')
@include('admin-views.vendor.profile.finance-modal')
@include('admin-views.vendor.profile.records')
@include('admin-views.vendor.profile.password-modal')

@push('script')<script src="{{ dynamicAsset(path:'public/assets/back-end/js/admin-vendor-profile.js') }}?v=20261021.2" defer></script>@endpush
