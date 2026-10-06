<nav class="vendor-global-links" aria-label="{{ translate('vendor_navigation') }}">
    @foreach(['orders' => ['vendor.orders.list', 'fi-rr-shopping-bag'], 'notifications' => ['vendor.notifications', 'fi-rr-bell'], 'messages' => ['vendor.messages', 'fi-rr-envelope']] as $key => $link)
        <a href="{{ $key === 'orders' ? route($link[0], 'all') : route($link[0]) }}" title="{{ translate($key) }}" aria-label="{{ translate($key) }}{{ $vendorInboxCounts[$key] ? ' +'.$vendorInboxCounts[$key] : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                @if($key === 'orders')<path d="M8 3h8l2 3v15H6V6l2-3Z"/><path d="M9 10h6M9 14h6"/>
                @elseif($key === 'notifications')<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>
                @else<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>@endif
            </svg><span class="vendor-link-label">{{ translate($key) }}</span>
            <span data-inbox-count="{{ $key }}" class="vendor-count {{ $key === 'messages' ? 'is-green' : '' }}" {{ $vendorInboxCounts[$key] ? '' : 'hidden' }}>+{{ $vendorInboxCounts[$key] }}</span>
        </a>
    @endforeach
    <a class="vendor-add-product" href="{{ route('vendor.products.add') }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> {{ translate('add_product') }}</a>
</nav>
<span hidden id="vendor-inbox-counts-url" data-url="{{ route('vendor.inbox.counts') }}"></span>
