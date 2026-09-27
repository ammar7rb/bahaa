@php use App\Utils\Helpers; @endphp

@extends('layouts.admin.app')

@section('title', translate('dashboard'))

@push('css_or_js')
    <style>
        .admin-dashboard-v3 { --dash-blue:#2563eb; --dash-ink:#172033; --dash-muted:#6b7280; }
        .admin-dashboard-v3 .dashboard-hero { position:relative; overflow:hidden; padding:28px 30px; border-radius:22px; color:#fff; background:linear-gradient(125deg,#1249b8 0%,#2563eb 58%,#4f8df7 100%); box-shadow:0 16px 38px rgba(37,99,235,.20); }
        .admin-dashboard-v3 .dashboard-hero::after { content:""; position:absolute; width:260px; height:260px; inset-inline-end:-75px; top:-120px; border:48px solid rgba(255,255,255,.09); border-radius:50%; }
        .admin-dashboard-v3 .dashboard-hero h1,.admin-dashboard-v3 .dashboard-hero p { color:#fff; position:relative; z-index:1; }
        .admin-dashboard-v3 .dashboard-hero-meta { position:relative; z-index:1; display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
        .admin-dashboard-v3 .dashboard-live-pill { display:inline-flex; align-items:center; gap:7px; padding:7px 11px; border-radius:999px; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.22); font-size:12px; font-weight:700; }
        .admin-dashboard-v3 .dashboard-live-dot { width:8px; height:8px; border-radius:50%; background:#67e8a5; box-shadow:0 0 0 5px rgba(103,232,165,.18); }
        .admin-dashboard-v3 .dashboard-panel { border:1px solid rgba(148,163,184,.18)!important; border-radius:20px!important; box-shadow:0 9px 28px rgba(15,23,42,.055)!important; overflow:hidden; background:var(--bs-body-bg,#fff); }
        .admin-dashboard-v3 .dashboard-panel-header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 20px; border-bottom:1px solid rgba(148,163,184,.14); }
        .admin-dashboard-v3 .dashboard-header-icon { width:40px; height:40px; display:grid; place-items:center; border-radius:12px; color:#2563eb; background:#edf4ff; font-size:17px; }
        .admin-dashboard-v3 .dashboard-shortcut-group-title { display:flex; align-items:center; gap:8px; margin-bottom:11px; color:var(--dash-muted); font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.025em; }
        .admin-dashboard-v3 .dashboard-shortcut-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
        .admin-dashboard-v3 .dashboard-shortcut { position:relative; min-height:70px; display:flex; align-items:center; gap:11px; width:100%; padding:12px; border:1px solid #e4eaf2!important; border-radius:15px; color:var(--dash-ink); background:#fff; transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease; text-decoration:none; }
        .admin-dashboard-v3 .dashboard-shortcut:hover { transform:translateY(-2px); border-color:#a9c4fb!important; box-shadow:0 9px 22px rgba(37,99,235,.10); color:#174fb9; }
        .admin-dashboard-v3 .dashboard-shortcut-icon { flex:0 0 42px; width:42px; height:42px; display:grid; place-items:center; border-radius:13px; color:#2563eb; background:#edf4ff; font-size:18px; }
        .admin-dashboard-v3 .dashboard-shortcut-label { flex:1; min-width:0; font-size:13px; font-weight:750; line-height:1.35; }
        .admin-dashboard-v3 .dashboard-shortcut-arrow { color:#a5afbf; font-size:17px; }
        .admin-dashboard-v3 .dashboard-shortcut-count { position:absolute; inset-inline-start:35px; top:7px; min-width:19px; height:19px; padding:0 5px; display:grid; place-items:center; border-radius:999px; color:#fff; background:#ef4444; border:2px solid #fff; font-size:10px; font-weight:800; }
        .admin-dashboard-v3 .dashboard-shortcut-muted .dashboard-shortcut-icon { color:#64748b; background:#f1f5f9; }
        .admin-dashboard-v3 .dashboard-collapse-toggle { display:inline-flex; align-items:center; gap:8px; min-height:38px; padding:7px 12px; border:1px solid #dbe5f2; border-radius:11px; color:#31527f; background:#f7faff; font-size:12px; font-weight:800; }
        .admin-dashboard-v3 .dashboard-shortcuts-panel.is-collapsed #dashboard-shortcuts-content { display:none; }
        .admin-dashboard-v3 .dashboard-live-card { min-height:112px; position:relative; display:flex; align-items:center; gap:13px; padding:17px; border:1px solid #e8edf4; border-radius:16px; background:linear-gradient(145deg,#fff,#f8faff); color:var(--dash-ink); transition:transform .18s ease,box-shadow .18s ease; }
        .admin-dashboard-v3 .dashboard-live-card:hover { transform:translateY(-2px); box-shadow:0 9px 24px rgba(15,23,42,.08); }
        .admin-dashboard-v3 .dashboard-live-icon { width:44px; height:44px; flex:0 0 44px; display:grid; place-items:center; border-radius:14px; font-size:18px; color:var(--metric-color); background:color-mix(in srgb,var(--metric-color) 11%,white); }
        .admin-dashboard-v3 .dashboard-live-card[data-tone="blue"] { --metric-color:#2563eb; }.admin-dashboard-v3 .dashboard-live-card[data-tone="violet"] { --metric-color:#7c3aed; }.admin-dashboard-v3 .dashboard-live-card[data-tone="orange"] { --metric-color:#ea580c; }.admin-dashboard-v3 .dashboard-live-card[data-tone="green"] { --metric-color:#059669; }.admin-dashboard-v3 .dashboard-live-card[data-tone="cyan"] { --metric-color:#0891b2; }.admin-dashboard-v3 .dashboard-live-card[data-tone="rose"] { --metric-color:#e11d48; }
        .admin-dashboard-v3 .dashboard-live-value { font-size:27px; line-height:1; font-weight:850; color:var(--dash-ink); }
        .admin-dashboard-v3 .dashboard-live-label { margin-top:7px; color:var(--dash-muted); font-size:12px; line-height:1.35; }
        .admin-dashboard-v3 .dashboard-overview-card { --metric-color:#2563eb; min-height:114px; display:flex; align-items:center; gap:13px; padding:17px; border:1px solid #e6edf6; border-radius:16px; color:var(--dash-ink); background:linear-gradient(145deg,#fff,#f8faff); text-decoration:none; }
        .admin-dashboard-v3 .dashboard-overview-icon { width:44px; height:44px; flex:0 0 44px; display:grid; place-items:center; border-radius:14px; color:var(--metric-color); background:color-mix(in srgb,var(--metric-color) 11%,white); font-size:18px; }
        .admin-dashboard-v3 .dashboard-overview-value { display:block; font-size:23px; line-height:1.1; font-weight:850; color:var(--dash-ink); }
        .admin-dashboard-v3 .dashboard-overview-label { display:block; margin-top:7px; color:var(--dash-muted); font-size:12px; }
        .admin-dashboard-v3 .dashboard-finance-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
        .admin-dashboard-v3 .dashboard-finance-card { min-height:118px; padding:17px; border:1px solid #e6edf6; border-radius:16px; background:linear-gradient(145deg,#fff,#f8faff); }
        .admin-dashboard-v3 .dashboard-finance-card.is-primary { color:#fff; border-color:transparent; background:linear-gradient(135deg,#173f9d,#2563eb); }
        .admin-dashboard-v3 .dashboard-finance-card.is-primary .text-muted,.admin-dashboard-v3 .dashboard-finance-card.is-primary .dashboard-finance-label { color:rgba(255,255,255,.76)!important; }
        .admin-dashboard-v3 .dashboard-finance-value { margin-top:12px; color:inherit; font-size:20px; font-weight:850; overflow-wrap:anywhere; }
        .admin-dashboard-v3 .dashboard-finance-label { color:var(--dash-muted); font-size:12px; line-height:1.35; }
        .admin-dashboard-v3 .dashboard-list-shell { border:1px solid #e5ebf3; border-radius:18px; background:var(--bs-body-bg,#fff); overflow:hidden; }
        .admin-dashboard-v3 .dashboard-list-item { display:flex; align-items:center; gap:12px; min-height:76px; padding:12px 14px; border:1px solid #e8edf4; border-radius:14px; background:linear-gradient(145deg,#fff,#fafcff); }
        .admin-dashboard-v3 .dashboard-rank { width:28px; height:28px; flex:0 0 28px; display:grid; place-items:center; border-radius:9px; color:#2563eb; background:#edf4ff; font-size:11px; font-weight:850; }
        .admin-dashboard-v3 #order_stats > [class*="col-"] > * { border-radius:15px!important; border-color:#e7ecf3!important; transition:transform .18s ease,box-shadow .18s ease; }
        .admin-dashboard-v3 #order_stats > [class*="col-"] > *:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(15,23,42,.07); }
        .admin-dashboard-v3 #order_stats .business-analytics { background:linear-gradient(145deg,#fff,#f7faff); }
        .admin-dashboard-v3 .custom-select { border-radius:12px; border-color:#dce4ef; min-height:42px; }
        .admin-dashboard-v3 .dashboard-analytics-panel .card-body { padding:20px; }
        .admin-dashboard-v3 .dashboard-analytics-heading-icon { width:42px; height:42px; flex:0 0 42px; display:grid; place-items:center; border-radius:13px; color:#2563eb; background:#edf4ff; font-size:19px; }
        .admin-dashboard-v3 .dashboard-analytics-summary { position:relative; isolation:isolate; min-height:132px; display:flex; align-items:center; gap:14px; padding:19px; overflow:hidden; border:1px solid #e3eaf4; border-radius:17px; color:var(--dash-ink); background:linear-gradient(145deg,#fff 0%,#f7faff 100%); text-decoration:none; box-shadow:0 5px 16px rgba(15,23,42,.04); transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease; }
        .admin-dashboard-v3 .dashboard-analytics-summary::after { content:""; position:absolute; z-index:-1; width:100px; height:100px; inset-inline-end:-42px; inset-block-start:-48px; border-radius:50%; background:var(--analytics-accent); opacity:.08; }
        .admin-dashboard-v3 .dashboard-analytics-summary:hover { color:var(--dash-ink); border-color:color-mix(in srgb,var(--analytics-accent) 42%,#d9e2ef); box-shadow:0 12px 28px rgba(15,23,42,.09); transform:translateY(-3px); }
        .admin-dashboard-v3 .dashboard-analytics-summary-icon { width:50px; height:50px; flex:0 0 50px; display:grid; place-items:center; border-radius:15px; color:var(--analytics-accent); background:color-mix(in srgb,var(--analytics-accent) 12%,white); font-size:21px; }
        .admin-dashboard-v3 .dashboard-analytics-summary-content { flex:1; min-width:0; display:flex; flex-direction:column; gap:7px; }
        .admin-dashboard-v3 .dashboard-analytics-summary-label { color:var(--dash-muted); font-size:12px; font-weight:750; line-height:1.35; }
        .admin-dashboard-v3 .dashboard-analytics-summary-value { color:var(--dash-ink); font-size:29px; font-weight:850; line-height:1; }
        .admin-dashboard-v3 .dashboard-analytics-card-arrow { color:#a2aec0; font-size:18px; opacity:0; transform:translateX(5px); transition:opacity .18s ease,transform .18s ease; }
        .admin-dashboard-v3 .dashboard-analytics-summary:hover .dashboard-analytics-card-arrow { opacity:1; transform:none; }
        .admin-dashboard-v3 .dashboard-order-status-card { min-height:76px; display:flex; align-items:center; gap:12px; padding:13px 15px; border:1px solid #e6ebf3; border-radius:15px; color:var(--dash-ink); background:#f8fafd; text-decoration:none; transition:transform .18s ease,border-color .18s ease,background-color .18s ease,box-shadow .18s ease; }
        .admin-dashboard-v3 .dashboard-order-status-card:hover { color:var(--dash-ink); border-color:color-mix(in srgb,var(--analytics-accent) 40%,#d8e0eb); background:#fff; box-shadow:0 8px 20px rgba(15,23,42,.07); transform:translateY(-2px); }
        .admin-dashboard-v3 .dashboard-order-status-icon { width:39px; height:39px; flex:0 0 39px; display:grid; place-items:center; border-radius:12px; color:var(--analytics-accent); background:color-mix(in srgb,var(--analytics-accent) 12%,white); font-size:17px; }
        .admin-dashboard-v3 .dashboard-order-status-label { flex:1; min-width:0; color:var(--dash-ink); font-size:13px; font-weight:750; line-height:1.3; }
        .admin-dashboard-v3 .dashboard-order-status-value { min-width:30px; color:var(--analytics-accent); font-size:19px; font-weight:850; text-align:end; }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 { --dash-ink:#edf3ff; --dash-muted:#aab5c7; }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-shortcut,[data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-live-card,[data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-overview-card,[data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-finance-card,[data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-list-item { background:#172033; border-color:#2b3850!important; }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-shortcut-icon,[data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-header-icon { background:#1e3155; }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-collapse-toggle { color:#cbd8ec; background:#172033; border-color:#2b3850; }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-list-shell { border-color:#2b3850; background:#111827; }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-rank { background:#1e3155; }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-analytics-heading-icon { color:#83b1ff; background:#1e3155; }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-analytics-summary { color:#edf3ff; border-color:#30415f; background:linear-gradient(145deg,#1a263b 0%,#141f32 100%); box-shadow:0 8px 22px rgba(0,0,0,.16); }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-analytics-summary::after { opacity:.14; }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-analytics-summary:hover { color:#fff; border-color:color-mix(in srgb,var(--analytics-accent) 55%,#30415f); box-shadow:0 13px 30px rgba(0,0,0,.27); }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-analytics-summary-icon,[data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-order-status-icon { background:color-mix(in srgb,var(--analytics-accent) 18%,#172033); }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-analytics-summary-label { color:#aebdd3; }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-analytics-summary-value,[data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-order-status-label { color:#f1f5fb; }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-order-status-card { color:#edf3ff; border-color:#2b3b57; background:#18243a; }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-order-status-card:hover { color:#fff; border-color:color-mix(in srgb,var(--analytics-accent) 52%,#30415f); background:#1d2b44; box-shadow:0 10px 24px rgba(0,0,0,.22); }
        [data-ba-admin-theme="dark"] .admin-dashboard-v3 .dashboard-analytics-card-arrow { color:#7890b5; }
        [dir="ltr"] .admin-dashboard-v3 .dashboard-shortcut-arrow { transform:rotate(180deg); }
        @media(max-width:1199.98px){.admin-dashboard-v3 .dashboard-finance-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
        @media(max-width:1199.98px){.admin-dashboard-v3 .dashboard-shortcut-grid{grid-template-columns:repeat(3,minmax(0,1fr));}}
        @media(max-width:767.98px){.admin-dashboard-v3 .dashboard-hero{padding:22px 20px}.admin-dashboard-v3 .dashboard-shortcut-grid{grid-template-columns:repeat(2,minmax(0,1fr));}.admin-dashboard-v3 .dashboard-shortcut{min-height:64px;padding:10px}.admin-dashboard-v3 .dashboard-shortcut-icon{width:38px;height:38px;flex-basis:38px;}}
        @media(max-width:575.98px){.admin-dashboard-v3 .dashboard-finance-grid{grid-template-columns:1fr;}.admin-dashboard-v3 .dashboard-panel-header{align-items:flex-start;}.admin-dashboard-v3 .dashboard-analytics-panel .dashboard-panel-header{flex-wrap:wrap;}.admin-dashboard-v3 .dashboard-analytics-panel .dashboard-panel-header>div:last-child,.admin-dashboard-v3 .dashboard-analytics-panel .min-w-200,.admin-dashboard-v3 .dashboard-analytics-panel .custom-select{width:100%!important;}}
        @media(max-width:420px){.admin-dashboard-v3 .dashboard-shortcut-grid{grid-template-columns:1fr;}}
    </style>
@endpush

@section('content')
    @if (auth('admin')->user()->admin_role_id == 1 || Helpers::module_permission_check('dashboard'))
        <div class="content container-fluid admin-dashboard-v3">
            <div class="dashboard-hero mb-4">
                <h1 class="page-header-title">{{ translate('welcome') . ' ' . auth('admin')->user()->name }}</h1>
                <p class="mb-3 opacity-75">{{ translate('monitor_your_business_analytics_and_statistics') . '.' }}</p>
                <div class="dashboard-hero-meta">
                    <span class="dashboard-live-pill"><span class="dashboard-live-dot"></span>{{ translate('live_dashboard') }}</span>
                    <span class="dashboard-live-pill"><i class="fi fi-rr-calendar"></i>{{ now()->translatedFormat('l، j F Y') }}</span>
                </div>
            </div>

            @include('admin-views.system.partials.operational-shortcuts')
            @include('admin-views.system.partials.seller-activation-inbox')

            <div class="card mb-3 dashboard-panel">
                <div class="card-header dashboard-panel-header">
                    <div>
                        <h4 class="mb-1 fw-bold fs-16">{{ translate('operational_notifications') }}</h4>
                        <p class="text-muted mb-0 fs-12">{{ translate('updates_automatically_without_reloading_the_page') }}</p>
                    </div>
                    <span class="dashboard-live-pill text-success border bg-success bg-opacity-10"><span class="dashboard-live-dot"></span>{{ translate('live') }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach([
                            ['label' => 'new_orders', 'key' => 'new_order_count', 'value' => $data['new_order_count'], 'url' => route('admin.orders.list', ['pending']), 'icon' => 'fi fi-sr-shopping-cart', 'tone' => 'blue'],
                            ['label' => 'pending_activation_requests', 'key' => 'pending_activation_count', 'value' => $data['pending_activation_count'], 'url' => route('admin.activation-center.index'), 'icon' => 'fi fi-sr-user-check', 'tone' => 'violet'],
                            ['label' => 'pending_refund_Requests', 'key' => 'pending_refund_count', 'value' => $data['pending_refund_count'], 'url' => route('admin.refund-section.refund.list', ['status' => 'pending']), 'icon' => 'fi fi-sr-refund-alt', 'tone' => 'orange'],
                            ['label' => 'new_customers_today', 'key' => 'customer_registrations_today', 'value' => $data['customer_registrations_today'], 'url' => route('admin.customer.list'), 'icon' => 'fi fi-sr-user-add', 'tone' => 'green'],
                            ['label' => 'new_sellers_today', 'key' => 'seller_registrations_today', 'value' => $data['seller_registrations_today'], 'url' => route('admin.vendors.vendor-list'), 'icon' => 'fi fi-sr-shop', 'tone' => 'cyan'],
                            ['label' => 'password_reset_requests_today', 'key' => 'password_resets_today', 'value' => $data['password_resets_today'], 'url' => route('admin.customer.list'), 'icon' => 'fi fi-sr-key', 'tone' => 'rose'],
                        ] as $liveMetric)
                            <div class="col-6 col-lg-4 col-xl-2">
                                <a href="{{ $liveMetric['url'] }}" class="dashboard-live-card" data-tone="{{ $liveMetric['tone'] }}">
                                    <span class="dashboard-live-icon"><i class="{{ $liveMetric['icon'] }}"></i></span>
                                    <span>
                                        <span class="dashboard-live-value d-block" data-live-metric="{{ $liveMetric['key'] }}">{{ $liveMetric['value'] }}</span>
                                        <span class="dashboard-live-label d-block">{{ translate($liveMetric['label']) }}</span>
                                    </span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mb-3 dashboard-panel dashboard-analytics-panel">
                <div class="card-header dashboard-panel-header">
                    <div class="d-flex align-items-center gap-3">
                        <span class="dashboard-analytics-heading-icon" aria-hidden="true">
                            <i class="fi fi-sr-chart-histogram"></i>
                        </span>
                        <h4 class="text-capitalize mb-0 fw-bold fs-16">{{ translate('Business_Analytics') }}</h4>
                    </div>
                    <div class="d-flex justify-content-end">
                        <div class="min-w-200">
                                <select class="custom-select w-auto" name="statistics_type" id="statistics_type">
                                    <option value="overall"
                                        {{ session()->has('statistics_type') && session('statistics_type') == 'overall' ? 'selected' : '' }}>
                                        {{ translate('Overall_Statistics') }}
                                    </option>
                                    <option value="today"
                                        {{ session()->has('statistics_type') && session('statistics_type') == 'today' ? 'selected' : '' }}>
                                        {{ translate('Today_Statistics') }}
                                    </option>
                                    <option value="this_week"
                                        {{ session()->has('statistics_type') && session('statistics_type') == 'this_week' ? 'selected' : '' }}>
                                        {{ translate('This_Week_Statistics') }}
                                    </option>
                                    <option value="this_month"
                                        {{ session()->has('statistics_type') && session('statistics_type') == 'this_month' ? 'selected' : '' }}>
                                        {{ translate('This_Month_Statistics') }}
                                    </option>
                                    <option value="this_year"
                                        {{ session()->has('statistics_type') && session('statistics_type') == 'this_year' ? 'selected' : '' }}>
                                        {{ translate('This_Year_Statistics') }}
                                    </option>
                                </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3" id="order_stats">
                        @include('admin-views.partials._dashboard-order-status', ['data' => $data])
                    </div>
                </div>
            </div>

            <div class="card mb-3 dashboard-panel">
                <div class="card-header dashboard-panel-header">
                    <div>
                        <h4 class="d-flex align-items-center text-capitalize gap-10 mb-1 fw-bold fs-16"><i class="fi fi-sr-chart-pie-alt text-primary"></i>{{ translate('Customer_and_Platform_Overview') }}</h4>
                        <p class="text-muted mb-0 fs-12">{{ translate('privacy_safe_admin_metrics') }}</p>
                    </div>
                    <span class="dashboard-header-icon"><i class="fi fi-sr-stats"></i></span>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach([
                            ['label' => 'Active_Customers', 'value' => $data['active_customer_count'], 'url' => route('admin.customer.list'), 'icon'=>'fi fi-sr-users', 'color'=>'#059669', 'live'=>'active_customer_count'],
                            ['label' => 'Active_Products', 'value' => $data['active_product_count'], 'url' => route('admin.products.list', ['type' => 'all']), 'icon'=>'fi fi-sr-box-open', 'color'=>'#2563eb', 'live'=>'active_product_count'],
                            ['label' => 'Pending_Product_Reviews', 'value' => $data['pending_product_count'], 'url' => route('admin.products.list', ['type' => 'vendor', 'request_status' => 0]), 'icon'=>'fi fi-sr-time-check', 'color'=>'#ea580c', 'live'=>'pending_product_count'],
                            ['label' => 'Active_Vendor_Packages', 'value' => $data['active_vendor_packages'], 'url' => route('admin.vendors.packages.index'), 'icon'=>'fi fi-sr-boxes', 'color'=>'#7c3aed', 'live'=>''],
                            ['label' => 'Active_Campaign_Subscriptions', 'value' => $data['active_campaign_subscriptions'], 'url' => route('admin.vendors.quota-control.index'), 'icon'=>'fi fi-sr-megaphone', 'color'=>'#db2777', 'live'=>''],
                            ['label' => 'website_page_views', 'value' => $data['website_page_views'], 'url' => route('admin.report.operational-print', ['section'=>'visits','q'=>'web']), 'icon'=>'fi fi-sr-globe', 'color'=>'#0891b2', 'live'=>''],
                            ['label' => 'app_api_requests', 'value' => $data['app_api_requests'], 'url' => route('admin.report.operational-print', ['section'=>'visits','q'=>'app']), 'icon'=>'fi fi-sr-mobile-button', 'color'=>'#4f46e5', 'live'=>''],
                        ] as $metric)
                            <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                                <a href="{{ $metric['url'] }}" class="dashboard-overview-card" style="--metric-color:{{ $metric['color'] }}">
                                    <span class="dashboard-overview-icon"><i class="{{ $metric['icon'] }}"></i></span>
                                    <span><span class="dashboard-overview-value" @if($metric['live']) data-live-metric="{{ $metric['live'] }}" @endif>{{ number_format($metric['value']) }}</span><span class="dashboard-overview-label">{{ translate($metric['label']) }}</span></span>
                                </a>
                            </div>
                        @endforeach
                        <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                            <div class="dashboard-overview-card" style="--metric-color:#0f766e">
                                <span class="dashboard-overview-icon"><i class="fi fi-sr-sack-dollar"></i></span>
                                <span><span class="dashboard-overview-value">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $data['paid_sales_total'])) }}</span><span class="dashboard-overview-label">{{ translate('Paid_Sales_Total') }}</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3 dashboard-panel">
                <div class="card-header dashboard-panel-header">
                    <div>
                        <h4 class="d-flex align-items-center text-capitalize gap-10 mb-1"><i class="fi fi-sr-wallet text-primary"></i><span class="fw-bold fs-16">{{ translate('Management_Wallet') }}</span></h4>
                        <p class="text-muted mb-0 fs-12">{{ translate('financial_control_overview') }}</p>
                    </div>
                    <a href="{{ route('admin.report.earning') }}" class="btn btn-outline-primary btn-sm">{{ translate('open_financial_center') }}</a>
                </div>
                <div class="card-body">
                    @php
                        $customerInsuranceTotal = $data['customer_insurance_held'] + $data['customer_insurance_balance'];
                        $sellerInsuranceTotal = $data['seller_base_insurance_total'] + $data['seller_order_insurance_total'];
                    @endphp
                    <div class="dashboard-finance-grid" id="wallet_stats">
                        @foreach([
                            ['label'=>'management_wallet_total','value'=>$data['management_wallet_total'],'icon'=>'fi fi-sr-wallet','primary'=>true],
                            ['label'=>'paid_sales_total','value'=>$data['paid_sales_total'],'icon'=>'fi fi-sr-sack-dollar'],
                            ['label'=>'customer_insurance_total','value'=>$customerInsuranceTotal,'icon'=>'fi fi-sr-user-shield'],
                            ['label'=>'seller_insurance_total','value'=>$sellerInsuranceTotal,'icon'=>'fi fi-sr-shop-lock'],
                            ['label'=>'customer_insurance_held','value'=>$data['customer_insurance_held'],'icon'=>'fi fi-sr-hourglass-end'],
                            ['label'=>'customer_insurance_balance','value'=>$data['customer_insurance_balance'],'icon'=>'fi fi-sr-coins'],
                            ['label'=>'seller_base_insurance','value'=>$data['seller_base_insurance_total'],'icon'=>'fi fi-sr-shield-check'],
                            ['label'=>'seller_order_insurance','value'=>$data['seller_order_insurance_total'],'icon'=>'fi fi-sr-file-invoice-dollar'],
                            ['label'=>'pending_amount','value'=>$data['pending_amount'],'icon'=>'fi fi-sr-time-past'],
                            ['label'=>'pending_refund_amount','value'=>$data['pending_refund_amount'],'icon'=>'fi fi-sr-refund-alt'],
                            ['label'=>'in-house_earning','value'=>$data['inhouse_earning'],'icon'=>'fi fi-sr-house-chimney'],
                            ['label'=>'commission_earned','value'=>$data['commission_earned'],'icon'=>'fi fi-sr-percentage'],
                            ['label'=>'delivery_charge_earned','value'=>$data['delivery_charge_earned'],'icon'=>'fi fi-sr-truck-moving'],
                            ['label'=>'total_tax_collected','value'=>$data['total_tax_collected'],'icon'=>'fi fi-sr-receipt'],
                            ['label'=>'withdrawn_amount','value'=>$data['withdrawn'],'icon'=>'fi fi-sr-money-bill-transfer'],
                        ] as $finance)
                            <div class="dashboard-finance-card {{ $finance['primary'] ?? false ? 'is-primary' : '' }}">
                                <div class="d-flex align-items-center justify-content-between gap-2"><span class="dashboard-finance-label">{{ translate($finance['label']) }}</span><i class="{{ $finance['icon'] }}"></i></div>
                                <div class="dashboard-finance-value">{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $finance['value']), currencyCode: getCurrencyCode()) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8" id="order-statistics-div">
                    @include('admin-views.system.partials.order-statistics')
                </div>
                <div class="col-lg-4">
                    <div class="card remove-card-shadow h-100">
                        <div class="card-header">
                            <h4 class="d-flex align-items-center text-capitalize gap-10 mb-0 fw-bold fs-16">
                                {{ translate('User_Overview') }}
                            </h4>
                        </div>
                        <div class="card-body justify-content-center d-flex flex-column">
                            <div>
                                <div class="position-relative">
                                    <div id="chart" class="apex-pie-chart d-flex justify-content-center"></div>
                                    <div class="total--orders">
                                        <h3 class="fw-bold" data-live-total-users>{{ $data['getTotalCustomerCount'] + $data['getTotalVendorCount'] }}
                                        </h3>
                                        <span class="text-capitalize">{{ translate('total_User') }}</span>
                                    </div>
                                </div>
                                <div class="apex-legends flex-column">
                                    <div data-color="#7bc4ff">
                                        <span class="text-capitalize" data-live-user-label="customer">{{ translate('total_customer') . ' ' . '(' . $data['getTotalCustomerCount'] . ')' }}
                                        </span>
                                    </div>
                                    <div data-color="#f9b530">
                                        <span
                                            class="text-capitalize" data-live-user-label="vendor">{{ translate('total_vendor') . ' ' . '(' . $data['getTotalVendorCount'] . ')' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12" id="earn-statistics-div">
                    @include('admin-views.system.partials.earning-statistics')
                </div>
                <div class="col-12">
                    @include('admin-views.system.partials._users', [
                            'top_customer' => $data['top_customer'],
                        ])
                </div>
                <div class="col-12">
                    @include('admin-views.system.partials._stores', [
                        'topVendorListByWishlist' => $data['topVendorListByWishlist'],
                        'topVendorByEarning' => $data['topVendorByEarning'],
                    ])
                </div>
                <div class="col-12">
                    @include('admin-views.system.partials._vendor-products', [
                        'vendorTopSellProduct' => $data['vendorTopSellProduct'],
                    ])
                </div>
            </div>
        </div>
    @else
        <div class="content container-fluid">
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-12 mb-2 mb-sm-0">
                        <h3 class="text-center">{{ translate('hi') }} {{ auth('admin')->user()->name }}
                            {{ ' , ' . translate('welcome_to_dashboard') }}.</h3>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <span id="earning-statistics-url" data-url="{{ route('admin.dashboard.earning-statistics') }}"></span>
    <span id="order-status-url" data-url="{{ route('admin.dashboard.order-status') }}"></span>
    <span id="seller-text" data-text="{{ translate('vendor') }}"></span>
    <span id="message-commission-text" data-text="{{ translate('commission') }}"></span>
    <span id="in-house-text" data-text="{{ translate('In-house') }}"></span>
    <span id="customer-text" data-text="{{ translate('customer') }}"></span>
    <span id="store-text" data-text="{{ translate('store') }}"></span>
    <span id="product-text" data-text="{{ translate('product') }}"></span>
    <span id="order-text" data-text="{{ translate('order') }}"></span>
    <span id="brand-text" data-text="{{ translate('brand') }}"></span>
    <span id="business-text" data-text="{{ translate('business') }}"></span>
    <span id="orders-text" data-text="{{ $data['order'] }}"></span>
    <span id="user-overview-data" style="background-color: #000;" data-customer="{{ $data['getTotalCustomerCount'] }}"
        data-customer-title="{{ translate('Total_Customer') }}" data-vendor="{{ $data['getTotalVendorCount'] }}"
        data-vendor-title="{{ translate('Total_Vendor') }}"></span>
@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/assets/new/back-end/js/apexcharts.js') }}"></script>
    <script src="{{ dynamicAsset(path: 'public/assets/new/back-end/js/admin/dashboard.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const guideModal = document.getElementById('guideModal');
            const arrowIcon = document.querySelector('.setup-guide__button .fi');
            const shortcutsToggle = document.querySelector('[data-dashboard-shortcuts-toggle]');
            const shortcutsPanel = shortcutsToggle?.closest('.dashboard-shortcuts-panel');

            if (shortcutsToggle && shortcutsPanel) {
                const storageKey = 'ba_admin_dashboard_shortcuts_collapsed';
                const applyShortcutsState = (collapsed) => {
                    shortcutsPanel.classList.toggle('is-collapsed', collapsed);
                    shortcutsToggle.setAttribute('aria-expanded', String(!collapsed));
                    shortcutsToggle.querySelector('[data-shortcuts-toggle-label]').textContent = shortcutsToggle.dataset[collapsed ? 'showLabel' : 'hideLabel'];
                    const icon = shortcutsToggle.querySelector('[data-shortcuts-toggle-icon]');
                    icon.classList.toggle('fi-rr-angle-small-down', collapsed);
                    icon.classList.toggle('fi-rr-angle-small-up', !collapsed);
                };
                let collapsed = localStorage.getItem(storageKey) === '1';
                applyShortcutsState(collapsed);
                shortcutsToggle.addEventListener('click', () => {
                    collapsed = !collapsed;
                    localStorage.setItem(storageKey, collapsed ? '1' : '0');
                    applyShortcutsState(collapsed);
                });
            }

            if (guideModal && arrowIcon) {
                guideModal.addEventListener('shown.bs.modal', () => {
                    arrowIcon.classList.remove('fi-sr-angle-right');
                    arrowIcon.classList.add('fi-sr-angle-down');
                });

                guideModal.addEventListener('hidden.bs.modal', () => {
                    arrowIcon.classList.remove('fi-sr-angle-down');
                    arrowIcon.classList.add('fi-sr-angle-right');
                });
            }
        });
    </script>


@endpush
