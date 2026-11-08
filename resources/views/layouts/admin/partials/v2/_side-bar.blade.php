@php
    use App\Utils\Helpers;
    use App\Enums\EmailTemplateKey;
    $sidebarWorkspaceCategories = app(\App\Services\AdminWorkspaceService::class)->snapshot()['categories'];
    $v2Auction = function_exists('getCheckAddonPublishedStatus') && getCheckAddonPublishedStatus(moduleName: 'Auction');
    $v2Tax = function_exists('getCheckAddonPublishedStatus') && getCheckAddonPublishedStatus(moduleName: 'TaxModule');
    $v2Ai = function_exists('getCheckAddonPublishedStatus') && getCheckAddonPublishedStatus(moduleName: 'AI');
    $v2AuctionRoutes = $v2Auction ? include(base_path("Modules/Auction/Addon/admin_routes.php")) : [];
    $v2TaxRoutes = $v2Tax ? include(base_path("Modules/TaxModule/Addon/tax_routes.php")) : [];
    $v2TaxReportRoutes = $v2Tax ? include(base_path("Modules/TaxModule/Addon/tax_report_routes.php")) : [];
    $v2ThemeRoutes = config('get_theme_routes') ?: [];
    $v2AddonAdminRoutes = config('addon_admin_routes') ?: [];
@endphp

<aside class="v2-ctxpanel" id="v2-ctxpanel">
    <div class="v2-ctx-scroll">

    {{-- ================= HOME (active by default so menu shows even
         before JS has a chance to hydrate from URL or localStorage) ===== --}}
    <div class="v2-ctx-section v2-is-on" data-section="home">
        <div class="v2-ctx-head"><div class="v2-ctx-title">{{ translate('home') }}</div></div>
        <div class="v2-ctx-group v2-is-pinned" style="display:none;">
            <div class="v2-ctx-group-head"><span>{{ translate('pinned') }}</span></div>
            <div class="v2-ctx-group-body"></div>
        </div>
        <div class="v2-ctx-group">
            <div class="v2-ctx-group-head"><span>{{ translate('overview') }}</span></div>
            <a class="v2-nav-item {{ Request::is('admin/dashboard') ? 'v2-is-active' : '' }}" data-item="dashboard" href="{{ route('admin.dashboard.index') }}">
                <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('dashboard') }}</span></span>
                <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="dashboard" aria-label="Pin"></button></div>
            </a>
            @if (Helpers::module_permission_check('pos'))
                <a class="v2-nav-item {{ Request::is('admin/pos*') ? 'v2-is-active' : '' }}" data-item="pos" href="{{ route('admin.pos.index') }}">
                    <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('POS') }}</span></span>
                    <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="pos" aria-label="Pin"></button></div>
                </a>
            @endif

        </div>
    </div>

    {{-- ================= CATALOG ================= --}}
    @if (Helpers::module_permission_check('catalog'))
        <div class="v2-ctx-section" data-section="catalog">
            <div class="v2-ctx-head"><div class="v2-ctx-title">{{ translate('products') }}</div></div>
            <div class="v2-ctx-group v2-is-pinned" style="display:none;">
                <div class="v2-ctx-group-head"><span>{{ translate('pinned') }}</span></div>
                <div class="v2-ctx-group-body"></div>
            </div>

            <div class="v2-ctx-group">
                <div class="v2-ctx-group-head"><span>{{ translate('vendor_Products') }}</span></div>
                <a class="v2-nav-item {{ str_contains(url()->current().'?request_status='.request()->get('request_status'),'admin/products/list/vendor?request_status=0') ? 'v2-is-active' : '' }}" data-item="vendor-new-products" href="{{ route('admin.products.review-queue') }}">
                    <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('new_Products_Requests') }}</span></span>
                    <div class="v2-nav-right">
                        <span data-v2-tag="danger">{{ getVendorProductsCount(type: 'new-product', panel: 'admin') }}</span>
                        <button class="v2-pin-btn" type="button" data-pin="vendor-new-products" aria-label="Pin"></button>
                    </div>
                </a>
                @if (getWebConfig(name: 'product_wise_shipping_cost_approval') == 1)
                    <a class="v2-nav-item {{ Request::is('admin/products/updated-product-list/vendor') ? 'v2-is-active' : '' }}" data-item="vendor-updates" href="{{ route('admin.products.updated-product-list', ['type' => 'vendor']) }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('product_update_requests') }}</span></span>
                        <div class="v2-nav-right">
                            <span data-v2-tag="info">{{ getVendorProductsCount(type: 'product-updated-request', panel: 'admin') }}</span>
                            <button class="v2-pin-btn" type="button" data-pin="vendor-updates" aria-label="Pin"></button>
                        </div>
                    </a>
                @endif
                <a class="v2-nav-item {{ str_contains(url()->current().'?request_status='.request()->get('request_status'),'/admin/products/list/vendor?request_status=1') ? 'v2-is-active' : '' }}" data-item="vendor-approved" href="{{ route('admin.products.list', ['vendor', 'request_status' => '1']) }}">
                    <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('approved_Products') }}</span></span>
                    <div class="v2-nav-right">
                        <span data-v2-tag="success">{{ getVendorProductsCount(type: 'approved', panel: 'admin') }}</span>
                        <button class="v2-pin-btn" type="button" data-pin="vendor-approved" aria-label="Pin"></button>
                    </div>
                </a>
                <a class="v2-nav-item {{ str_contains(url()->current().'?request_status='.request()->get('request_status'),'/admin/products/list/vendor?request_status=2') ? 'v2-is-active' : '' }}" data-item="vendor-denied" href="{{ route('admin.products.list', ['vendor', 'request_status' => '2']) }}">
                    <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('denied_Products') }}</span></span>
                    <div class="v2-nav-right">
                        <span data-v2-tag="danger">{{ getVendorProductsCount(type: 'denied', panel: 'admin') }}</span>
                        <button class="v2-pin-btn" type="button" data-pin="vendor-denied" aria-label="Pin"></button>
                    </div>
                </a>
            </div>

            <div class="v2-ctx-group">
                <div class="v2-ctx-group-head"><span>{{ translate('organization') }}</span></div>
                <div class="v2-nav-item v2-has-children {{ (Request::is('admin/category/*') || Request::is('admin/sub-category/*') || Request::is('admin/sub-sub-category/*')) ? 'v2-is-active' : '' }}" data-item="category-setup">
                    <a class="v2-nav-btn" href="{{ route('admin.category.view') }}">
                        <span class="v2-nav-label">{{ translate('category_Setup') }}</span>
                    </a>
                    <div class="v2-nav-right">
                        <button class="v2-pin-btn" type="button" data-pin="category-setup" aria-label="Pin"></button>
                        <span class="v2-nav-chev" aria-label="{{ translate('toggle') }}">
                            <svg width="10" height="10" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 5 5 5-5 5"/></svg>
                        </span>
                    </div>
                </div>
                <div class="v2-nav-children v2-is-collapsed" data-children-for="category-setup">
                    <a class="v2-nav-child {{ Request::is('admin/category/*') ? 'v2-is-on' : '' }}" href="{{ route('admin.category.view') }}">
                        <span class="v2-nav-child-dot" data-tone="primary"></span>
                        <span class="v2-nav-child-label">{{ translate('categories') }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/sub-category/*') ? 'v2-is-on' : '' }}" href="{{ route('admin.sub-category.view') }}">
                        <span class="v2-nav-child-dot" data-tone="primary"></span>
                        <span class="v2-nav-child-label">{{ translate('sub_Categories') }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/sub-sub-category/*') ? 'v2-is-on' : '' }}" href="{{ route('admin.sub-sub-category.view') }}">
                        <span class="v2-nav-child-dot" data-tone="primary"></span>
                        <span class="v2-nav-child-label">{{ translate('sub_Sub_Categories') }}</span>
                    </a>
                </div>
                <a class="v2-nav-item {{ Request::is('admin/brand/list') ? 'v2-is-active' : '' }}" data-item="brands" href="{{ route('admin.brand.list') }}">
                    <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Brand_Setup') }}</span></span>
                    <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="brands" aria-label="Pin"></button></div>
                </a>
                <a class="v2-nav-item {{ Request::is('admin/products/product-gallery') ? 'v2-is-active' : '' }}" data-item="product-gallery" href="{{ route('admin.products.product-gallery') }}">
                    <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Product_Gallery') }}</span></span>
                    <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="product-gallery" aria-label="Pin"></button></div>
                </a>
            </div>
        </div>
    @endif


    {{-- ================= ORDERS ================= --}}
    @if (Helpers::module_permission_check('orders'))
        <div class="v2-ctx-section" data-section="orders">
            <div class="v2-ctx-head"><div class="v2-ctx-title">{{ translate('orders') }}</div></div>
            <div class="v2-ctx-group v2-is-pinned" style="display:none;">
                <div class="v2-ctx-group-head"><span>{{ translate('pinned') }}</span></div>
                <div class="v2-ctx-group-body"></div>
            </div>

            <div class="v2-ctx-group">
                <div class="v2-ctx-group-head"><span>{{ translate('sales') }}</span></div>

                <a class="v2-nav-item {{ Request::is('admin/orders/center*') ? 'v2-is-active' : '' }}" data-item="orders-center" href="{{ route('admin.orders.center') }}">
                    <span class="v2-nav-btn"><span class="v2-nav-label">مركز الطلبات</span></span>
                </a>

                <div class="v2-nav-item v2-has-children {{ Request::is('admin/orders/list*') ? 'v2-is-active' : '' }}" data-item="orders-all">
                    <a class="v2-nav-btn" href="{{ route('admin.orders.list', ['all']) }}">
                        <span class="v2-nav-label">{{ translate('orders') }}</span>
                    </a>
                    <div class="v2-nav-right">
                        <span data-v2-tag="primary" data-nav-workspace-count="orders" class="{{ empty($sidebarWorkspaceCategories['orders']['count']) ? 'd-none' : '' }}">{{ ($sidebarWorkspaceCategories['orders']['count'] ?? 0) > 99 ? '99+' : ($sidebarWorkspaceCategories['orders']['count'] ?? 0) }}</span>
                        <button class="v2-pin-btn" type="button" data-pin="orders-all" aria-label="Pin"></button>
                        <span class="v2-nav-chev" aria-label="{{ translate('toggle') }}">
                            <svg width="10" height="10" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 5 5 5-5 5"/></svg>
                        </span>
                    </div>
                </div>
                <div class="v2-nav-children v2-is-collapsed" data-children-for="orders-all">
                    <a class="v2-nav-child {{ Request::is('admin/orders/list/all') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.list', ['all']) }}">
                        <span class="v2-nav-child-dot" data-tone="primary"></span>
                        <span class="v2-nav-child-label">{{ translate('all') }}</span>
                        <span class="v2-nav-child-count" data-tone="primary">{{ $sidebarTotalOrders ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/orders/list/pending') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.list', ['pending']) }}">
                        <span class="v2-nav-child-dot" data-tone="primary"></span>
                        <span class="v2-nav-child-label">{{ translate('pending') }}</span>
                        <span class="v2-nav-child-count" data-tone="primary">{{ $sidebarOrderCounts['pending'] ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/orders/list/confirmed') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.list', ['confirmed']) }}">
                        <span class="v2-nav-child-dot" data-tone="success"></span>
                        <span class="v2-nav-child-label">{{ translate('confirmed') }}</span>
                        <span class="v2-nav-child-count" data-tone="success">{{ $sidebarOrderCounts['confirmed'] ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/orders/list/processing') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.list', ['processing']) }}">
                        <span class="v2-nav-child-dot" data-tone="warn"></span>
                        <span class="v2-nav-child-label">{{ translate('packaging') }}</span>
                        <span class="v2-nav-child-count" data-tone="warn">{{ $sidebarOrderCounts['processing'] ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/orders/list/out_for_delivery') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.list', ['out_for_delivery']) }}">
                        <span class="v2-nav-child-dot" data-tone="warn"></span>
                        <span class="v2-nav-child-label">{{ translate('out_for_delivery') }}</span>
                        <span class="v2-nav-child-count" data-tone="warn">{{ $sidebarOrderCounts['out_for_delivery'] ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/orders/list/delivered') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.list', ['delivered']) }}">
                        <span class="v2-nav-child-dot" data-tone="success"></span>
                        <span class="v2-nav-child-label">{{ translate('delivered') }}</span>
                        <span class="v2-nav-child-count" data-tone="success">{{ $sidebarOrderCounts['delivered'] ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/orders/list/returned') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.list', ['returned']) }}">
                        <span class="v2-nav-child-dot" data-tone="danger"></span>
                        <span class="v2-nav-child-label">{{ translate('returned') }}</span>
                        <span class="v2-nav-child-count" data-tone="danger">{{ $sidebarOrderCounts['returned'] ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/orders/list/failed') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.list', ['failed']) }}">
                        <span class="v2-nav-child-dot" data-tone="danger"></span>
                        <span class="v2-nav-child-label">{{ translate('failed_to_Deliver') }}</span>
                        <span class="v2-nav-child-count" data-tone="danger">{{ $sidebarOrderCounts['failed'] ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/orders/list/canceled') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.list', ['canceled']) }}">
                        <span class="v2-nav-child-dot" data-tone="neutral"></span>
                        <span class="v2-nav-child-label">{{ translate('canceled') }}</span>
                        <span class="v2-nav-child-count" data-tone="danger">{{ $sidebarOrderCounts['canceled'] ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/orders/package-refunds*') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.package-refunds.index') }}">
                        <span class="v2-nav-child-dot" data-tone="danger"></span><span class="v2-nav-child-label">طلبات رد المشتريات</span>
                        @if(\Illuminate\Support\Facades\Schema::hasTable('order_admin_purchase_refunds'))
                            <span class="v2-nav-child-count" data-tone="danger">{{ \App\Models\OrderAdminPurchaseRefund::where('reason','customer_canceled_waiting_for_package')->where('status','customer_requested')->count() }}</span>
                        @endif
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/orders/post-purchase-invoices*') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.post-purchase-invoices.index') }}">
                        <span class="v2-nav-child-dot" data-tone="primary"></span>
                        <span class="v2-nav-child-label">{{ translate('post_purchase_invoices') }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/orders/delivery-proofs') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.delivery-proofs') }}">
                        <span class="v2-nav-child-dot" data-tone="success"></span>
                        <span class="v2-nav-child-label">إثبات التسليم</span>
                    </a>
                </div>

                <div class="v2-nav-item v2-has-children {{ Request::is('admin/refund-section/refund/*') ? 'v2-is-active' : '' }}" data-item="refunds">
                    <a class="v2-nav-btn" href="{{ route('admin.refund-section.refund.list', ['pending']) }}">
                        <span class="v2-nav-label">{{ translate('refund_Requests') }}</span>
                    </a>
                    <div class="v2-nav-right">
                        @if (($sidebarRefundCounts['pending'] ?? 0) > 0)
                            <span data-v2-tag="warn">{{ $sidebarRefundCounts['pending'] }}</span>
                        @endif
                        <button class="v2-pin-btn" type="button" data-pin="refunds" aria-label="Pin"></button>
                        <span class="v2-nav-chev">
                            <svg width="10" height="10" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 5 5 5-5 5"/></svg>
                        </span>
                    </div>
                </div>
                <div class="v2-nav-children v2-is-collapsed" data-children-for="refunds">
                    <a class="v2-nav-child {{ Request::is('admin/refund-section/refund/list/pending') ? 'v2-is-on' : '' }}" href="{{ route('admin.refund-section.refund.list', ['pending']) }}">
                        <span class="v2-nav-child-dot" data-tone="primary"></span>
                        <span class="v2-nav-child-label">{{ translate('pending') }}</span>
                        <span class="v2-nav-child-count" data-tone="primary">{{ $sidebarRefundCounts['pending'] ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/refund-section/refund/list/approved') ? 'v2-is-on' : '' }}" href="{{ route('admin.refund-section.refund.list', ['approved']) }}">
                        <span class="v2-nav-child-dot" data-tone="success"></span>
                        <span class="v2-nav-child-label">{{ translate('approved') }}</span>
                        <span class="v2-nav-child-count" data-tone="success">{{ $sidebarRefundCounts['approved'] ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/refund-section/refund/list/refunded') ? 'v2-is-on' : '' }}" href="{{ route('admin.refund-section.refund.list', ['refunded']) }}">
                        <span class="v2-nav-child-dot" data-tone="success"></span>
                        <span class="v2-nav-child-label">{{ translate('refunded') }}</span>
                        <span class="v2-nav-child-count" data-tone="success">{{ $sidebarRefundCounts['refunded'] ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/refund-section/refund/list/rejected') ? 'v2-is-on' : '' }}" href="{{ route('admin.refund-section.refund.list', ['rejected']) }}">
                        <span class="v2-nav-child-dot" data-tone="danger"></span>
                        <span class="v2-nav-child-label">{{ translate('rejected') }}</span>
                        <span class="v2-nav-child-count" data-tone="danger">{{ $sidebarRefundCounts['rejected'] ?? 0 }}</span>
                    </a>
                    <a class="v2-nav-child {{ Request::is('admin/orders/post-payment-refunds') ? 'v2-is-on' : '' }}" href="{{ route('admin.orders.post-payment-refunds') }}">
                        <span class="v2-nav-child-dot" data-tone="warning"></span>
                        <span class="v2-nav-child-label">{{ translate('post_payment_refunds') }}</span>
                    </a>
                </div>
            </div>
        </div>
    @endif

    @if (Helpers::module_permission_check('catalog') || Helpers::module_permission_check('people') || Helpers::module_permission_check('orders') || Helpers::module_permission_check('business_settings'))
    <div class="v2-ctx-section" data-section="operations">
        <div class="v2-ctx-head"><div class="v2-ctx-title">{{ __('payments.operations') }}</div></div>
        <div class="v2-ctx-group v2-is-pinned" style="display:none;"><div class="v2-ctx-group-head"><span>{{ translate('pinned') }}</span></div><div class="v2-ctx-group-body"></div></div>
        <div class="v2-ctx-group">
            @if(Helpers::module_permission_check('business_settings'))
                <a class="v2-nav-item" data-item="seller-settlement-timing" href="{{ route('admin.business-settings.vendor-settings.index') }}#seller-settlement-hold-days">
                    <span class="v2-nav-btn"><span class="v2-nav-label">مدد تسوية مستحقات التجار</span></span>
                </a>
            @endif
            @if(Helpers::module_permission_check('people'))
                <a class="v2-nav-item" data-item="customer-withdrawal-rates" href="{{ route('admin.customer-withdrawals.index') }}">
                    <span class="v2-nav-btn"><span class="v2-nav-label">نسب ومدد صرف الأموال</span></span>
                </a>
            @endif
            @if (Helpers::module_permission_check('people'))
                <a class="v2-nav-item {{ request()->routeIs('admin.monthly-packages.*') ? 'v2-is-active' : '' }}" data-item="monthly-packages" href="{{ route('admin.monthly-packages.index') }}"><span class="v2-nav-btn"><span class="v2-nav-icon" aria-hidden="true"><i class="fi fi-rr-calendar"></i></span><span class="v2-nav-label">الباقات الشهرية</span></span></a>
                <a class="v2-nav-item {{ request()->routeIs('admin.customer.password-recovery.*') ? 'v2-is-active' : '' }}" data-item="customer-password-recovery" href="{{ route('admin.customer.password-recovery.index') }}"><span class="v2-nav-btn"><span class="v2-nav-icon" aria-hidden="true"><i class="fi fi-rr-key"></i></span><span class="v2-nav-label">استعادة كلمة مرور العملاء</span></span></a>
            @endif
            @if(Helpers::module_permission_check('catalog'))
            @foreach(['medical-devices' => ['medical_devices', 'fi-rr-heart-rate'], 'homepage-sections' => ['homepage_sections', 'fi-rr-apps']] as $entry => [$label, $icon])
            <a class="v2-nav-item {{ request()->routeIs('admin.operations.'.$entry.'.*') ? 'v2-is-active' : '' }}" data-item="{{ $entry }}" href="{{ route('admin.operations.'.$entry.'.index') }}"><span class="v2-nav-btn"><span class="v2-nav-icon" aria-hidden="true"><i class="fi {{ $icon }}"></i></span><span class="v2-nav-label">{{ __('operations.'.$label) }}</span></span></a>
            @endforeach
            @endif
            @if (Helpers::module_permission_check('orders'))
            <a class="v2-nav-item {{ request()->routeIs('admin.orders.logistics.*') ? 'v2-is-active' : '' }}" data-item="order-tracking" href="{{ route('admin.orders.logistics.index') }}"><span class="v2-nav-btn"><span class="v2-nav-icon" aria-hidden="true"><i class="fi fi-rr-route"></i></span><span class="v2-nav-label">تتبع الطلبات</span></span></a>
            @endif

            @if (Helpers::module_permission_check('people') || Helpers::module_permission_check('orders'))
            <a class="v2-nav-item {{ request()->routeIs('admin.payment-reviews.*') || request()->is('admin/workspace/reviews/payments') || request()->routeIs('admin.vendors.balance-funding-settings.*') ? 'v2-is-active' : '' }}" data-item="payment-reviews" href="{{ route('admin.payment-reviews.index') }}"><span class="v2-nav-btn"><span class="v2-nav-icon" aria-hidden="true"><i class="fi fi-rr-credit-card"></i></span><span class="v2-nav-label">{{ __('payments.title') }}</span></span><span class="v2-nav-right"><span data-v2-tag="primary" data-nav-workspace-count="payments" class="{{ empty($sidebarWorkspaceCategories['payments']['count']) ? 'd-none' : '' }}">{{ ($sidebarWorkspaceCategories['payments']['count'] ?? 0) > 99 ? '99+' : ($sidebarWorkspaceCategories['payments']['count'] ?? 0) }}</span></span></a>
            @if(\App\Utils\Helpers::module_permission_check('orders'))
                <a class="v2-nav-item {{ request()->routeIs('admin.guest-orders.*') ? 'v2-is-active' : '' }}" data-item="guest-orders" href="{{ route('admin.guest-orders.index') }}">
                    <span class="v2-nav-btn"><span class="v2-nav-label">طلبات زوار</span></span>
                </a>
            @endif
            @endif
            @if (Helpers::module_permission_check('business_settings'))
            <a class="v2-nav-item {{ request()->routeIs('admin.operations.shipping.*') ? 'v2-is-active' : '' }}" data-item="shipping-setup" href="{{ route('admin.operations.shipping.index') }}"><span class="v2-nav-btn"><span class="v2-nav-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h11v11H3zM14 10h4l3 4v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg></span><span class="v2-nav-label">{{ __('shipping-governorates.setup') }}</span></span></a>
            @endif
        </div>
    </div>
    @endif

    {{-- ================= AUCTION ================= --}}
    @if ($v2Auction)
        <div class="v2-ctx-section" data-section="auction">
            <div class="v2-ctx-head"><div class="v2-ctx-title">{{ translate('Auction_Management') }}</div></div>
            <div class="v2-ctx-group v2-is-pinned" style="display:none;">
                <div class="v2-ctx-group-head"><span>{{ translate('pinned') }}</span></div>
                <div class="v2-ctx-group-body"></div>
            </div>

            @foreach ($v2AuctionRoutes as $v2AuctionGroup)
                <div class="v2-ctx-group">
                    <div class="v2-ctx-group-head"><span>{{ translate($v2AuctionGroup['name']) }}</span></div>
                    @foreach ($v2AuctionGroup['routes'] as $v2AuctionRoute)
                        @php
                            $isInhouseRoute = str_contains($v2AuctionRoute['path'], '/inhouse/');
                            $badgeCounts = $isInhouseRoute ? ($sidebarAuctionInhouseCounts ?? []) : ($sidebarAuctionVendorCounts ?? []);
                            $badgeData = function_exists('getAuctionSidebarBadgeData') ? getAuctionSidebarBadgeData($v2AuctionRoute['path'], $badgeCounts) : ['is_status_route' => false, 'badge_class' => 'neutral', 'count' => 0];
                            $v2Slug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($v2AuctionGroup['name'] . '-' . $v2AuctionRoute['name']));
                            $v2Tone = match($badgeData['badge_class'] ?? 'neutral') {
                                'success' => 'success',
                                'danger' => 'danger',
                                'warning' => 'warn',
                                'info' => 'info',
                                default => 'primary',
                            };
                        @endphp
                        <a class="v2-nav-item {{ strstr(Request::url(), $v2AuctionRoute['path']) ? 'v2-is-active' : '' }}" data-item="auction-{{ $v2Slug }}" href="{{ $v2AuctionRoute['url'] }}">
                            <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate($v2AuctionRoute['name']) }}</span></span>
                            <div class="v2-nav-right">
                                @if ($badgeData['is_status_route'])
                                    <span data-v2-tag="{{ $v2Tone }}">{{ $badgeData['count'] }}</span>
                                @endif
                                <button class="v2-pin-btn" type="button" data-pin="auction-{{ $v2Slug }}" aria-label="Pin"></button>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endforeach

            <div class="v2-ctx-group">
                <div class="v2-ctx-group-head"><span>{{ translate('Finance') }}</span></div>
                <a class="v2-nav-item {{ Request::is('admin/auction/auction-withdraw*') ? 'v2-is-active' : '' }}" data-item="auction-withdraw" href="{{ route('admin.auction.auction-withdraw') }}">
                    <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Withdraw_List') }}</span></span>
                    <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="auction-withdraw" aria-label="Pin"></button></div>
                </a>
            </div>
        </div>
    @endif

    {{-- ================= MARKETING ================= --}}

    {{-- ================= PEOPLE ================= --}}
    @if (Helpers::module_permission_check('people'))
        <div class="v2-ctx-section" data-section="customers">
            <div class="v2-ctx-head"><div class="v2-ctx-title">{{ translate('customers') }}</div></div>
            <div class="v2-ctx-group">
                @foreach(['list'=>['قائمة العملاء','user'],'payments'=>['مدفوعات','wallet'],'debts'=>['الديون','receipt'],'subscriptions'=>['الاشتراكات','calendar']] as $customerPage=>$customerNav)
                @if($customerPage==='list' || \App\Services\CustomerAccountAccess::allows('financial_view'))
                <a class="v2-nav-item {{ request()->is('admin/customer/accounts/'.$customerPage) ? 'v2-is-active' : '' }}" data-item="customer-accounts-{{ $customerPage }}" href="{{ route('admin.customer.accounts.index',array_filter(['page'=>$customerPage,'customer_id'=>request()->is('admin/customer/accounts*')?request('customer_id'):(request()->is('admin/customer/view/*')?request()->route('user_id'):null)])) }}"><span class="v2-nav-btn"><span class="v2-nav-icon" aria-hidden="true"><i class="fi fi-rr-{{ $customerNav[1] }}"></i></span><span class="v2-nav-label">{{ $customerNav[0] }}</span></span></a>
                @endif
                @endforeach
            </div>
        </div>
    @endif
    @if (Helpers::module_permission_check('people'))
        <div class="v2-ctx-section" data-section="vendors">
            <div class="v2-ctx-head"><div class="v2-ctx-title">التجار</div></div>
            <div class="v2-ctx-group">
                @foreach(['list'=>['قائمة التجار','shop'],'payments'=>['مدفوعات','wallet'],'debts'=>['الديون','receipt'],'subscriptions'=>['الاشتراكات','calendar']] as $vendorPage=>$vendorNav)
                @if($vendorPage==='list' || (int)auth('admin')->user()->admin_role_id===1 || Helpers::module_permission_check('vendor_financial_view') || Helpers::module_permission_check('vendor_financial_manage'))
                <a class="v2-nav-item {{ request()->is('admin/vendors/accounts/'.$vendorPage) ? 'v2-is-active' : '' }}" data-item="vendor-accounts-{{ $vendorPage }}" href="{{ route('admin.vendors.accounts.index',array_filter(['page'=>$vendorPage,'seller_id'=>request()->is('admin/vendors/accounts*')?request('seller_id'):null])) }}"><span class="v2-nav-btn"><span class="v2-nav-icon" aria-hidden="true"><i class="fi fi-rr-{{ $vendorNav[1] }}"></i></span><span class="v2-nav-label">{{ $vendorNav[0] }}</span></span></a>
                @endif
                @endforeach
            </div>
        </div>
    @endif
    @if (Helpers::module_permission_check('people'))
        <div class="v2-ctx-section" data-section="people">
            <div class="v2-ctx-head"><div class="v2-ctx-title">{{ translate('people') }}</div></div>
            <div class="v2-ctx-group">
                <a class="v2-nav-item {{ request()->routeIs('admin.account-directory.*') ? 'v2-is-active' : '' }}" data-item="all-accounts" href="{{ route('admin.account-directory.index') }}">
                    <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('all_customer_vendor_accounts') }}</span></span>
                </a>
                <a class="v2-nav-item {{ request()->boolean('guest_customers') ? 'v2-is-active' : '' }}" data-item="guest-customers" href="{{ route('admin.account-directory.index', ['guest_customers' => 1]) }}">
                    <span class="v2-nav-btn"><span class="v2-nav-label">عملاء زوار</span></span>
                </a>
            </div>
            <div class="v2-ctx-group v2-is-pinned" style="display:none;">
                <div class="v2-ctx-group-head"><span>{{ translate('pinned') }}</span></div>
                <div class="v2-ctx-group-body"></div>
            </div>

            @if (Helpers::module_permission_check('people'))
                <div class="v2-ctx-group">
                    <div class="v2-ctx-group-head"><span>{{ translate('vendors') }}</span></div>
<a class="v2-nav-item {{ (Request::is('admin/vendors/list') || Request::is('admin/vendors/view*')) ? 'v2-is-active' : '' }}" data-item="vendor-list" href="{{ route('admin.vendors.vendor-list') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('vendor_List') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="vendor-list" aria-label="Pin"></button></div>
                    </a>
<a class="v2-nav-item {{ (Request::is('admin/vendors/withdraw-list') || Request::is('admin/vendors/withdraw-view/*')) ? 'v2-is-active' : '' }}" data-item="vendor-withdraw" href="{{ route('admin.vendors.withdraw_list') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('withdraws') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="vendor-withdraw" aria-label="Pin"></button></div>
                    </a>
                    <a class="v2-nav-item {{ Request::is('admin/vendors/withdraw-method/*') ? 'v2-is-active' : '' }}" data-item="withdraw-methods" href="{{ route('admin.vendors.withdraw-method.list') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('withdrawal_Methods') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="withdraw-methods" aria-label="Pin"></button></div>
                    </a>
                </div>

                {{-- Delivery-man navigation is intentionally removed; historical records remain in the database. --}}


            @endif

            @if (Helpers::module_permission_check('people'))
                <div class="v2-ctx-group">
                    <div class="v2-ctx-group-head"><span>{{ translate('support') }}</span></div>
                    <a class="v2-nav-item {{ Request::is('admin/workspace/communication*') ? 'v2-is-active' : '' }}" data-item="inbox" href="{{ route('admin.workspace.communication') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('workspace_communication_center') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="inbox" aria-label="Pin"></button></div>
                    </a>
                    <a class="v2-nav-item {{ Request::is('admin/support-ticket*') ? 'v2-is-active' : '' }}" data-item="support-ticket" href="{{ route('admin.support-ticket.view') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('support_Ticket') }}</span></span>
                        <div class="v2-nav-right">
                            <button class="v2-pin-btn" type="button" data-pin="support-ticket" aria-label="Pin"></button>
                        </div>
                    </a>
                    <a class="v2-nav-item {{ Request::is('admin/vendor-support*') ? 'v2-is-active' : '' }}" data-item="vendor-support" href="{{ route('admin.vendor-support.index') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('vendor_support_conversations') }}</span></span>
                    </a>
                    <a class="v2-nav-item {{ Request::is('admin/contact*') ? 'v2-is-active' : '' }}" data-item="contact-form" href="{{ route('admin.contact.list') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('contact_form') }}</span></span>
                        <div class="v2-nav-right">
                            @if (($sidebarUnseenMessages ?? 0) > 0)
                                <span data-v2-tag="danger">{{ $sidebarUnseenMessages }}</span>
                            @endif
                            <button class="v2-pin-btn" type="button" data-pin="contact-form" aria-label="Pin"></button>
                        </div>
                    </a>
                </div>
            @endif
        </div>
    @endif

    {{-- ================= REPORTS ================= --}}
    @if (Helpers::module_permission_check('reports'))
        <div class="v2-ctx-section" data-section="reports">
            <div class="v2-ctx-head"><div class="v2-ctx-title">{{ __('financials.title') }}</div></div>
            <div class="v2-ctx-group">
                
                @foreach (['company', 'customers'] as $financialPage)
                    <a class="v2-nav-item {{ Request::routeIs('admin.report.financials.'.$financialPage) || ($financialPage === 'company' && Request::routeIs('admin.report.financials.entry')) ? 'v2-is-active' : '' }}" data-item="financials-{{ $financialPage }}" href="{{ route('admin.report.financials.'.$financialPage) }}">
                        <span class="v2-nav-btn"><i class="fi fi-rr-{{ $financialPage === 'company' ? 'chart-histogram' : 'wallet' }}" aria-hidden="true"></i><span class="v2-nav-label">{{ __('financials.'.$financialPage) }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="financials-{{ $financialPage }}" aria-label="{{ translate('pinned') }}"></button></div>
                    </a>
                @endforeach
            </div>
            <div class="v2-ctx-group v2-is-pinned" style="display:none;">
                <div class="v2-ctx-group-head"><span>{{ translate('pinned') }}</span></div>
                <div class="v2-ctx-group-body"></div>
            </div>

            @if (false)
                <div class="v2-ctx-group">
                    <div class="v2-ctx-group-head"><span>{{ translate('tax') }}</span></div>
                    @foreach ($v2TaxReportRoutes as $v2TaxReport)
                        @php
                            $isActive = strstr(Request::url(), $v2TaxReport['path']);
                            if (!$isActive && isset($v2TaxReport['sub_routes'])) {
                                foreach ($v2TaxReport['sub_routes'] as $sub) {
                                    if (strstr(Request::url(), $sub['path'])) { $isActive = true; break; }
                                }
                            }
                            $v2TaxSlug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower('tax-' . $v2TaxReport['name']));
                        @endphp
                        <a class="v2-nav-item {{ $isActive ? 'v2-is-active' : '' }}" data-item="{{ $v2TaxSlug }}" href="{{ $v2TaxReport['url'] }}">
                            <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate($v2TaxReport['name']) }}</span></span>
                            <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="{{ $v2TaxSlug }}" aria-label="Pin"></button></div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- ================= SETTINGS ================= --}}
    @if (Helpers::module_permission_check('business_settings') || Helpers::module_permission_check('system_settings') || Helpers::module_permission_check('3rd_party_setup') || Helpers::module_permission_check('themes_and_addons'))
        <div class="v2-ctx-section" data-section="settings">
            <div class="v2-ctx-head"><div class="v2-ctx-title">{{ translate('settings') }}</div></div>
            <div class="v2-ctx-group v2-is-pinned" style="display:none;">
                <div class="v2-ctx-group-head"><span>{{ translate('pinned') }}</span></div>
                <div class="v2-ctx-group-body"></div>
            </div>

            @if (Helpers::module_permission_check('business_settings'))
                <div class="v2-ctx-group">
                    <div class="v2-ctx-group-head"><span>{{ translate('Business_Settings') }}</span></div>
                    <a class="v2-nav-item {{ (Request::is('admin/business-settings/web-config') || Request::is('admin/business-settings/refund-setup') || Request::is('admin/business-settings/website-setup') || Request::is('admin/business-settings/product-settings') || Request::is('admin/business-settings/payment-method/payment-option') || Request::is('admin/business-settings/vendor-settings') || Request::is('admin/business-settings/customer-settings') || Request::is('admin/business-settings/order-settings/index') || Request::is('admin/business-settings/invoice-settings') || Request::is('admin/business-settings/delivery-zone')) ? 'v2-is-active' : '' }}" data-item="business-setup" href="{{ route('admin.business-settings.web-config.index') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Business_Setup') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="business-setup" aria-label="Pin"></button></div>
                    </a>


                    @if ($v2Tax && count($v2TaxRoutes))
                        @php
                            $v2TaxActive = false;
                            foreach ($v2TaxRoutes as $v2TaxRow) {
                                if (strstr(Request::url(), $v2TaxRow['path'])) { $v2TaxActive = true; break; }
                            }
                        @endphp
                        <div class="v2-nav-item v2-has-children {{ $v2TaxActive ? 'v2-is-active' : '' }}" data-item="vat-setup">
                            <a class="v2-nav-btn" href="{{ $v2TaxRoutes[0]['url'] }}">
                                <span class="v2-nav-label">{{ translate('vat_tax_setup') }}</span>
                            </a>
                            <div class="v2-nav-right">
                                <button class="v2-pin-btn" type="button" data-pin="vat-setup" aria-label="Pin"></button>
                                <span class="v2-nav-chev">
                                    <svg width="10" height="10" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 5 5 5-5 5"/></svg>
                                </span>
                            </div>
                        </div>
                        <div class="v2-nav-children v2-is-collapsed" data-children-for="vat-setup">
                            @foreach ($v2TaxRoutes as $v2TaxRow)
                                <a class="v2-nav-child {{ strstr(Request::url(), $v2TaxRow['path']) ? 'v2-is-on' : '' }}" href="{{ $v2TaxRow['url'] }}">
                                    <span class="v2-nav-child-dot" data-tone="primary"></span>
                                    <span class="v2-nav-child-label">{{ translate($v2TaxRow['name']) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <a class="v2-nav-item {{ Request::is('admin/pages-and-media/vendor-registration-settings/*') ? 'v2-is-active' : '' }}" data-item="vendor-registration" href="{{ route('admin.pages-and-media.vendor-registration-settings.index') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('vendor_Registration') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="vendor-registration" aria-label="Pin"></button></div>
                    </a>
                    <a class="v2-nav-item {{ (Request::is('admin/pages-and-media/list') || Request::is('admin/pages-and-media/page*') || Request::is('admin/pages-and-media/privacy-policy') || Request::is('admin/pages-and-media/about-us') || Request::is('admin/helpTopic/index') || Request::is('admin/pages-and-media/features-section') || Request::is('admin/pages-and-media/company-reliability')) ? 'v2-is-active' : '' }}" data-item="business-pages" href="{{ route('admin.pages-and-media.list') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('business_Pages') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="business-pages" aria-label="Pin"></button></div>
                    </a>
                    <a class="v2-nav-item {{ Request::is('admin/pages-and-media/social-media') ? 'v2-is-active' : '' }}" data-item="social-media-links" href="{{ route('admin.pages-and-media.social-media') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('social_Media_Links') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="social-media-links" aria-label="Pin"></button></div>
                    </a>
                    <a class="v2-nav-item {{ (Request::is('admin/seo-settings/web-master-tool') || Request::is('admin/seo-settings/robot-txt') || Request::is('admin/seo-settings/sitemap') || Request::is('admin/seo-settings/robots-meta-content*') || Request::is('admin/seo-settings/error-logs/index')) ? 'v2-is-active' : '' }}" data-item="seo" href="{{ route('admin.seo-settings.web-master-tool') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('SEO_Settings') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="seo" aria-label="Pin"></button></div>
                    </a>
                </div>
            @endif

            @if (Helpers::module_permission_check('system_settings'))
                <div class="v2-ctx-group">
                    <div class="v2-ctx-group-head"><span>{{ translate('System_Settings') }}</span></div>
                    <a class="v2-nav-item {{ (Request::is('admin/system-setup/environment-setup') || Request::is('admin/system-setup/app-settings') || Request::is('admin/system-setup/app-deep-link') || Request::is('admin/system-setup/sitemap') || Request::is('admin/system-setup/currency/view') || Request::is('admin/system-setup/web-config/db-index') || Request::is('admin/system-setup/language*') || Request::is('admin/system-setup/software-update') || Request::is('admin/system-setup/web-config/app-settings') || Request::is('admin/system-setup/invoice-settings/') || Request::is('admin/system-setup/db-index')) ? 'v2-is-active' : '' }}" data-item="system-setup" href="{{ route('admin.system-setup.environment-setup') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('System_Setup') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="system-setup" aria-label="Pin"></button></div>
                    </a>
                    <a class="v2-nav-item {{ (Request::is('admin/system-setup/login-settings/login-url-setup') || Request::is('admin/system-setup/login-settings/customer-login-setup') || Request::is('admin/system-setup/login-settings/otp-setup')) ? 'v2-is-active' : '' }}" data-item="login-settings" href="{{ route('admin.system-setup.login-settings.customer-login-setup') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Login_Settings') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="login-settings" aria-label="Pin"></button></div>
                    </a>
                    <a class="v2-nav-item {{ Request::is('admin/system-setup/email-templates/*') ? 'v2-is-active' : '' }}" data-item="email-template" href="{{ route('admin.system-setup.email-templates.view', ['admin', EmailTemplateKey::ADMIN_EMAIL_LIST[0]]) }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Email_Template') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="email-template" aria-label="Pin"></button></div>
                    </a>
                    <a class="v2-nav-item {{ Request::is('admin/system-setup/file-manager*') ? 'v2-is-active' : '' }}" data-item="file-gallery" href="{{ route('admin.system-setup.file-manager.index') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Gallery') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="file-gallery" aria-label="Pin"></button></div>
                    </a>
                </div>
            @endif

            @if (Helpers::module_permission_check('3rd_party_setup'))
                <div class="v2-ctx-group">
                    <div class="v2-ctx-group-head"><span>{{ translate('3rd_Party_Setup') }}</span></div>
                    <a class="v2-nav-item {{ (Request::is('admin/third-party/payment-method') || Request::is('admin/third-party/offline-payment-method/index') || Request::is('admin/third-party/offline-payment-method*')) ? 'v2-is-active' : '' }}" data-item="payment-methods" href="{{ route('admin.third-party.payment-method.index') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Payment_Methods') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="payment-methods" aria-label="Pin"></button></div>
                    </a>
                    <a class="v2-nav-item {{ (Request::is('admin/third-party/firebase-configuration/setup') || Request::is('admin/third-party/firebase-configuration/authentication')) ? 'v2-is-active' : '' }}" data-item="firebase" href="{{ route('admin.third-party.firebase-configuration.setup') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Firebase') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="firebase" aria-label="Pin"></button></div>
                    </a>
                    <a class="v2-nav-item {{ Request::is('admin/third-party/analytics-index') ? 'v2-is-active' : '' }}" data-item="marketing-tools" href="{{ route('admin.third-party.analytics-index') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Marketing_Tools') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="marketing-tools" aria-label="Pin"></button></div>
                    </a>
                    @if ($v2Ai)
                        <a class="v2-nav-item {{ (Request::is('admin/third-party/ai-setting') || Request::is('admin/third-party/ai-setting/vendors-usage-limits') || Request::is('admin/third-party/ai-setting/customers-usage-limits')) ? 'v2-is-active' : '' }}" data-item="ai-setup" href="{{ route('admin.third-party.ai-setting.index') }}">
                            <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('AI_Setup') }}</span></span>
                            <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="ai-setup" aria-label="Pin"></button></div>
                        </a>
                    @endif
                    <a class="v2-nav-item {{ (Request::is('admin/third-party/mail') || Request::is('admin/third-party/sms-module') || Request::is('admin/third-party/social-login/view') || Request::is('admin/third-party/social-media-chat/view') || Request::is('admin/third-party/storage-connection-settings/index') || Request::is('admin/third-party/map-api')) ? 'v2-is-active' : '' }}" data-item="other-config" href="{{ route('admin.third-party.social-login.view') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Other_Configuration') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="other-config" aria-label="Pin"></button></div>
                    </a>
                </div>
            @endif

            @if (count($v2ThemeRoutes) > 0)
                @php
                    $v2ThemeEnabledCount = 0;
                    foreach (($v2ThemeRoutes['route_list'] ?? []) as $themeRoute) {
                        if (isset($themeRoute['module_permission']) && Helpers::module_permission_check($themeRoute['module_permission'])) {
                            $v2ThemeEnabledCount++;
                        }
                    }
                @endphp
                @if ($v2ThemeEnabledCount > 0)
                    <div class="v2-ctx-group">
                        <div class="v2-ctx-group-head"><span>{{ $v2ThemeRoutes['name'] ?? '' }} {{ translate('Menu') }}</span></div>
                        @foreach ($v2ThemeRoutes['route_list'] as $themeRoute)
                            @if (isset($themeRoute['module_permission']) && Helpers::module_permission_check($themeRoute['module_permission']))
                                @php
                                    $v2ThemeSlug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower('theme-' . $themeRoute['name']));
                                    $v2ThemeActive = Request::is($themeRoute['path']) || Request::is($themeRoute['path'].'*');
                                    if (!$v2ThemeActive && !empty($themeRoute['route_list'])) {
                                        foreach ($themeRoute['route_list'] as $sub) {
                                            if (Request::is($sub['path']) || Request::is($sub['path'].'*')) { $v2ThemeActive = true; break; }
                                        }
                                    }
                                @endphp
                                @if (!empty($themeRoute['route_list']))
                                    @foreach ($themeRoute['route_list'] as $sub)
                                        @php $v2ThemeSubSlug = $v2ThemeSlug . '-' . preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($sub['name'])); @endphp
                                        <a class="v2-nav-item {{ (Request::is($sub['path']) || Request::is($sub['path'].'*')) ? 'v2-is-active' : '' }}" data-item="{{ $v2ThemeSubSlug }}" href="{{ $sub['url'] }}">
                                            <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate($sub['name']) }}</span></span>
                                            <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="{{ $v2ThemeSubSlug }}" aria-label="Pin"></button></div>
                                        </a>
                                    @endforeach
                                @else
                                    <a class="v2-nav-item {{ $v2ThemeActive ? 'v2-is-active' : '' }}" data-item="{{ $v2ThemeSlug }}" href="{{ $themeRoute['url'] }}">
                                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate($themeRoute['name']) }}</span></span>
                                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="{{ $v2ThemeSlug }}" aria-label="Pin"></button></div>
                                    </a>
                                @endif
                            @endif
                        @endforeach
                    </div>
                @endif
            @endif

            @if (Helpers::module_permission_check('themes_and_addons'))
                <div class="v2-ctx-group">
                    <div class="v2-ctx-group-head"><span>{{ translate('Themes_&_Addons') }}</span></div>
                    <a class="v2-nav-item {{ Request::is('admin/system-setup/theme/setup') ? 'v2-is-active' : '' }}" data-item="theme-setup" href="{{ route('admin.system-setup.theme.setup') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Theme_Setup') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="theme-setup" aria-label="Pin"></button></div>
                    </a>
                    <a class="v2-nav-item {{ Request::is('admin/system-setup/addon') ? 'v2-is-active' : '' }}" data-item="system-addons" href="{{ route('admin.system-setup.addon.index') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('System_Addons') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="system-addons" aria-label="Pin"></button></div>
                    </a>
                    <a class="v2-nav-item {{ Request::is('admin/system-setup/addon-activation') ? 'v2-is-active' : '' }}" data-item="addon-activation" href="{{ route('admin.system-setup.addon-activation.index') }}">
                        <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate('Addon_Activation') }}</span></span>
                        <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="addon-activation" aria-label="Pin"></button></div>
                    </a>

                    @if (count($v2AddonAdminRoutes) > 0)
                        @foreach ($v2AddonAdminRoutes as $routes)
                            @foreach ($routes as $route)
                                @php $v2AddonSlug = 'addon-' . preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($route['name'])); @endphp
                                <a class="v2-nav-item {{ strstr(Request::url(), $route['path']) ? 'v2-is-active' : '' }}" data-item="{{ $v2AddonSlug }}" href="{{ $route['url'] }}">
                                    <span class="v2-nav-btn"><span class="v2-nav-label">{{ translate($route['name']) }}</span></span>
                                    <div class="v2-nav-right"><button class="v2-pin-btn" type="button" data-pin="{{ $v2AddonSlug }}" aria-label="Pin"></button></div>
                                </a>
                            @endforeach
                        @endforeach
                    @endif
                </div>
            @endif
        </div>
    @endif

    </div>{{-- /.v2-ctx-scroll --}}

</aside>

<aside class="v2-rail-flyout" id="v2-rail-flyout" aria-hidden="true"></aside>

{{-- Command palette (⌘K) --}}
<div class="v2-palette-scrim" id="v2-palette-scrim" hidden>
    <div class="v2-palette">
        <div class="v2-palette-head">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="9" cy="9" r="5"/><path d="m13 13 3.5 3.5"/>
            </svg>
            <input id="v2-palette-input" type="text" placeholder="{{ translate('Jump_to_anything') }} — {{ translate('orders_vendors_settings') }}…" autocomplete="off"/>
            <span class="v2-kbd">esc</span>
        </div>
        <div class="v2-palette-list" id="v2-palette-list"></div>
        <div class="v2-palette-foot">
            <span class="v2-palette-hint"><span class="v2-kbd">↑</span><span class="v2-kbd">↓</span> {{ translate('navigate') }}</span>
            <span class="v2-palette-hint"><span class="v2-kbd">↵</span> {{ translate('open') }}</span>
            <span class="v2-palette-hint"><span class="v2-kbd">esc</span> {{ translate('close') }}</span>
        </div>
    </div>
</div>
