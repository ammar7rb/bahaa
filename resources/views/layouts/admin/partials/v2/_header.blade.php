@php
    use App\Utils\Helpers;
    $v2Logo = getWebConfig(name: 'company_web_logo');
    $v2Favicon = getWebConfig(name: 'company_fav_icon');
    $v2Local = session('local', app()->getLocale());
    $v2Languages = getWebConfig(name: 'language') ?? [];
    $v2LocalFlag = getLanguageFlagCode($v2Local);
    $workspaceSnapshot = app(\App\Services\AdminWorkspaceService::class)->snapshot();
    $workspaceMenus = [
        'payments' => ['payments_waiting_for_review', 'credit-card', route('admin.workspace.reviews', ['kind' => 'payments'])],
        'orders' => ['pending_Orders', 'shopping-bag', route('admin.workspace.reviews', ['kind' => 'orders'])],
        'products' => ['product_pending_review', 'box', route('admin.products.list', ['type' => 'vendor', 'request_status' => 0])],
        'messages' => ['messages', 'comment', route('admin.workspace.communication')],
    ];
@endphp
<header class="v2-header">
    <div class="v2-header-brand">
        <a class="v2-brand-logo" href="{{ route('admin.dashboard.index') }}" title="{{ translate('dashboard') }}">
            <img src="{{ getStorageImages(path: $v2Logo, type: 'backend-logo') }}" alt="{{ translate('logo') }}">
        </a>
        {{-- Compact "mini" mark shown only when the sidebar is collapsed. --}}
        <a class="v2-brand-logo-mini" href="{{ route('admin.dashboard.index') }}" title="{{ translate('dashboard') }}">
            <img src="{{ getStorageImages(path: $v2Favicon, type: 'backend-logo') }}" alt="{{ translate('logo') }}">
        </a>
        <button class="v2-sidebar-toggle" id="v2-sidebar-toggle" type="button"
                data-v2-tooltip="{{ translate('Collapse_sidebar') }}" aria-label="{{ translate('Toggle_sidebar') }}"
                data-v2-tip-expanded="{{ translate('Collapse_sidebar') }}"
                data-v2-tip-collapsed="{{ translate('Expand_sidebar') }}">
            {{-- Shown when sidebar is expanded → "collapse" arrow pointing inward --}}
            <svg class="v2-sb-icon v2-sb-collapse" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 6l-6 6 6 6"/>
                <path d="M21 4v16"/>
            </svg>
            {{-- Shown when sidebar is collapsed → "expand" arrow pointing outward --}}
            <svg class="v2-sb-icon v2-sb-expand" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 6l6 6-6 6"/>
                <path d="M3 4v16"/>
            </svg>
        </button>
    </div>


    <div class="admin-global-search" data-search-url="{{ route('admin.workspace.search') }}">
        <i class="fi fi-rr-search" aria-hidden="true"></i>
        <input id="admin-global-search" type="search" autocomplete="off" maxlength="100" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="admin-search-results" placeholder="{{ translate('workspace_search_placeholder') }}" aria-label="{{ translate('Search') }}">
        <kbd>Ctrl K</kbd>
        <div id="admin-search-results" class="admin-search-results" role="listbox" hidden></div>
    </div>
    <div class="v2-header-right">
        @foreach($workspaceMenus as $kind => [$label, $icon, $url])
            @if(isset($workspaceSnapshot['categories'][$kind]) && ($kind !== 'payments' || Helpers::module_permission_check('orders') || Helpers::module_permission_check('people') || Helpers::module_permission_check('reports')))
                @php
                    $category = $workspaceSnapshot['categories'][$kind];
                @endphp
                <div class="v2-dropdown">
                    <button class="v2-icon-btn admin-inbox-toggle" type="button" data-v2-dropdown-toggle data-workspace-label="{{ translate($label) }}" aria-expanded="false" aria-label="{{ translate($label) }} ({{ $category['count'] }})" title="{{ translate($label) }}">
                        <i class="fi fi-rr-{{ $icon }}" aria-hidden="true"></i>
                        <span class="v2-badge-dot {{ $category['count'] ? '' : 'd-none' }}" data-workspace-count="{{ $kind }}">{{ $category['count'] > 99 ? '99+' : $category['count'] }}</span>
                    </button>
                    <div class="v2-dropdown-menu v2-orders-menu admin-inbox">
                        <div class="v2-pop-head"><h4>{{ translate($label) }}</h4><a href="{{ $url }}">{{ translate('view_all') }}</a></div>
                        <div class="v2-pop-scroll" data-workspace-items="{{ $kind }}">
                            @forelse($category['items'] as $item)
                                <a class="admin-inbox-row" href="{{ $item['url'] }}"><strong>{{ $item['title'] }}</strong><span>{{ $item['detail'] }}</span></a>
                            @empty
                                <p class="admin-empty">{{ translate('workspace_no_pending') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
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
                        $v2LangCode = $v2Lang['code'];
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


        <button class="v2-icon-btn v2-theme-toggle" type="button" data-admin-theme-toggle onclick="window.baToggleAdminTheme()" aria-label="{{ translate('dark_mode') }}" aria-pressed="false" title="{{ translate('dark_mode') }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.8 13A9 9 0 0 1 11 3.2 9 9 0 1 0 20.8 13Z"/></svg>
        </button>
        {{-- Profile --}}
        <div class="v2-dropdown">
            <button class="v2-avatar-wrap" type="button" data-v2-dropdown-toggle
                    data-v2-tooltip="{{ auth('admin')->user()->name }}" aria-label="{{ auth('admin')->user()->name }}">
                <span class="v2-avatar">
                    <img src="{{ getStorageImages(path: auth('admin')->user()->image_full_url, type: 'backend-profile') }}"
                         alt="{{ translate('image_description') }}">
                    <span class="v2-avatar-status" aria-hidden="true"></span>
                </span>
                <span class="v2-profile-inline" aria-hidden="true">
                    <span class="v2-profile-inline-name">{{ auth('admin')->user()->name }}</span>
                    <span class="v2-profile-inline-role">{{ ucwords(auth('admin')->user()?->role?->name ?? '') }}</span>
                </span>
                <span class="v2-avatar-caret" aria-hidden="true">
                    <svg width="12" height="12" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 8 5 5 5-5"/></svg>
                </span>
            </button>
            <div class="v2-dropdown-menu">
                <div class="v2-dropdown-header">
                    <img src="{{ getStorageImages(path: auth('admin')->user()->image_full_url, type: 'backend-profile') }}"
                         alt="{{ translate('image_description') }}">
                    <div>
                        <div class="v2-profile-name">{{ auth('admin')->user()->name }}</div>
                        <div class="v2-profile-role">{{ ucwords(auth('admin')->user()?->role?->name ?? '') }}</div>
                    </div>
                </div>
                <a class="v2-dropdown-item"
                   href="{{ route('admin.profile.update', ['id' => auth('admin')->user()->id]) }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 0 1-4 0v-.1A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 0 1 0-4h.1A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 0 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>
                    </svg>
                    <span>{{ translate('settings') }}</span>
                </a>
                <div class="v2-dropdown-divider"></div>
                <a class="v2-dropdown-item v2-dropdown-item-danger" href="javascript:"
                   data-bs-toggle="modal" data-bs-target="#sign-out-modal">
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
