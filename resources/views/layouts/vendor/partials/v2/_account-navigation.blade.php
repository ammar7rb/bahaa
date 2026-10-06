<nav class="vendor-global-links" aria-label="{{ translate('vendor_navigation') }}">
    <a class="vendor-order-shortcut" href="{{ route('vendor.orders.list', 'all') }}" title="{{ translate('orders') }}">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'orders'])<span class="vendor-count" data-inbox-count="orders" {{ $vendorInboxCounts['orders'] ? '' : 'hidden' }}>+{{ $vendorInboxCounts['orders'] }}</span></a>
    @foreach(['messages', 'notifications'] as $channel)
        <div class="v2-dropdown vendor-inbox-dropdown">
            <button class="vendor-inbox-trigger" type="button" data-v2-dropdown-toggle aria-expanded="false" aria-controls="vendor-popover-{{ $channel }}" aria-label="{{ translate($channel === 'messages' ? 'administration_messages' : 'notifications') }}">
                @include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>$channel])<span class="vendor-count {{ $channel === 'messages' ? 'is-green' : '' }}" data-inbox-count="{{ $channel }}" {{ $vendorInboxCounts[$channel] ? '' : 'hidden' }}>+{{ $vendorInboxCounts[$channel] }}</span>
            </button>
            <div class="v2-dropdown-menu vendor-inbox-popover" id="vendor-popover-{{ $channel }}">
                <div class="vendor-popover-heading"><strong>{{ translate($channel === 'messages' ? 'administration_messages' : 'notifications') }}</strong><span class="vendor-popover-total" data-unread-label="{{ translate('vendor_unread') }}">{{ $vendorInboxCounts[$channel] }} {{ translate('vendor_unread') }}</span></div>
                <div class="vendor-popover-list">
                @include('layouts.vendor.partials.v2._inbox-previews')
                </div>
                <a class="vendor-popover-footer" href="{{ route($channel === 'messages' ? 'vendor.messages' : 'vendor.notifications') }}">{{ translate('vendor_view_all') }} @include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'arrow'])</a>
            </div>
        </div>
    @endforeach
    <a class="vendor-add-product" href="{{ route('vendor.products.add') }}">@include('layouts.vendor.partials.v2._workspace-icon', ['icon'=>'add'])<span>{{ translate('add_product') }}</span></a>
</nav>
<span hidden id="vendor-inbox-counts-url" data-url="{{ route('vendor.inbox.counts') }}"></span>
