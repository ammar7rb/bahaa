<section class="card customer-profile-overview vendor-profile-page-heading">
    <header class="customer-profile-identity"><img src="{{ getStorageImages(path:$seller->shop?->image_full_url,type:'shop') }}" width="64" height="64" alt="{{ translate('store') }}"><div class="customer-profile-name"><h2>{{ $sectionTitle }}</h2><div class="customer-profile-reference"><strong>{{ $seller->shop?->name ?: trim($seller->f_name.' '.$seller->l_name) }}</strong><bdi>{{ $seller->administrative_reference }}</bdi></div></div><a class="btn btn-outline-primary" href="{{ route('admin.vendors.view',$seller->id) }}"><i class="fi fi-rr-arrow-small-right" aria-hidden="true"></i> ملف التاجر</a></header>
</section>
@php
    $money = static fn ($value) => webCurrencyConverter(amount: (float) $value);
    $labels = ['active'=>'سارٍ','scheduled'=>'مجدول','expired'=>'منتهٍ','pending'=>'بانتظار الدفع','pending_review'=>'بانتظار مراجعة الدفع','rejected'=>'مرفوض','cancelled'=>'ملغى','replaced'=>'مستبدل','none'=>'لا يوجد اشتراك','approved'=>'معتمد','suspended'=>'معلّق إداريًا','paid'=>'مدفوع','unpaid'=>'غير مدفوع'];
@endphp
@include('admin-views.vendor.profile.launchers')
@include('admin-views.vendor.profile.subscriptions-modal')
@include('admin-views.vendor.profile.finance-modal')
@include('admin-views.vendor.profile.records')
@if(!request()->is('admin/vendors/view/*/product'))
    @push('script')<script src="{{ dynamicAsset(path:'public/assets/back-end/js/admin-vendor-profile.js') }}?v=20261021.2" defer></script>@endpush
@endif