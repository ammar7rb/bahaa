@php
    $isSuper = (int) auth('admin')->user()->admin_role_id === 1;
    $shortcuts = [
        ['group'=>'operations','permission'=>'people','label'=>'customers','icon'=>'fi fi-sr-users','route'=>'admin.customer.list','params'=>[]],
        ['group'=>'operations','permission'=>'people','label'=>'vendors','icon'=>'fi fi-sr-shop','route'=>'admin.vendors.vendor-list','params'=>[]],
        ['group'=>'operations','permission'=>'orders','label'=>'sales','icon'=>'fi fi-sr-shopping-cart','route'=>'admin.orders.list','params'=>['all']],
        ['group'=>'operations','permission'=>'orders','label'=>'admin_order_gate','icon'=>'fi fi-sr-shield-check','route'=>'admin.orders.admin-gate.index','params'=>[],'count'=>$data['pending_admin_order_gate_count']??0],
        ['group'=>'operations','permission'=>'orders','label'=>'shipping','icon'=>'fi fi-sr-truck-moving','route'=>'admin.orders.logistics.index','params'=>[]],
        ['group'=>'operations','permission'=>'orders','label'=>'refunds','icon'=>'fi fi-sr-refund-alt','route'=>'admin.refund-section.refund.list','params'=>['pending']],
        ['group'=>'operations','permission'=>'people','label'=>'communication_center','icon'=>'fi fi-sr-comment-alt','route'=>'admin.support-ticket.view','params'=>[]],
        ['group'=>'operations','permission'=>'people','label'=>'account_activation_center','icon'=>'fi fi-sr-user-check','route'=>'admin.activation-center.index','params'=>[],'count'=>$data['pending_activation_count']??0],
        ['group'=>'operations','permission'=>'people','label'=>'payment_review_center','icon'=>'fi fi-sr-document-signed','route'=>'admin.payment-reviews.index','params'=>[]],
        ['group'=>'growth','permission'=>'people','label'=>'vendor_packages','icon'=>'fi fi-sr-box-open','route'=>'admin.vendors.packages.index','params'=>[]],
        ['group'=>'growth','permission'=>'catalog','label'=>'advertisements','icon'=>'fi fi-sr-megaphone','route'=>'admin.products.list','params'=>['type' => 'vendor', 'request_status' => 0]],
        ['group'=>'growth','permission'=>'reports','label'=>'finances','icon'=>'fi fi-sr-calculator','route'=>'admin.report.earning','params'=>[]],
        ['group'=>'growth','permission'=>'reports','label'=>'insurance','icon'=>'fi fi-sr-shield-check','route'=>'admin.insurance-center.index','params'=>[]],
        ['group'=>'growth','permission'=>'marketing','label'=>'banner_Setup','icon'=>'fi fi-sr-pennant','route'=>'admin.banner.list','params'=>[]],
        ['group'=>'configuration','permission'=>'themes_and_addons','label'=>'Homepage_Section_Builder','icon'=>'fi fi-sr-layout-fluid','route'=>'admin.system-setup.theme.homepage-builder.index','params'=>[]],
        ['group'=>'configuration','permission'=>'business_settings','label'=>'display_priority_settings','icon'=>'fi fi-sr-sort-alt','route'=>'admin.business-settings.priority-setup.index','params'=>[]],
    ];
    $shortcutGroups = [
        'operations' => ['label' => 'daily_operations', 'icon' => 'fi fi-sr-apps'],
        'growth' => ['label' => 'growth_and_finance', 'icon' => 'fi fi-sr-chart-histogram'],
        'configuration' => ['label' => 'display_and_configuration', 'icon' => 'fi fi-sr-settings-sliders'],
    ];
@endphp
<div class="card mb-3 dashboard-panel dashboard-shortcuts-panel">
    <div class="card-header dashboard-panel-header">
        <div>
            <h4 class="mb-1 fw-bold fs-16">{{ translate('operational_shortcuts') }}</h4>
            <p class="mb-0 text-muted fs-12">{{ translate('reach_the_most_important_admin_tools_quickly') }}</p>
        </div>
        <button type="button" class="dashboard-collapse-toggle" data-dashboard-shortcuts-toggle
                aria-controls="dashboard-shortcuts-content" aria-expanded="true"
                data-show-label="{{ translate('show_shortcuts') }}" data-hide-label="{{ translate('hide_shortcuts') }}">
            <span data-shortcuts-toggle-label>{{ translate('hide_shortcuts') }}</span>
            <i class="fi fi-rr-angle-small-up" data-shortcuts-toggle-icon></i>
        </button>
    </div>
    <div class="card-body" id="dashboard-shortcuts-content">
        @foreach($shortcutGroups as $groupKey => $group)
            @php($groupShortcuts = collect($shortcuts)->where('group', $groupKey))
            <div class="dashboard-shortcut-group {{ !$loop->last ? 'mb-4' : '' }}">
                <div class="dashboard-shortcut-group-title"><i class="{{ $group['icon'] }}"></i><span>{{ translate($group['label']) }}</span></div>
                <div class="dashboard-shortcut-grid">
                    @foreach($groupShortcuts as $shortcut)
                        @if(($isSuper || \App\Utils\Helpers::module_permission_check($shortcut['permission'])) && Route::has($shortcut['route']))
                            <a class="dashboard-shortcut" href="{{ route($shortcut['route'], $shortcut['params']) }}">
                                <span class="dashboard-shortcut-icon"><i class="{{ $shortcut['icon'] }}"></i></span>
                                <span class="dashboard-shortcut-label">{{ translate($shortcut['label']) }}</span>
                                @if(($shortcut['count']??0)>0)<span class="dashboard-shortcut-count">{{ $shortcut['count'] }}</span>@endif
                                <i class="fi fi-rr-angle-small-left dashboard-shortcut-arrow"></i>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
        @if($isSuper || \App\Utils\Helpers::module_permission_check('reports'))
            <div class="dashboard-shortcut-group mt-4">
                <div class="dashboard-shortcut-group-title"><i class="fi fi-sr-print"></i><span>{{ translate('printing_tools') }}</span></div>
                <div class="dashboard-shortcut-grid">
                    <a class="dashboard-shortcut dashboard-shortcut-muted" target="_blank" href="{{ route('admin.report.operational-print', ['section'=>'finances']) }}">
                        <span class="dashboard-shortcut-icon"><i class="fi fi-sr-print"></i></span><span class="dashboard-shortcut-label">{{ translate('print_center') }}</span><i class="fi fi-rr-angle-small-left dashboard-shortcut-arrow"></i>
                    </a>
                    <button class="dashboard-shortcut dashboard-shortcut-muted border-0 text-start" type="button" onclick="window.baFullPagePrint()" title="طباعة محتوى الصفحة والسجلات بالكامل" data-smart-print-trigger>
                        <span class="dashboard-shortcut-icon"><i class="fi fi-sr-print"></i></span><span class="dashboard-shortcut-label">{{ translate('print_this_page') }}</span><i class="fi fi-rr-angle-small-left dashboard-shortcut-arrow"></i>
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
