(() => {
    'use strict';
    const header = document.querySelector('[data-customer-communication]');
    if (!header) return;
    const ar = document.documentElement.lang.startsWith('ar') || document.documentElement.dir === 'rtl';
    const words = ar ? {empty:'لا توجد عناصر حالياً.',error:'تعذر تحميل التحديثات. أعد المحاولة.',retry:'إعادة المحاولة'}
        : {empty:'No items yet.',error:'Could not load updates. Please retry.',retry:'Retry'};
    let busy = false;
    let stopped = false;
    const row = (item, full) => {
        const element = document.createElement(item.url ? 'a' : 'article');
        element.className = full ? 'customer-communication-item' : 'customer-preview-row';
        // All navigation comes from the authenticated, ownership-scoped server feed.
        if (item.url) {
            const url = new URL(item.url, location.origin);
            if (url.origin === location.origin) element.href = url.href;
        }
        const body = document.createElement('div');
        const title = document.createElement(full ? 'h2' : 'strong');
        title.textContent = item.title;
        const description = document.createElement(full ? 'p' : 'span');
        description.textContent = item.description;
        body.append(title, description);
        if (item.created_at) {
            const time = document.createElement('time');
            time.dateTime = item.created_at;
            time.textContent = new Date(item.created_at).toLocaleString(ar ? 'ar-EG' : 'en-GB');
            body.append(time);
        }
        element.append(body);
        return element;
    };
    const rendered = new WeakMap();
    const render = (container, items, full = false) => {
        const signature = JSON.stringify(items);
        if (rendered.get(container) === signature) return;
        rendered.set(container, signature);
        if (!items.length) {
            const empty = document.createElement('p'); empty.textContent = words.empty;
            container.replaceChildren(empty);
        } else container.replaceChildren(...items.map(item => row(item, full)));
    };
    const get = async (url) => {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(url, {headers:{Accept:'application/json'}, credentials:'same-origin', cache:'no-store', signal:controller.signal});
            if (response.status === 401 || response.status === 403) stopped = true;
            if (!response.ok) throw new Error('communication_failed');
            return await response.json();
        } finally { clearTimeout(timeout); }
    };
    const refresh = async () => {
        if (busy || stopped || document.hidden) return;
        busy = true;
        try {
            const data = await get(header.dataset.feedUrl);
            header.querySelectorAll('[data-communication-type]').forEach(preview => {
                const total = data[preview.dataset.communicationType].total;
                const count = preview.querySelector('[data-communication-total]');
                count.hidden = total === 0;
                count.textContent = total > 99 ? '99+' : String(total);
                render(preview.querySelector('[data-preview-items]'), data[preview.dataset.communicationType].items);
            });
            const list = document.querySelector('[data-communication-list]');
            if (list) {
                const url = new URL(header.dataset.feedUrl, location.origin);
                url.searchParams.set('type', list.dataset.communicationList);
                const page = Math.max(1, Number(new URL(location.href).searchParams.get('page')) || 1);
                url.searchParams.set('page', String(page));
                const result = await get(url);
                render(list, result.items, true);
                const pagination = document.querySelector('[data-communication-pagination]');
                if (pagination) {
                    pagination.replaceChildren();
                    for (const [label, target] of [[ar?'السابق':'Previous',page-1],[ar?'التالي':'Next',page+1]]) {
                        if (target < 1 || target > result.last_page) continue;
                        const link = document.createElement('a');
                        const targetUrl = new URL(location.href); targetUrl.searchParams.set('page',String(target));
                        link.href = targetUrl.href; link.textContent = label; link.className = 'btn btn-outline-primary m-1';
                        pagination.append(link);
                    }
                }
            }
            header.querySelectorAll('[data-communication-error]').forEach(node => node.remove());
        } catch (_) {
            header.querySelectorAll('[data-preview-items]').forEach(container => {
                if (container.querySelector('[data-communication-error]')) return;
                const error = document.createElement('div'); error.dataset.communicationError = '';
                const text = document.createElement('p'); text.textContent = words.error;
                const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-link';
                retry.textContent = words.retry; retry.addEventListener('click', refresh);
                error.append(text,retry); container.prepend(error);
            });
        } finally { busy = false; }
    };
    header.querySelectorAll('details').forEach(current => current.addEventListener('toggle', () => {
        if (current.open) { header.querySelectorAll('details').forEach(other => {if(other !== current) other.open = false;}); refresh(); }
    }));
    document.addEventListener('click', event => {if(!header.contains(event.target)) header.querySelectorAll('details').forEach(item=>item.open=false);});
    document.addEventListener('keydown', event => {if(event.key === 'Escape') header.querySelectorAll('details').forEach(item=>item.open=false);});
    document.addEventListener('visibilitychange', () => {if(!document.hidden) refresh();});
    const timer = setInterval(refresh, 5000);
    window.addEventListener('pagehide', () => clearInterval(timer), {once:true});
    refresh();
})();
