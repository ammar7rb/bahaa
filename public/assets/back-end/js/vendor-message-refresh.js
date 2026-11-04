'use strict';
(() => {
    const selector = '[data-vendor-message-refresh]';
    const container = document.querySelector(selector);
    if (!container) return;
    let refreshing = false;
    async function refreshMessages() {
        if (document.hidden || refreshing || container.contains(document.activeElement)) return;
        refreshing = true;
        try {
            const response = await fetch(window.location.href, {
                credentials: 'same-origin', cache: 'no-store',
                headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'}
            });
            if (!response.ok || response.redirected) return;
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = page.querySelector(selector);
            if (updated && !container.contains(document.activeElement) && updated.innerHTML !== container.innerHTML) {
                container.replaceChildren(...updated.childNodes);
            }
        } catch (_) { /* Keep the last successfully received conversation. */ }
        finally { refreshing = false; }
    }
    window.setInterval(refreshMessages, 8000);
    document.addEventListener('visibilitychange', refreshMessages);
})();
