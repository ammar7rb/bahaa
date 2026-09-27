{{-- v1 body — wraps the rail + header + ctxpanel + @yield('content') in the grid shell.
     All shell markup uses v2- prefixed classes so no styling leaks into @yield('content'). --}}
{{-- The legacy vendor demo banner is intentionally omitted from the project preview. --}}

{{-- v2 sidebar/header chrome uses its own built-in colour palette (the
     defaults defined inside .app-v2 in admin-v2.css). The system
     primary / secondary picker in business settings is intentionally
     NOT propagated here — v2 stays on its design colours. --}}

<div class="app-v2" id="appV2">
    @include('layouts.admin.partials.v2._header')
    @include('layouts.admin.partials.v2._rail')
    @include('layouts.admin.partials.v2._side-bar')

    {{-- Pre-paint sidebar hydration. Runs synchronously while the parser is
         still walking the document, so the rail's v2-is-active and the
         correct ctx-section's v2-is-on are set BEFORE the browser paints
         the sidebar. Without this the home section is hard-shown until
         admin-v2.js's DOMContentLoaded boot runs hydrateActive() — that
         gap is the visible "Dashboard flicker" on every navigation.
         Reads the rendered hrefs (same source-of-truth admin-v2.js uses
         for pickActiveSectionFromURL), so URL changes flow through with
         no separate pattern map to maintain. --}}
    <script>
    (function () {
        var path = window.location.pathname;
        var sections = document.querySelectorAll('#v2-ctxpanel .v2-ctx-section');
        if (!sections.length) return;
        var match = null;
        for (var i = 0; i < sections.length; i++) {
            var sec = sections[i];
            var anchors = sec.querySelectorAll('a[href]');
            for (var j = 0; j < anchors.length; j++) {
                var href = anchors[j].getAttribute('href') || '';
                href = href.replace(/^https?:\/\/[^/]+/, '').split('?')[0].split('#')[0];
                if (!href || href === '/') continue;
                if (path === href || path.indexOf(href.replace(/\/$/, '') + '/') === 0) {
                    match = sec.getAttribute('data-section');
                    break;
                }
            }
            if (match) break;
        }
        if (!match) return;
        for (var k = 0; k < sections.length; k++) {
            sections[k].classList.toggle('v2-is-on', sections[k].getAttribute('data-section') === match);
        }
        var rails = document.querySelectorAll('.v2-rail .v2-rail-btn[data-section]');
        for (var m = 0; m < rails.length; m++) {
            rails[m].classList.toggle('v2-is-active', rails[m].getAttribute('data-section') === match);
        }
    })();
    </script>

    <main class="v2-main" id="content" role="main">
        @yield('content')
    </main>
</div>

{{-- Re-use project modals (sign-out, image, toggle). v1 uses its own setup popover (below) instead of the v0 modal. --}}
@include('layouts.admin.partials._toggle-modal')
@include('layouts.admin.components.image-modal')
@include('layouts.admin.partials._sign-out-modal')
@include('layouts.admin.partials._modals')
@include('layouts.admin.partials._alert-message')
@include('layouts.admin.partials.v2._setup-popover')

{{-- Server-side pin shortcuts: load the current admin's saved pin list
     so the v2 JS can render the pin section on first paint without an
     extra round-trip. The route is registered in routes/admin/routes.php
     under the same admin middleware group, so getting here means the
     user can hit it. --}}
@php
    $v2InitialPins = [];
    try {
        $__v2Admin = auth('admin')->user();
        if ($__v2Admin) {
            $v2InitialPins = \App\Models\V2SidebarPin::pinsFor(\App\Models\V2SidebarPin::TYPE_ADMIN, (int)$__v2Admin->id);
        }
    } catch (\Throwable $e) { /* table not migrated yet — fall through with empty list */ }
@endphp
<script>
    window.__v2InitialPins = {!! json_encode($v2InitialPins) !!};
    window.__v2PinsToggleUrl  = "{{ route('admin.v2.sidebar-pins.toggle') }}";
    window.__v2PinsReplaceUrl = "{{ route('admin.v2.sidebar-pins.replace') }}";
    window.__v2PinsCsrf       = "{{ csrf_token() }}";
</script>

{{-- v2 advance-search wiring — opens the same #advanceSearchModal the
     classic UI uses (markup in _modals, included above), but with no
     backdrop dimming so it reads as a popover. --}}
@include('layouts.admin.partials.v2._advance-search-script')

<script>
    (function () {
        const endpoint = @json(route('admin.operational-feed.index'));
        const pollVisibleMs = 3000;
        const pollHiddenMs = 15000;
        const list = document.getElementById('v2-operational-feed-items');
        const actionList = document.getElementById('v2-operational-action-items');
        const eventList = document.getElementById('v2-operational-event-items');
        const emptyState = document.getElementById('v2-operational-empty');
        const eventTitle = document.getElementById('v2-operational-event-title');
        const messageList = document.getElementById('v2-message-items');
        const messageEmpty = document.getElementById('v2-message-empty');
        const seenEventIds = new Set();
        let cursor = new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString();
        let initialized = false;
        let timer = null;

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[character];
            });
        }

        function updateCount(selector, value) {
            document.querySelectorAll(selector).forEach(function (element) {
                element.textContent = value > 99 ? '99+' : value;
                element.classList.toggle('d-none', value < 1);
            });
        }

        function updateDashboard(metrics) {
            if (!metrics) return;
            Object.keys(metrics).forEach(function (key) {
                document.querySelectorAll('[data-live-metric="' + key + '"]').forEach(function (element) {
                    if (element.getAttribute('data-live-metric')) element.textContent = Number(metrics[key] || 0).toLocaleString();
                });
            });

            const customers = Number(metrics.total_customer_count || 0);
            const vendors = Number(metrics.total_vendor_count || 0);
            document.querySelectorAll('[data-live-total-users]').forEach(function (element) {
                element.textContent = (customers + vendors).toLocaleString();
            });
            document.querySelectorAll('[data-live-user-label="customer"]').forEach(function (element) {
                element.textContent = @json(translate('total_customer')) + ' (' + customers.toLocaleString() + ')';
            });
            document.querySelectorAll('[data-live-user-label="vendor"]').forEach(function (element) {
                element.textContent = @json(translate('total_vendor')) + ' (' + vendors.toLocaleString() + ')';
            });
        }

        function renderEvent(event) {
            if (!eventList || seenEventIds.has(event.id)) return false;
            seenEventIds.add(event.id);
            const item = document.createElement('a');
            item.className = 'v2-dropdown-item d-flex align-items-center gap-2';
            item.href = event.url;
            item.dataset.operationalEvent = event.id;
            item.innerHTML = '<span class="v2-notification-icon"><i class="fi fi-rr-bell"></i></span>' +
                '<span class="flex-grow-1"><strong>' + escapeHtml(event.title) + '</strong>' +
                '<span class="d-block small text-muted">' + escapeHtml(event.body) + '</span>' +
                '<span class="d-block small text-primary">' + escapeHtml(event.display_time) + '</span></span>';
            eventList.prepend(item);
            return true;
        }

        function renderActionItems(items) {
            if (!actionList) return;
            actionList.innerHTML = '';
            (items || []).forEach(function (item) {
                const link = document.createElement('a');
                if (item.kind === 'product_review') {
                    link.className = 'v2-review-card';
                    link.href = item.url;
                    link.dataset.operationalSummary = item.id;
                    link.innerHTML = '<img class="v2-review-thumb" src="' + escapeHtml(item.image) + '" alt="">' +
                        '<span class="v2-review-content"><strong>' + escapeHtml(item.title) + '</strong>' +
                        '<span class="v2-review-name">' + escapeHtml(item.name) + '</span>' +
                        '<span class="v2-review-meta"><span>' + escapeHtml(item.reference_label) + ': <b>' + escapeHtml(item.reference) + '</b></span>' +
                        '<span>' + escapeHtml(item.amount_label) + ': <b>' + escapeHtml(item.amount) + '</b></span></span>' +
                        '<span class="d-block small text-muted">' + escapeHtml(item.meta) + '</span></span>' +
                        '<span class="v2-pop-action"><i class="fi fi-rr-eye"></i></span>';
                    actionList.appendChild(link);
                    return;
                }
                link.className = 'v2-dropdown-item d-flex align-items-center gap-2';
                link.href = item.url;
                link.dataset.operationalSummary = item.id;
                link.innerHTML = '<span class="v2-notification-icon"><i class="' + escapeHtml(item.icon) + '"></i></span>' +
                    '<span class="flex-grow-1"><strong>' + escapeHtml(item.title) + '</strong>' +
                    '<span class="d-block small text-muted">' + escapeHtml(item.body) + '</span></span>' +
                    '<span class="v2-notification-count">' + Number(item.count || 0).toLocaleString() + '</span>';
                actionList.appendChild(link);
            });
        }

        function renderMessages(messages) {
            if (!messageList) return;
            messageList.querySelectorAll('.v2-message-card').forEach(function (item) { item.remove(); });
            (messages || []).forEach(function (message) {
                const link = document.createElement('a');
                link.className = 'v2-message-card';
                link.href = message.url;
                link.innerHTML = '<span class="v2-message-avatar">' + escapeHtml(message.initial) + '</span>' +
                    '<span class="v2-message-content"><span class="v2-message-heading"><strong>' + escapeHtml(message.subject) + '</strong><time>' + escapeHtml(message.time) + '</time></span>' +
                    '<span class="v2-message-sender">' + escapeHtml(message.sender) + '</span><span class="v2-message-preview">' + escapeHtml(message.preview) + '</span></span>' +
                    '<span class="v2-message-unread" aria-hidden="true"></span>';
                messageList.insertBefore(link, messageEmpty || null);
            });
            if (messageEmpty) messageEmpty.classList.toggle('d-none', (messages || []).length > 0);
        }

        function updateEmptyState() {
            const hasActions = actionList && actionList.children.length > 0;
            const hasEvents = eventList && eventList.children.length > 0;
            if (emptyState) emptyState.classList.toggle('d-none', hasActions || hasEvents);
            if (eventTitle) eventTitle.classList.toggle('d-none', !hasEvents);
        }

        function showToast(event) {
            let container = document.getElementById('admin-live-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'admin-live-toast-container';
                container.className = 'position-fixed top-0 end-0 p-3';
                container.style.zIndex = '1090';
                document.body.appendChild(container);
            }
            const toast = document.createElement('a');
            toast.href = event.url;
            toast.className = 'd-block bg-white text-body border rounded shadow p-3 mb-2 text-decoration-none';
            toast.style.width = 'min(360px, calc(100vw - 32px))';
            toast.innerHTML = '<strong>' + escapeHtml(event.title) + '</strong><div class="small text-muted mt-1">' + escapeHtml(event.body) + '</div>';
            container.appendChild(toast);
            window.setTimeout(function () { toast.remove(); }, 8000);

            if ('Notification' in window && Notification.permission === 'granted') {
                const notification = new Notification(event.title, {body: event.body, tag: event.id});
                notification.onclick = function () { window.focus(); window.location.href = event.url; };
            }
        }

        function playAlert() {
            const audio = document.getElementById('myAudio');
            if (audio) {
                audio.currentTime = 0;
                audio.play().catch(function () {});
            }
        }

        function applyPayload(payload) {
            if (!payload) return;
            const summary = payload.summary || {};
            const actionableTotal = ['pending_products', 'pending_activations', 'pending_refunds', 'customer_registrations_today', 'seller_registrations_today', 'password_resets_today'].reduce(function (total, key) {
                return total + Number(summary[key] || 0);
            }, 0);
            updateCount('[data-admin-new-order-count]', Number(summary.pending_orders || 0));
            updateCount('[data-admin-pending-product-count]', Number(summary.pending_products || 0));
            updateCount('[data-admin-message-count]', Number(summary.unread_messages || 0));
            updateCount('[data-admin-notification-total]', actionableTotal);
            updateDashboard(Object.assign({}, payload.dashboard_metrics || {}, {
                new_order_count: summary.new_orders,
                pending_activation_count: summary.pending_activations,
                pending_refund_count: summary.pending_refunds,
                customer_registrations_today: summary.customer_registrations_today,
                seller_registrations_today: summary.seller_registrations_today,
                password_resets_today: summary.password_resets_today
            }));

            renderActionItems(payload.action_items || []);
            renderMessages(payload.messages || []);
            if (!initialized && eventList) eventList.innerHTML = '';
            let newestEvent = null;
            (payload.events || []).slice().reverse().forEach(function (event) {
                if (event.type === 'new_order' || event.type === 'contact_message') {
                    if (!seenEventIds.has(event.id)) {
                        seenEventIds.add(event.id);
                        newestEvent = event;
                    }
                    return;
                }
                if (renderEvent(event)) newestEvent = event;
            });
            updateEmptyState();
            if (initialized && newestEvent) {
                playAlert();
                showToast(newestEvent);
            }
            initialized = true;
            cursor = payload.server_time || new Date().toISOString();
        }

        async function refreshAdminNotifications() {
            window.clearTimeout(timer);
            try {
                const includeDashboard = document.querySelector('[data-live-metric]') ? '&dashboard=1' : '';
                const response = await fetch(endpoint + '?after=' + encodeURIComponent(cursor) + includeDashboard, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
                });
                if (response.ok) applyPayload(await response.json());
            } catch (error) {
                // Keep the last successful values during a temporary connection failure.
            } finally {
                timer = window.setTimeout(refreshAdminNotifications, document.hidden ? pollHiddenMs : pollVisibleMs);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const audio = document.getElementById('myAudio');
            document.addEventListener('pointerdown', function unlockSound() {
                if (!audio) return;
                const volume = audio.volume;
                audio.volume = 0;
                audio.play().then(function () { audio.pause(); audio.currentTime = 0; audio.volume = volume; }).catch(function () { audio.volume = volume; });
            }, {once: true});
            refreshAdminNotifications();
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) refreshAdminNotifications();
            });
        });
    })();
</script>
