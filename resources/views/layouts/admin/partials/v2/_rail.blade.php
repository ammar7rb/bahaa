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
            <button class="v2-rail-btn" type="button" data-section="home" data-label="{{ translate('home') }}" aria-label="{{ translate('home') }}"><i class="fi fi-rr-home" aria-hidden="true"></i></button>
        @endif

        @if ($v2HasCatalog)
            <button class="v2-rail-btn" type="button" data-section="catalog" data-label="{{ translate('products') }}" aria-label="{{ translate('products') }}"><i class="fi fi-rr-box-open" aria-hidden="true"></i></button>
        @endif

        @if ($v2HasOrders)
            <button class="v2-rail-btn" type="button" data-section="orders" data-label="{{ translate('orders') }}" aria-label="{{ translate('orders') }}"><i class="fi fi-rr-shopping-cart" aria-hidden="true"></i></button>
        @endif


        @if ($v2HasAuction)
            <button class="v2-rail-btn" type="button" data-section="auction" data-label="{{ translate('Auction_Management') }}" aria-label="{{ translate('Auction_Management') }}"><i class="fi fi-rr-gavel" aria-hidden="true"></i></button>
        @endif


        @if ($v2HasPeople)
            <button class="v2-rail-btn" type="button" data-section="customers" data-label="{{ translate('customers') }}" aria-label="{{ translate('customers') }}"><i class="fi fi-rr-users" aria-hidden="true"></i></button>
            <button class="v2-rail-btn" type="button" data-section="vendors" data-label="التجار" aria-label="التجار"><i class="fi fi-rr-shop" aria-hidden="true"></i></button>
            <button class="v2-rail-btn" type="button" data-section="people" data-label="{{ translate('people') }}" aria-label="{{ translate('people') }}"><i class="fi fi-rr-users-alt" aria-hidden="true"></i></button>
        @endif

        @if ($v2HasReports)
            <button class="v2-rail-btn" type="button" data-section="reports" data-label="{{ __('financials.title') }}" aria-label="{{ __('financials.title') }}"><i class="fi fi-rr-chart-histogram" aria-hidden="true"></i></button>
        @endif

        @if ($v2HasSettings)
            <button class="v2-rail-btn" type="button" data-section="settings" data-label="{{ translate('settings') }}" aria-label="{{ translate('settings') }}"><i class="fi fi-rr-settings-sliders" aria-hidden="true"></i></button>
        @endif
    </div>

</aside>
