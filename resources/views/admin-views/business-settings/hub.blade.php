@extends('layouts.admin.app')

@section('title', 'الإعدادات')

@section('content')
<main class="content container-fluid sigma-settings-hub" dir="rtl">
    <div class="page-header mb-4"><h1 class="page-header-title">الإعدادات</h1><p class="text-muted mb-0">إعدادات العمل وروابط أدوات الإدارة ذات الصلة</p></div>
    @php
        // الشحن، مواضع الصفحة الرئيسية، الباقات الشهرية للعملاء، الأجهزة الطبية، الفئات والعلامات التجارية
        // لها أماكنها في القوائم الجانبية (الإعدادات / العملاء / المنتجات) فلا تتكرر هنا.
        $groups = [
            ['الدفع والضريبة', 'fi fi-rr-credit-card', 'primary', [
                ['جهات استلام المحفظة وInstaPay', 'fi-rr-wallet', 'admin.third-party.offline-payment-method.index', 'payment_destinations_manage'],
                ['ضريبة العملاء والاستثناءات', 'fi-rr-receipt', 'admin.settings.rates.index', 'tax_manage'],
            ]],
            ['نسبة الشركة', 'fi fi-rr-percentage', 'info', [
                ['نسب التجار والاستثناءات', 'fi-rr-percentage', 'admin.settings.rates.index', 'commission_manage'],
            ]],
            ['باقات التجار والإعلانات', 'fi fi-rr-box', 'success', [
                ['باقات التجار', 'fi-rr-box', 'admin.vendors.packages.index', 'package_pricing_manage'],
                ['سعر الإعلان الإضافي', 'fi-rr-megaphone', 'admin.vendors.extra-ads.index', 'package_pricing_manage'],
            ]],
            ['المحتوى القانوني', 'fi fi-rr-document', 'warning', [
                ['الشروط والأحكام وسياسة الخصوصية', 'fi-rr-document-signed', 'admin.settings.legal', 'business_settings'],
            ]],
            ['التواصل والإعلانات', 'fi fi-rr-bell', 'danger', [
                ['إرسال إشعارات إدارية', 'fi-rr-bell-ring', 'admin.settings.notifications.index', 'notification_broadcast'],
                ['لافتات الموقع', 'fi-rr-picture', 'admin.banner.list', 'marketing'],
            ]],
            ['الأمان والتحليلات', 'fi fi-rr-shield-check', 'primary', [
                ['إعادة تعيين كلمات مرور الحسابات', 'fi-rr-key', 'admin.settings.account-security', 'business_settings'],
                ['عدد مشاهدات منتجات التجار', 'fi-rr-eye', 'admin.settings.product-views.index', 'product_view_adjust'],
                ['سجل تغييرات الإعدادات', 'fi-rr-time-past', 'admin.settings.audit', 'business_settings'],
            ]],
        ];
    @endphp
    <div class="row g-3">
        @foreach($groups as [$title, $icon, $tone, $entries])
            @php
                $visible = collect($entries)->filter(fn ($e) => \App\Utils\Helpers::module_permission_check($e[3]) && \Illuminate\Support\Facades\Route::has($e[2])
                    && ($e[2] !== 'admin.settings.account-security' || \App\Utils\Helpers::module_permission_check('customer_password_reset') || \App\Utils\Helpers::module_permission_check('vendor_password_reset')));
            @endphp
            @continue($visible->isEmpty())
            <section class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 sigma-hub-card" data-tone="{{ $tone }}"><div class="card-body">
                    <h2 class="h5 mb-3 sigma-hub-title"><span class="sigma-hub-icon"><i class="{{ $icon }}" aria-hidden="true"></i></span> {{ $title }}</h2>
                    <div class="sigma-hub-list">
                        @foreach($visible as [$label, $entryIcon, $routeName])
                            <a class="sigma-hub-link" href="{{ route($routeName) }}"><span class="sigma-hub-link-icon"><i class="fi {{ $entryIcon }}" aria-hidden="true"></i></span><span class="sigma-hub-link-label">{{ $label }}</span><i class="fi fi-rr-angle-small-left sigma-hub-chevron" aria-hidden="true"></i></a>
                        @endforeach
                    </div>
                </div></div>
            </section>
        @endforeach
    </div>
</main>
@endsection
