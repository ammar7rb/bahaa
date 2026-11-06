(() => {
    'use strict';
    const dialog = document.getElementById('vendor-invoice-dialog');
    const open = document.querySelector('[data-invoice-open]');
    if (!dialog || !open) return;
    const content = dialog.querySelector('[data-invoice-content]');
    const loading = content.textContent;
    async function load() {
        content.textContent = loading;
        try {
            const response = await fetch(dialog.dataset.url, {headers: {'Accept': 'text/html'}, credentials: 'same-origin'});
            if (!response.ok || response.redirected) throw new Error('load_failed');
            content.innerHTML = await response.text();
        } catch (_) {
            content.replaceChildren();
            const link = document.createElement('a');
            link.href = open.dataset.invoiceListUrl;
            link.textContent = open.dataset.invoiceError;
            content.append(link);
        }
    }
    open.addEventListener('click', () => { dialog.showModal(); load(); });
    dialog.querySelector('[data-invoice-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
})();

(() => {
    'use strict';
    const form = document.querySelector('[data-sales-transfer]');
    if (!form) return;
    const dialog = form.querySelector('[data-transfer-dialog]');
    const button = form.querySelector('[data-transfer-quote]');
    const error = form.querySelector('[data-transfer-error]');
    try {
        if (form.dataset.success === '1') sessionStorage.removeItem(form.dataset.storageKey);
        const previous = sessionStorage.getItem(form.dataset.storageKey);
        if (previous) form.elements.request_key.value = previous;
    } catch (_) { /* The server still rejects duplicate request keys. */ }
    button.addEventListener('click', async () => {
        const amount = form.elements.amount;
        if (!amount.reportValidity()) return;
        button.disabled = true; error.textContent = '';
        try {
            const url = new URL(form.dataset.quoteUrl, location.href);
            url.searchParams.set('amount', amount.value);
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || form.dataset.error);
            for (const key of ['fee_type', 'fee_value', 'fee']) form.elements[key].value = data.quote[key];
            form.elements.quoted_amount.value = data.quote.amount;
            form.elements.currency_code.value = data.currency_code;
            form.querySelector('[data-transfer-gross]').textContent = data.display.amount + ' ' + data.currency_code;
            form.querySelector('[data-transfer-fee]').textContent = data.display.fee + ' ' + data.currency_code;
            form.querySelector('[data-transfer-net]').textContent = data.display.net + ' ' + data.currency_code;
            dialog.showModal();
        } catch (e) { error.textContent = e.message || form.dataset.error; }
        finally { button.disabled = false; }
    });
    form.elements.amount.addEventListener('input', () => { form.elements.fee_type.value = ''; });
    form.querySelector('[data-transfer-cancel]').addEventListener('click', () => dialog.close());
    form.addEventListener('submit', (event) => {
        if (!dialog.open || !form.elements.fee_type.value) { event.preventDefault(); return; }
        try {
            sessionStorage.setItem(form.dataset.storageKey, form.elements.request_key.value);
            if (sessionStorage.getItem(form.dataset.storageKey) !== form.elements.request_key.value) throw new Error('storage');
        } catch (_) { event.preventDefault(); dialog.close(); error.textContent = form.dataset.storageError; return; }
        form.querySelector('[data-transfer-confirm]').disabled = true;
    });
})();
