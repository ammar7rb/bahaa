(() => {
    'use strict';
    if (window.adminWorkspaceStarted) return;
    window.adminWorkspaceStarted = true;
    const config = window.adminWorkspaceConfig;
    const input = document.getElementById('admin-global-search');
    const panel = document.getElementById('admin-search-results');
    let timer, request, generation = 0, selected = -1;
    const message = text => { panel.replaceChildren(); const p = document.createElement('p'); p.className = 'admin-empty'; p.textContent = text; p.setAttribute('role', 'status'); panel.append(p); };
    const open = show => { panel.hidden = !show; input.setAttribute('aria-expanded', String(show)); if (!show) input.removeAttribute('aria-activedescendant'); };
    function row(item, className) {
        const a = document.createElement('a'); a.className = className; a.href = item.url;
        const title = document.createElement('strong'); title.textContent = item.title;
        const detail = document.createElement('span'); detail.textContent = item.detail;
        a.append(title, detail); return a;
    }
    function notification(item, kind) {
        const a=document.createElement('a'); a.className='admin-notice'+(item.unread?' is-unread':''); a.dataset.noticeKind=kind; a.href=item.url;
        const media=document.createElement('span'); media.className='admin-notice-media';
        if (item.images?.length) item.images.slice(0,3).forEach(photo=>{const img=document.createElement('img'); img.className='admin-notice-thumb'; img.src=photo.url; img.alt=photo.alt || ''; img.loading='lazy'; img.width=56; img.height=56; media.append(img);});
        else {const icon=document.createElement('i'); icon.className='fi fi-rr-'+(item.icon || 'bell'); icon.setAttribute('aria-hidden','true'); media.append(icon);}
        const content=document.createElement('span'); content.className='admin-notice-content';
        const text=(tag,cls,value)=>{const el=document.createElement(tag); el.className=cls; el.textContent=value; return el;};
        content.append(text('strong','',item.title),text('span','admin-notice-identity',item.detail));
        if(item.preview) content.append(text('span','admin-notice-preview',item.preview));
        if(item.meta) content.append(text('span','admin-notice-meta',item.meta));
        const footer=document.createElement('span'); footer.className='admin-notice-footer';
        if(item.badge) footer.append(text('span','admin-notice-status',item.badge));
        if(item.time){const time=text('time','',item.time_label || ''); time.dateTime=item.time; footer.append(time);}
        content.append(footer);
        const arrow=document.createElement('i'); arrow.className='fi fi-rr-angle-small-left admin-notice-arrow'; arrow.setAttribute('aria-hidden','true');
        a.append(media,content,arrow); return a;
    }
    function imageFallback(img) {
        if (!img.dataset.fallbackApplied && config.imageFallback) {img.dataset.fallbackApplied='true'; img.src=config.imageFallback; return;}
        img.hidden=true;
        const media=img.closest('.admin-notice-media');
        if(media && !media.querySelector('i')) {const icon=document.createElement('i'); icon.className='fi fi-rr-picture'; icon.setAttribute('aria-hidden','true'); media.append(icon);}
    }
    document.addEventListener('error',event=>{const img=event.target; if(img instanceof HTMLImageElement && img.classList.contains('admin-notice-thumb')) imageFallback(img);},true);
    document.querySelectorAll('.admin-notice-thumb').forEach(img=>{if(img.complete && img.naturalWidth===0) imageFallback(img);});
    async function search() {
        const token = ++generation;
        request?.abort(); selected = -1; input.removeAttribute('aria-activedescendant');
        const q = input.value.trim();
        if (q.length < 2) { open(false); return; }
        request = new AbortController(); open(true); message(config.loading); input.setAttribute('aria-busy', 'true');
        const activeRequest = request;
        const timeout = setTimeout(() => activeRequest.abort(), 12000);
        try {
            const response = await fetch(input.closest('[data-search-url]').dataset.searchUrl + '?q=' + encodeURIComponent(q), {credentials: 'same-origin', headers: {Accept: 'application/json'}, signal: request.signal});
            if (!response.ok) throw new Error('search');
            const data = await response.json(); if (token !== generation) return;
            panel.replaceChildren(); let index = 0;
            data.groups.forEach(group => {
                const heading = document.createElement('div'); heading.className = 'admin-search-group'; heading.textContent = group.label; heading.setAttribute('role', 'presentation'); panel.append(heading);
                group.items.forEach(item => { const a = row(item, 'admin-search-option'); a.id = 'admin-search-option-' + index++; a.setAttribute('role', 'option'); a.setAttribute('aria-selected', 'false'); panel.append(a); });
            });
            if (!index) message(config.empty);
        } catch (error) { if (token === generation) message(config.error); }
        finally { clearTimeout(timeout); if (token === generation) input.setAttribute('aria-busy', 'false'); }
    }
    input.addEventListener('input', () => { clearTimeout(timer); request?.abort(); ++generation; selected = -1; panel.replaceChildren(); input.removeAttribute('aria-activedescendant'); input.setAttribute('aria-busy', 'false'); open(false); timer = setTimeout(search, 300); });
    input.addEventListener('focus', () => { if (input.value.trim().length >= 2) search(); });
    input.addEventListener('keydown', event => {
        const items = [...panel.querySelectorAll('[role=option]')];
        if (event.key === 'Escape') { clearTimeout(timer); request?.abort(); ++generation; input.setAttribute('aria-busy', 'false'); open(false); return; }
        if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && items.length) {
            event.preventDefault(); open(true); selected = (selected + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items.forEach((item, index) => item.setAttribute('aria-selected', String(index === selected)));
            input.setAttribute('aria-activedescendant', items[selected].id); items[selected].scrollIntoView({block: 'nearest'});
        } else if (event.key === 'Enter' && !panel.hidden && items.length) { event.preventDefault(); items[Math.max(0, selected)].click(); }
    });
    document.addEventListener('keydown', event => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {event.preventDefault(); input.focus();} });
    document.addEventListener('click', event => { if (!event.target.closest('.admin-global-search')) { clearTimeout(timer); request?.abort(); ++generation; open(false); } });
    input.closest('.admin-global-search').addEventListener('focusout', event => { if (!event.relatedTarget || !event.relatedTarget.closest('.admin-global-search')) open(false); });
    let refreshing = false;
    async function refresh() {
        if (refreshing || document.hidden) return;
        refreshing = true;
        try {
            const response = await fetch(config.snapshot, {credentials:'same-origin', headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error('snapshot');
            const data = await response.json();
            Object.entries(data.totals).forEach(([key, total]) => { const el = document.querySelector('[data-workspace-total="' + key + '"]'); if (el && total !== null) el.textContent = new Intl.NumberFormat(document.documentElement.lang).format(total); });
            Object.entries(data.categories).forEach(([key, category]) => {
                const badge = document.querySelector('[data-workspace-count="' + key + '"]');
                if (badge) {badge.textContent = category.count > 99 ? '99+' : category.count; badge.classList.toggle('d-none', category.count === 0); badge.setAttribute('aria-label', String(category.count));}
                if (badge) { const button = badge.closest('[data-workspace-label]'); button?.setAttribute('aria-label', button.dataset.workspaceLabel + ' (' + category.count + ')'); }
                const previewCount=document.querySelector('[data-workspace-preview-count="'+key+'"]'); if(previewCount) previewCount.textContent=category.count;
                const list = document.querySelector('[data-workspace-items="' + key + '"]');
                if (!list) return;
                // Preserve keyboard focus while a preview is being used.
                if (list.contains(document.activeElement)) return;
                list.replaceChildren(...category.items.map(item => notification(item, key)));
                if (!category.items.length) { const p = document.createElement('p'); p.className = 'admin-empty'; p.textContent = config.pendingEmpty; list.append(p); }
            });
        } catch (_) { /* Retain the last data until the next navigation or return. */ }
        finally { refreshing = false; }
    }
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
    document.addEventListener('v2:page-loaded', () => { open(false); refresh(); });
})();
