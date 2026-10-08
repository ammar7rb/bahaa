@php
    $seller = auth('seller')->user();
    $sections = [
        ['home','dashboard','vendor.dashboard.index',[],request()->routeIs('vendor.dashboard.*')],
        ['wallet','finance_my_wallet','vendor.balance.index',[],request()->routeIs('vendor.balance.*')],
        ['products','products','vendor.products.list',['type'=>'all'],request()->routeIs('vendor.products.*')],
        ['profile','account_profile','vendor.account',[],request()->routeIs('vendor.account*')],
        ['invoices','vendor_invoices','vendor.invoices',[],request()->routeIs('vendor.invoices')],
        ['orders','orders','vendor.orders.list',['status'=>'all'],request()->routeIs('vendor.orders.*')],
        ['messages','administration_messages','vendor.messages',[],request()->routeIs('vendor.messages*')],
        ['notifications','notifications','vendor.notifications',[],request()->routeIs('vendor.notifications')],
        ['marketing','ads_manager','vendor.packages.index',[],request()->routeIs('vendor.packages.*')],
    ];
@endphp
<aside class="v2-ctxpanel" id="v2-ctxpanel" aria-label="{{ translate('vendor_navigation') }}">
    <div class="vendor-sidebar-brand"><a href="{{ route('vendor.dashboard.index') }}"><img src="{{ getStorageImages(path: getWebConfig(name: 'company_web_logo'), type: 'backend-logo') }}" alt="{{ getWebConfig(name: 'company_name') }}"></a><button type="button" data-vendor-sidebar-close aria-label="{{ translate('close') }}">&times;</button></div>
    <div class="vendor-store-identity"><strong>{{ $seller->shop?->name }}</strong><span>{{ translate('vendor_workspace') }}</span></div>
    <nav class="v2-ctx-scroll vendor-main-navigation">
        @foreach($sections as [$icon,$label,$name,$parameters,$active])
            <a class="vendor-main-nav-item {{ $active ? 'is-active' : '' }}" href="{{ route($name,$parameters) }}" @if($active) aria-current="page" @endif>
                @include('layouts.vendor.partials.v2._workspace-icon',compact('icon'))<span>{{ translate($label) }}</span>
                @if(in_array($icon,['orders','messages','notifications']))<span class="vendor-count {{ $icon === 'messages' ? 'is-green' : '' }}" data-inbox-count="{{ $icon }}" @if(empty($vendorInboxCounts[$icon])) hidden @endif>+{{ $vendorInboxCounts[$icon] ?? 0 }}</span>@endif
            </a>
        @endforeach
    </nav>
    <div class="vendor-sidebar-footer"><span>{{ $seller->administrative_reference }}</span></div>
</aside>
