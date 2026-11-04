(() => {
    'use strict';
    const root = document.querySelector('[data-financial-accounts]');
    if (!root) return;
    const dialog = root.querySelector('[data-statement-dialog]');
    const content = root.querySelector('[data-statement-content]');
    const owner = root.querySelector('[data-statement-owner]');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let request;
    let origin;
    let lastUrl;
    let generation = 0;
    let entrance;
    const state = name => {
        content.replaceChildren(root.querySelector(`[data-statement-${name}]`).content.cloneNode(true));
    };
    async function load(url) {
        const token = ++generation;
        request?.abort(); request = new AbortController();
        lastUrl = url;
        state('loading'); content.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {signal: request.signal, credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}});
            if (!response.ok || response.redirected || response.headers.get('X-Financial-Statement') !== '1') throw new Error('Statement unavailable');
            const html = await response.text();
            if (token !== generation || !dialog.open) return;
            content.innerHTML = html;
            owner.textContent = content.querySelector('[data-financial-statement]')?.dataset.accountName || owner.textContent;
            content.scrollTop = 0;
        } catch (error) {
            if (error.name !== 'AbortError' && token === generation && dialog.open) state('error');
        } finally {
            if (token === generation) content.removeAttribute('aria-busy');
        }
    }
    function open(url, name, trigger, animate) {
        origin = trigger;
        owner.textContent = name || '';
        if (!dialog.open) dialog.showModal();
        document.body.classList.add('fa-statement-open');
        root.querySelector('[data-statement-close]').focus();
        entrance?.cancel();
        if (animate && !reduced.matches) entrance = dialog.animate([{opacity:.6,transform:'scale(.98)'},{opacity:1,transform:'scale(1)'}],{duration:180,easing:'cubic-bezier(.23,1,.32,1)'});
        load(url);
    }
    root.addEventListener('click', event => {
        const trigger = event.target.closest('[data-statement-open]');
        if (!trigger || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        open(trigger.href, trigger.dataset.accountName, trigger, event.detail > 0);
    });
    root.querySelector('[data-statement-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const box = dialog.getBoundingClientRect();
        if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => {++generation; request?.abort(); entrance?.cancel(); document.body.classList.remove('fa-statement-open'); origin?.focus();});
    content.addEventListener('click', event => {
        const retry = event.target.closest('[data-statement-retry]');
        if (retry) {load(lastUrl); return;}
        const link = event.target.closest('[data-statement-pagination] a,[data-statement-reset]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey) return;
        event.preventDefault(); load(link.href);
    });
    content.addEventListener('submit', event => {
        const form = event.target.closest('[data-statement-form]');
        if (!form) return;
        event.preventDefault();
        const from = form.elements.namedItem('from');
        const to = form.elements.namedItem('to');
        to.setCustomValidity(from.value && to.value && to.value < from.value ? root.dataset.dateError : '');
        if (!form.reportValidity()) return;
        const url = new URL(form.action, location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        load(url.href);
    });
    content.addEventListener('input', event => {
        if (event.target.name === 'from' || event.target.name === 'to') event.target.form?.elements.namedItem('to')?.setCustomValidity('');
    });
    if (root.dataset.openStatement) open(root.dataset.openStatement, '', null, false);
})();
