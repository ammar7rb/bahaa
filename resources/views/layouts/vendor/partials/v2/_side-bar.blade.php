@php
    $seller = auth('seller')->user();
    $sections = [
        ['home','dashboard','vendor.dashboard.index',[],request()->routeIs('vendor.dashboard.*')],
        ['profile','account_profile','vendor.account',[],request()->routeIs('vendor.account*')],
        ['wallet','finance_my_wallet','vendor.balance.index',[],request()->routeIs('vendor.balance.*')],
        ['add','add_product','vendor.products.add',[],request()->routeIs('vendor.products.add')],
        ['products','products','vendor.products.list',['type'=>'all'],request()->routeIs('vendor.products.*') && !request()->routeIs('vendor.products.add')],
        ['orders','orders','vendor.orders.list',['status'=>'all'],request()->routeIs('vendor.orders.*')],
        ['marketing','vendor_subscriptions','vendor.packages.index',[],request()->routeIs('vendor.packages.*')],
        ['settings','sigma_account_settings','vendor.account',[],false],
        ['invoices','vendor_invoices','vendor.invoices',[],request()->routeIs('vendor.invoices')],
    ];
@endphp
<aside class="v2-ctxpanel" id="v2-ctxpanel" aria-label="{{ translate('vendor_navigation') }}">
    <div class="vendor-sidebar-brand"><a href="{{ route('vendor.dashboard.index') }}"><img src="{{ getStorageImages(path: getWebConfig(name: 'company_web_logo'), type: 'backend-logo') }}" alt="{{ getWebConfig(name: 'company_name') }}"></a><button type="button" data-vendor-sidebar-close aria-label="{{ translate('close') }}">&times;</button></div>
    <div class="vendor-store-identity"><div class="vendor-sidebar-person"><img src="{{ getStorageImages(path: $seller->image_full_url, type: 'backend-profile') }}" alt="" width="44" height="44"><div><strong>{{ trim($seller->f_name.' '.$seller->l_name) ?: translate('vendor_account') }}</strong><span>{{ $seller->shop?->name ?: '—' }}</span></div></div>@if($seller->created_at)<span class="vendor-since">{{ translate('sigma_joined') }} <bdi>{{ $seller->created_at->format('Y/m/d') }}</bdi></span>@endif</div>
    <nav class="v2-ctx-scroll vendor-main-navigation">
        @foreach($sections as [$icon,$label,$name,$parameters,$active])
            <a class="vendor-main-nav-item {{ $active ? 'is-active' : '' }}" href="{{ route($name,$parameters) }}{{ $icon === 'settings' ? '#account-settings' : '' }}" @if($active) aria-current="page" @endif>
                @include('layouts.vendor.partials.v2._workspace-icon',compact('icon'))<span>{{ translate($label) }}</span>
                @if(in_array($icon,['orders','messages','notifications']))<span class="vendor-count {{ $icon === 'messages' ? 'is-green' : '' }}" data-inbox-count="{{ $icon }}" @if(empty($vendorInboxCounts[$icon])) hidden @endif>+{{ $vendorInboxCounts[$icon] ?? 0 }}</span>@endif
            </a>
        @endforeach
    </nav>
    <div class="vendor-sidebar-footer"><span>{{ $seller->administrative_reference }}</span></div>
</aside>
