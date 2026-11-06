// Each search owns its request; dismissing invalidates every pending response.
document.querySelectorAll('.care-search').forEach(function (search) {
    const input = search.querySelector('input[type="search"]');
    const panel = search.querySelector('.search-result-box');
    const form = search.querySelector('form');
    if (!input || !panel || !form) return;
    let timer;
    let request;
    let generation = 0;
    panel.id = 'care-search-results-' + input.id;
    input.setAttribute('aria-controls', panel.id);
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('autocomplete', 'off');

    function dismiss() {
        generation++;
        clearTimeout(timer);
        request?.abort();
        request = null;
        panel.replaceChildren();
        panel.style.display = 'none';
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-busy', 'false');
    }
    function schedule() {
        dismiss();
        const name = input.value.trim();
        if (!name) return;
        const current = generation;
        timer = setTimeout(async function () {
            const controller = new AbortController();
            request = controller;
            input.setAttribute('aria-busy', 'true');
            try {
                const base = document.querySelector('meta[name="base-url"]').content;
                const url = new URL(base.replace(/\/$/, '') + '/searched-products', location.origin);
                url.search = new URLSearchParams({name, category_id: search.querySelector('[name="category_id"]')?.value || 'all'});
                const response = await fetch(url, {signal: controller.signal, headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
                if (!response.ok) throw new Error('Search request failed');
                const data = await response.json();
                if (current !== generation || name !== input.value.trim() || !search.contains(document.activeElement)) return;
                panel.innerHTML = data.result;
                if (!panel.querySelector('a[href]')) {
                    const message = document.createElement('p');
                    message.className = 'm-0 p-3';
                    message.setAttribute('role', 'status');
                    message.textContent = document.documentElement.lang.startsWith('ar') ? 'لا توجد نتائج مطابقة' : 'No matching results';
                    panel.replaceChildren(message);
                }
                panel.style.display = 'block';
                input.setAttribute('aria-expanded', 'true');
            } catch (error) {
                if (current === generation) dismiss();
            } finally {
                if (current === generation) {
                    request = null;
                    input.setAttribute('aria-busy', 'false');
                }
            }
        }, 180);
    }
    input.addEventListener('input', function (event) { if (!event.isComposing) schedule(); });
    input.addEventListener('compositionend', schedule);
    input.addEventListener('focus', schedule);
    search.addEventListener('focusout', function (event) {
        if (!search.contains(event.relatedTarget)) dismiss();
    });
    document.addEventListener('pointerdown', function (event) {
        if (!search.contains(event.target)) dismiss();
    });
    form.addEventListener('submit', dismiss);
    search.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            input.focus();
            dismiss();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            const links = Array.from(panel.querySelectorAll('a[href]'));
            if (!links.length) return;
            event.preventDefault();
            const index = links.indexOf(document.activeElement);
            const next = event.key === 'ArrowDown' ? Math.min(index + 1, links.length - 1) : (index < 0 ? links.length - 1 : Math.max(index - 1, 0));
            links[next].focus();
        }
    });
});
