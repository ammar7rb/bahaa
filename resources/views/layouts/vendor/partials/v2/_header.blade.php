@php
    use App\Models\Seller;
    use Illuminate\Support\Facades\Session;
    $v2Vendor = Seller::with(['shop'])->where('id', auth('seller')->id())->first();
    $v2Direction = Session::get('direction');
    $v2Local = session()->has('local') ? session('local') : 'en';
    $v2LangRow = \App\Models\BusinessSetting::where('type', 'language')->first();
    $v2Languages = $v2LangRow ? json_decode($v2LangRow['value'] ?? '[]', true) : [];
    $v2LogoPath = getWebConfig(name: 'company_web_logo');
    $v2FaviconMini = getWebConfig(name: 'company_fav_icon');

    // Vendor brand: prefer the logged-in seller's shop logo for both the
    // full and mini header marks. Falls back to the platform site logo /
    // favicon when the shop hasn't uploaded an image yet (storageLink
    // returns status 404 in that case). Shop name is used as alt text
    // when available so the brand mark is self-describing.
    $v2ShopImage = $v2Vendor?->shop?->image_full_url;
    $v2HasShopLogo = is_array($v2ShopImage) && (($v2ShopImage['status'] ?? 0) === 200) && !empty($v2ShopImage['path']);
    $v2BrandLogoSrc = $v2HasShopLogo
        ? $v2ShopImage['path']
        : getStorageImages(path: $v2LogoPath, type: 'backend-logo');
    $v2BrandLogoMiniSrc = $v2HasShopLogo
        ? $v2ShopImage['path']
        : getStorageImages(path: $v2FaviconMini, type: 'backend-logo');
    $v2BrandLogoAlt = !empty($v2Vendor?->shop?->name) ? $v2Vendor->shop->name : translate('logo');
    // Resolve the currently-active language entry from the DB-backed
    // business_settings → language row (the same source v1 uses).
    // Display name + flag code for the trigger both come from that row
    // so there's no client-side guessing.
    $v2LocalFlag = getLanguageFlagCode($v2Local);
    $v2LocalLangName = '';
    foreach ($v2Languages as $v2LangItem) {
        if (($v2LangItem['code'] ?? '') === $v2Local) {
            $v2LocalFlag = getLanguageFlagCode($v2LangItem['code']);
            $v2LocalLangName = $v2LangItem['name'] ?? '';
        }
    }
@endphp

<header class="v2-header">
    <div class="v2-header-brand">
        <a class="v2-brand-logo" href="{{ route('vendor.dashboard.index') }}" title="{{ $v2BrandLogoAlt }}">
            <img class="{{ $v2HasShopLogo ? '' : 'ba-platform-logo' }}" src="{{ $v2BrandLogoSrc }}" alt="{{ $v2BrandLogoAlt }}">
        </a>
        {{-- Compact "mini" mark shown only when the sidebar is collapsed.
             Uses the same shop image when available so the brand identity
             stays consistent in both expanded and collapsed states. --}}
        <a class="v2-brand-logo-mini" href="{{ route('vendor.dashboard.index') }}" title="{{ $v2BrandLogoAlt }}">
            <img class="{{ $v2HasShopLogo ? '' : 'ba-platform-logo' }}" src="{{ $v2BrandLogoMiniSrc }}" alt="{{ $v2BrandLogoAlt }}">
        </a>
        <button class="v2-sidebar-toggle" id="v2-sidebar-toggle" type="button"
                data-v2-tooltip="{{ translate('Collapse_sidebar') }}" aria-label="{{ translate('Toggle_sidebar') }}"
                data-v2-tip-expanded="{{ translate('Collapse_sidebar') }}"
                data-v2-tip-collapsed="{{ translate('Expand_sidebar') }}"></button>
    </div>

    <nav class="v2-crumbs" id="v2-header-crumbs" aria-label="{{ translate('Breadcrumb') }}"></nav>

    <div class="v2-header-right">
<button class="v2-icon-btn" type="button" onclick="window.baFullPagePrint();"
                aria-label="{{ translate('print') }}" data-v2-tooltip="{{ translate('print') }}" data-smart-print-trigger>
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <path d="M6 14h12v7H6z"/>
            </svg>
        </button>

        @include('layouts.vendor.partials.v2._account-navigation')
        {{-- Language switcher --}}
        <div class="v2-dropdown">
            <button class="v2-lang-btn" type="button" data-v2-dropdown-toggle
                    aria-label="{{ translate('Language') }}">
                <img class="v2-flag-img" width="18" height="18"
                     src="{{ dynamicAsset(path: 'public/assets/front-end/img/flags/' . $v2LocalFlag . '.png') }}"
                     alt="{{ $v2Local }}">
                {{-- Compact locale code (EN, BN, …) so the header chip
                     stays narrow. The dropdown below shows the full DB
                     name for each language. --}}
                <span class="v2-lang-name">{{ strtoupper($v2LocalFlag ?: $v2Local) }}</span>
                <svg class="v2-lang-caret" width="10" height="10" viewBox="0 0 20 20" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m5 8 5 5 5-5"/>
                </svg>
            </button>
            <div class="v2-dropdown-menu">
                @foreach ($v2Languages as $v2Lang)
                    @php
                        $v2LangCode = $v2Lang['code'] ?? '';
                        $v2LangFlagCode = getLanguageFlagCode($v2LangCode);
                    @endphp
                    @if (($v2Lang['status'] ?? 0) == 1)
                        <a class="v2-dropdown-item change-language {{ $v2LangCode == $v2Local ? 'v2-is-active' : '' }}"
                           href="javascript:"
                           data-action="{{ route('change-language') }}"
                           data-language-code="{{ $v2LangCode }}">
                            <img width="20" height="20" style="border-radius:50%;object-fit:cover;"
                                 src="{{ dynamicAsset(path: 'public/assets/front-end/img/flags/' . $v2LangFlagCode . '.png') }}"
                                 alt="{{ $v2Lang['name'] ?? $v2LangCode }}">
                            <span class="text-capitalize">{{ $v2Lang['name'] ?? $v2LangCode }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Fullscreen toggle --}}
        <button class="v2-icon-btn" type="button" id="v2-fullscreen-toggle"
                data-v2-tooltip="{{ translate('Enter_fullscreen') }}" aria-label="{{ translate('Enter_fullscreen') }}"
                data-v2-tip-enter="{{ translate('Enter_fullscreen') }}"
                data-v2-tip-exit="{{ translate('Exit_fullscreen') }}">
            <svg class="v2-fs-icon v2-fs-enter" width="19" height="19" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 9V5a1 1 0 0 1 1-1h4"/>
                <path d="M15 4h4a1 1 0 0 1 1 1v4"/>
                <path d="M20 15v4a1 1 0 0 1-1 1h-4"/>
                <path d="M9 20H5a1 1 0 0 1-1-1v-4"/>
            </svg>
            <svg class="v2-fs-icon v2-fs-exit" style="display:none" width="19" height="19" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 4v4a1 1 0 0 1-1 1H4"/>
                <path d="M15 4v4a1 1 0 0 0 1 1h4"/>
                <path d="M15 20v-4a1 1 0 0 1 1-1h4"/>
                <path d="M9 20v-4a1 1 0 0 0-1-1H4"/>
            </svg>
        </button>

        {{-- Profile dropdown --}}
        <div class="v2-dropdown">
            <button class="v2-avatar-wrap" type="button" data-v2-dropdown-toggle
                    data-v2-tooltip="{{ $v2Vendor->f_name ?? '' }}" aria-label="{{ $v2Vendor->f_name ?? '' }}">
                <span class="v2-avatar">
                    <img src="{{ getStorageImages(path: $v2Vendor->image_full_url ?? '', type: 'backend-profile') }}"
                         alt="{{ translate('image_description') }}">
                    <span class="v2-avatar-status" aria-hidden="true"></span>
                </span>
                <span class="v2-profile-inline" aria-hidden="true">
                    <span class="v2-profile-inline-name">{{ $v2Vendor->f_name ?? '' }}</span>
                    <span class="v2-profile-inline-role">{{ $v2Vendor->email ?? '' }}</span>
                </span>
                <span class="v2-avatar-caret" aria-hidden="true">
                    <svg width="12" height="12" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 8 5 5 5-5"/></svg>
                </span>
            </button>
            <div class="v2-dropdown-menu">
                <div class="v2-dropdown-header">
                    <img src="{{ getStorageImages(path: $v2Vendor->image_full_url ?? '', type: 'backend-profile') }}"
                         alt="{{ translate('image_description') }}">
                    <div>
                        <div class="v2-profile-name">{{ $v2Vendor->f_name ?? '' }}</div>
                        <div class="v2-profile-role">{{ $v2Vendor->email ?? '' }}</div>
                    </div>
                </div>
                <a class="v2-dropdown-item"
                   href="{{ route('vendor.account') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 0 1-4 0v-.1A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 0 1 0-4h.1A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 0 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>
                    </svg>
                    <span>{{ translate('settings') }}</span>
                </a>
                <div class="v2-dropdown-divider"></div>
                <a class="v2-dropdown-item v2-dropdown-item-danger" href="javascript:"
                   data-toggle="modal" data-target="#sign-out-modal">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <path d="m16 17 5-5-5-5M21 12H9"/>
                    </svg>
                    <span>{{ translate('logout') }}</span>
                </a>
            </div>
        </div>
    </div>
</header>
