const operationsDirtyForms = new Set();
const operationsMessage = document.querySelector('[data-unsaved-message]')?.dataset.unsavedMessage;
function operationsMarkDirty(form) {
    if (!form || !operationsMessage) return;
    operationsDirtyForms.add(form);
    const status = form.querySelector('[data-dirty-state]'); if (status) status.hidden = false;
}
document.querySelectorAll('[data-operations-form]').forEach(form => {
    form.addEventListener('input', () => operationsMarkDirty(form));
    form.addEventListener('change', () => operationsMarkDirty(form));
    form.addEventListener('submit', event => {
        if (form.dataset.submitting) { event.preventDefault(); return; }
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
        const others = [...operationsDirtyForms].filter(item => item !== form);
        if (others.length && !window.confirm(operationsMessage)) { event.preventDefault(); return; }
        operationsDirtyForms.clear();
        form.dataset.submitting = '1';
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; button.setAttribute('aria-busy', 'true'); });
    });
});
window.addEventListener('beforeunload', event => {
    if (!operationsDirtyForms.size) return;
    event.preventDefault(); event.returnValue = '';
});
document.querySelectorAll('[data-assigned-list]').forEach(list => {
    list.addEventListener('click', event => {
        const button = event.target.closest('button[data-action]'); if (!button) return;
        const row = button.closest('li');
        if (button.dataset.action === 'remove') row.remove();
        if (button.dataset.action === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
        if (button.dataset.action === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
        operationsManualSelection(list); operationsSync(list); operationsMarkDirty(list.closest('form'));
    });
    operationsSync(list);
});
function operationsManualSelection(list) {
    const section = list.closest('section');
    if (!section) return;
    section.querySelector('[name="source_mode"]').value = 'manual';
    section.querySelector('[data-original-products]')?.remove();
    list.querySelectorAll('input').forEach(input => { input.disabled = false; });
}
document.querySelectorAll('.operations-section [name="source_mode"]').forEach(select => {
    select.addEventListener('change', () => {
        if (select.value === 'manual') operationsManualSelection(select.closest('section').querySelector('[data-assigned-list]'));
    });
});
function operationsSync(list) {
    [...list.children].forEach((row, index) => {
        row.querySelector('[data-action="up"]').disabled = index === 0;
        row.querySelector('[data-action="down"]').disabled = index === list.children.length - 1;
    });
}
const operationsPicker = document.querySelector('[data-product-picker]');
let operationsPickerSection;
let operationsPickerOpener;
function operationsRefreshPicker() {
    if (!operationsPickerSection) return;
    const ids = new Set([...operationsPickerSection.querySelectorAll('[data-product-id]')].map(row => row.dataset.productId));
    operationsPicker.querySelectorAll('[data-add-editorial-product]').forEach(button => {
        const selected = ids.has(button.dataset.id);
        button.disabled = selected;
        button.setAttribute('aria-pressed', String(selected));
        button.textContent = selected ? button.dataset.selectedLabel : button.dataset.addLabel;
    });
    const label = operationsPickerSection.querySelector('[data-assigned-heading]').textContent;
    operationsPicker.querySelector('[data-picker-count]').textContent = `${label}: ${ids.size}`;
}
document.addEventListener('click', event => {
    const opener = event.target.closest('[data-open-product-picker]');
    if (opener && operationsPicker) {
        operationsPickerSection = document.getElementById(opener.dataset.section);
        if (!operationsPickerSection) return;
        operationsPickerOpener = opener;
        operationsPicker.querySelector('[data-picker-section]').textContent = operationsPickerSection.querySelector('[name="title"]').value;
        operationsRefreshPicker();
        operationsPicker.showModal();
        operationsPicker.querySelector('[name="q"]').focus();
        return;
    }
    if (event.target.closest('[data-close-product-picker]')) { operationsPicker?.close(); return; }
    const button = event.target.closest('[data-add-editorial-product]');
    if (!button || !operationsPickerSection) return;
    const section = operationsPickerSection;
    const list = section.querySelector('[data-assigned-list]');
    if ([...list.children].some(row => row.dataset.productId === button.dataset.id)) return;
    if (list.children.length >= 50) { operationsPicker.querySelector('[data-editorial-state]').textContent = operationsPicker.dataset.limitMessage; return; }
    const row = document.querySelector('[data-assigned-template]').content.firstElementChild.cloneNode(true);
    row.dataset.productId = button.dataset.id;
    row.querySelector('input').value = button.dataset.id;
    row.querySelector('.operations-product-name').textContent = button.dataset.name;
    row.querySelector('.operations-product-vendor').textContent = button.dataset.vendor;
    row.querySelector('img').src = button.dataset.image;
    list.append(row); operationsSync(list);
    operationsManualSelection(list);
    operationsMarkDirty(list.closest('form'));
    operationsRefreshPicker();
});
operationsPicker?.addEventListener('close', () => operationsPickerOpener?.focus());
let operationsSearchController;
async function operationsSearch(url) {
    operationsSearchController?.abort();
    const controller = new AbortController(); operationsSearchController = controller;
    const state = document.querySelector('[data-editorial-state]');
    const catalog = document.querySelector('[data-editorial-catalog]');
    state.textContent = state.dataset.loading; catalog.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(url, {headers: {'Accept': 'application/json'}, signal: controller.signal});
        if (!response.ok) throw new Error('Search failed');
        const data = await response.json();
        if (typeof data.html !== 'string') throw new Error('Invalid search response');
        if (controller.signal.aborted) return;
        // The same-origin server renders escaped product names and account identities.
        catalog.innerHTML = data.html; state.textContent = ''; operationsRefreshPicker();
        // Filters stay inside the picker; section edits and page navigation remain unchanged.
    } catch (error) {
        if (error.name !== 'AbortError') state.textContent = state.dataset.error;
    } finally { if (!controller.signal.aborted) catalog.removeAttribute('aria-busy'); }
}
document.querySelector('[data-editorial-search]')?.addEventListener('submit', event => {
    event.preventDefault();
    const form = event.currentTarget;
    const url = new URL(form.action); url.search = new URLSearchParams(new FormData(form)).toString();
    operationsSearch(url);
});
document.querySelector('[data-editorial-catalog]')?.addEventListener('click', event => {
    const link = event.target.closest('.pagination a'); if (!link) return;
    event.preventDefault(); operationsSearch(new URL(link.href));
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-operations-form][data-submitting]').forEach(form => {
        delete form.dataset.submitting;
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = false; button.removeAttribute('aria-busy'); });
    });
});
