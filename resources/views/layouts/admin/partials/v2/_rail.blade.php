@php
    use App\Utils\Helpers;
    $v2HasHome     = true;
    $v2HasCatalog  = Helpers::module_permission_check('catalog');
    $v2HasOrders   = Helpers::module_permission_check('orders');
    $v2HasAuction  = function_exists('getCheckAddonPublishedStatus') && getCheckAddonPublishedStatus(moduleName: 'Auction');
    $v2HasPeople   = Helpers::module_permission_check('people');
    $v2HasReports  = Helpers::module_permission_check('reports');
    $v2HasSettings = Helpers::module_permission_check('business_settings')
                     || Helpers::module_permission_check('developer_configuration');
@endphp

<aside class="v2-rail">
    <div class="v2-rail-items">
        @if ($v2HasHome)
            <button class="v2-rail-btn" type="button" data-section="home" data-label="{{ translate('home') }}" aria-label="{{ translate('home') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-8.5Z"/>
                </svg>
            </button>
        @endif

        @if ($v2HasCatalog)
            <button class="v2-rail-btn" type="button" data-section="catalog" data-label="{{ translate('products') }}" aria-label="{{ translate('products') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9Z"/>
                    <path d="M3 7.5 12 12l9-4.5M12 12v9"/>
                </svg>
            </button>
        @endif

        @if ($v2HasOrders)
            <button class="v2-rail-btn" type="button" data-section="orders" data-label="{{ translate('orders') }}" aria-label="{{ translate('orders') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 8h12l-1 11.2a1 1 0 0 1-1 .8H8a1 1 0 0 1-1-.8L6 8Z"/>
                    <path d="M9 8V6a3 3 0 0 1 6 0v2"/>
                </svg>
            </button>
        @endif

        @if ($v2HasCatalog || $v2HasOrders || $v2HasPeople || Helpers::module_permission_check('business_settings'))
        <button class="v2-rail-btn" type="button" data-section="operations" data-label="{{ __('payments.operations') }}" aria-label="{{ __('payments.operations') }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/></svg>
        </button>
        @endif

        @if ($v2HasAuction)
            <button class="v2-rail-btn" type="button" data-section="auction" data-label="{{ translate('Auction_Management') }}" aria-label="{{ translate('Auction_Management') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m14 5 5 5-4 4-5-5 4-4Z"/>
                    <path d="m9 10-6 6 2 2 6-6"/>
                    <path d="M4 20h10"/>
                </svg>
            </button>
        @endif


        @if ($v2HasPeople)
            <button class="v2-rail-btn" type="button" data-section="customers" data-label="{{ translate('customers') }}" aria-label="{{ translate('customers') }}"><i class="fi fi-rr-user" aria-hidden="true"></i></button>
            <button class="v2-rail-btn" type="button" data-section="vendors" data-label="التجار" aria-label="التجار"><i class="fi fi-rr-shop" aria-hidden="true"></i></button>
            <button class="v2-rail-btn" type="button" data-section="people" data-label="{{ translate('people') }}" aria-label="{{ translate('people') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="9" cy="9" r="3"/>
                    <path d="M3 20c0-3 2.5-5 6-5s6 2 6 5"/>
                    <circle cx="17" cy="10" r="2.5"/>
                    <path d="M15 20c.4-2.2 2-3.5 4-3.5s3.6 1.3 4 3.5"/>
                </svg>
            </button>
        @endif

        @if ($v2HasReports)
            <a class="v2-rail-btn" href="{{ route('admin.report.financials.index') }}" data-section="reports" data-label="{{ __('financials.title') }}" aria-label="{{ __('financials.title') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 20V11M11 20V5M17 20v-6"/>
                    <path d="M3 20h18"/>
                </svg>
            </a>
        @endif

        @if ($v2HasSettings)
            <button class="v2-rail-btn" type="button" data-section="settings" data-label="{{ translate('settings') }}" aria-label="{{ translate('settings') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                    <circle cx="9"  cy="7"  r="2" fill="currentColor" stroke="none"/>
                    <circle cx="15" cy="12" r="2" fill="currentColor" stroke="none"/>
                    <circle cx="7"  cy="17" r="2" fill="currentColor" stroke="none"/>
                </svg>
            </button>
        @endif
    </div>

</aside>
