@extends('layouts.admin.app')

@section('title', 'الإعدادات')

@section('content')
<main class="content container-fluid" dir="rtl">
    <div class="page-header mb-4"><h1 class="page-header-title">الإعدادات</h1><p class="text-muted mb-0">إعدادات العمل وروابط أدوات الإدارة ذات الصلة</p></div>
    @php
        $groups = [
            ['الدفع والضريبة', 'fi fi-rr-credit-card', [
                ['جهات استلام المحفظة وInstaPay', 'admin.third-party.offline-payment-method.index', 'payment_destinations_manage'],
                ['ضريبة العملاء والاستثناءات', 'admin.settings.rates.index', 'tax_manage'],
            ]],
            ['نسبة الشركة', 'fi fi-rr-percentage', [
                ['نسب التجار والاستثناءات', 'admin.settings.rates.index', 'commission_manage'],
            ]],
            ['باقات العملاء والتجار', 'fi fi-rr-box', [
                ['باقات العملاء وزيادة الحد', 'admin.monthly-packages.index', 'package_pricing_manage'],
                ['باقات التجار', 'admin.vendors.packages.index', 'package_pricing_manage'],
                ['سعر الإعلان الإضافي', 'admin.vendors.extra-ads.index', 'package_pricing_manage'],
            ]],
            ['الشحن', 'fi fi-rr-truck-side', [
                ['طرق الشحن وأسعار المناطق', 'admin.operations.shipping.index', 'shipping_pricing_manage'],
            ]],
            ['المحتوى والكتالوج', 'fi fi-rr-document', [
                ['الشروط والخصوصية', 'admin.pages-and-media.list', 'business_settings'],
                ['الفئات', 'admin.category.view', 'catalog'],
                ['العلامات التجارية للمنتجات', 'admin.brand.list', 'catalog'],
                ['الأجهزة الطبية المتخصصة', 'admin.operations.medical-devices.index', 'catalog'],
            ]],
            ['التواصل والإعلانات', 'fi fi-rr-bell', [
                ['الإشعارات', 'admin.notification.index', 'marketing'],
                ['لافتات الموقع', 'admin.banner.list', 'marketing'],
                ['مواضع الصفحة الرئيسية', 'admin.operations.homepage-sections.index', 'catalog'],
            ]],
        ];
    @endphp
    <div class="row g-3">
        @foreach($groups as [$title, $icon, $entries])
            <section class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 shadow-sm"><div class="card-body">
                    <h2 class="h5 mb-3"><i class="{{ $icon }}" aria-hidden="true"></i> {{ $title }}</h2>
                    <div class="d-grid gap-2">
                        @foreach($entries as [$label, $routeName, $permission])
                            @if(\App\Utils\Helpers::module_permission_check($permission) && \Illuminate\Support\Facades\Route::has($routeName))
                                <a class="btn btn-outline-primary text-start" href="{{ route($routeName) }}">{{ $label }}</a>
                            @endif
                        @endforeach
                    </div>
                </div></div>
            </section>
        @endforeach
    </div>
</main>
@endsection
