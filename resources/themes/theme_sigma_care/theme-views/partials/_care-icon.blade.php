@php
    $careIconPaths = [
        'search.png' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'user.png' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'wishlist.png' => '<path d="M20.5 4.5a5.5 5.5 0 0 0-8.5 1 5.5 5.5 0 0 0-8.5 7L12 21l8.5-8.5a5.5 5.5 0 0 0 0-8z"/>',
        'category.png' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'home.png' => '<path d="m3 10 9-7 9 7v10H3zM9 20v-7h6v7"/>',
        'cart.png' => '<path d="M2 3h3l3 12h11l3-9H6"/><circle cx="9" cy="20" r="1"/><circle cx="19" cy="20" r="1"/>',
        'vendor_icon.svg' => '<path d="M3 10h18l-2-7H5zM4 10v11h16V10M8 21v-7h5v7"/>',
        'support_icon.svg' => '<path d="M4 13v-1a8 8 0 0 1 16 0v6a3 3 0 0 1-3 3h-4"/><rect x="2" y="11" width="4" height="7" rx="2"/><rect x="18" y="11" width="4" height="7" rx="2"/>',
        'lock.svg' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
        'free_delivery_icon.svg' => '<path d="M2 5h12v12H2zM14 9h4l4 5v3h-8"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'order_placed_icon_timeline.svg' => '<path d="m3 7 9-4 9 4v11l-9 4-9-4zM3 7l9 4 9-4M12 11v11"/>',
    ];
@endphp
@if(isset($careIconPaths[$icon]))
    <span class="care-icon {{ $iconClass ?? '' }}" aria-hidden="true" style="background:none;mask:none;-webkit-mask:none"><svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $careIconPaths[$icon] !!}</svg></span>
@else
    <span class="care-icon {{ $iconClass ?? '' }}" aria-hidden="true" style="--care-icon: url('{{ dynamicAsset('public/themes/theme_sigma_care/public/assets/care/icons/' . $icon) }}')"></span>
@endif
