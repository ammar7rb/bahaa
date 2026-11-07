/* Activation conversation state is always scoped to the original case and request. */
(() => {
    'use strict';
    const root = document.getElementById('activation-workspace');
    if (!root || root.dataset.initialized) return;
    window.activationWorkspaceCleanup?.();
    root.dataset.initialized = 'true';
    const labels = JSON.parse(root.dataset.labels);
    const selected = root.querySelector('#aw-selected');
    const list = root.querySelector('#aw-account-list');
    const filters = root.querySelector('#aw-filters');
    const notice = root.querySelector('#aw-notice');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const drafts = new Map(), pending = new Map();
    const previewUrls = new Set();
    function clearPreviews() {
        previewUrls.forEach(url => URL.revokeObjectURL(url));
        previewUrls.clear();
    }
    function renderSelectedFiles(form) {
        clearPreviews();
        const tray = form.querySelector('.aw-files'), input = form.querySelector('[type=file]');
        tray.replaceChildren();
        [...input.files].forEach((file, index) => {
            const item = node('span', 'aw-selected-file');
            if (/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
                const image = node('img', 'aw-selected-image');
                const url = URL.createObjectURL(file); previewUrls.add(url);
                image.src = url; image.alt = file.name; item.append(image);
            }
            const name = node('span', 'aw-selected-filename', file.name); name.title = file.name;
            const remove = node('button', 'aw-remove-attachment', labels.reset);
            remove.type = 'button'; remove.setAttribute('aria-label', labels.reset + ': ' + file.name);
            remove.disabled = !!form.dataset.busy || pending.has(root.dataset.selected);
            remove.addEventListener('click', () => {
                const transfer = new DataTransfer();
                [...input.files].filter((_, i) => i !== index).forEach(file => transfer.items.add(file));
                input.files = transfer.files; saveDraft(); renderSelectedFiles(form);
            });
            item.append(name, remove); tray.append(item);
        });
    }
    let generation = 0, listGeneration = 0, selectionRequest, stopped = false, listUrl = new URL(root.dataset.index);
    const currentUrl = new URL(location.href);
    for (const key of ['q', 'account_type', 'status', 'tab', 'page']) if (currentUrl.searchParams.has(key)) listUrl.searchParams.set(key, currentUrl.searchParams.get(key));
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf };
    const node = (tag, className, text) => { const el = document.createElement(tag); el.className = className; if (text != null) el.textContent = text; return el; };
    async function request(url, options = {}) {
        const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { ...headers, ...options.headers } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(Object.values(data.errors || {}).flat().join('\n') || data.message || labels.save_error);
            error.status = response.status;
            throw error;
        }
        return data;
    }
    const chat = () => selected.querySelector('.aw-chat');
    const active = (id, revision) => !stopped && root.isConnected && root.dataset.selected === String(id) && revision === generation;
    function view(name) {
        root.dataset.view = name;
        if (name === 'profile') {
            const panel = selected.querySelector('.aw-profile');
            if (panel) panel.scrollTop = 0;
            panel?.querySelector('.aw-profile-back')?.focus({ preventScroll: true });
        }
    }
    function highlight() {
        list.querySelectorAll('[data-account]').forEach(a => {
            const isCurrent = a.dataset.account === root.dataset.selected;
            a.classList.toggle('is-selected', isCurrent);
            a.setAttribute('aria-current', String(isCurrent));
        });
    }
    function saveDraft() {
        const form = selected.querySelector('[data-composer]');
        if (form && !pending.has(root.dataset.selected)) drafts.set(root.dataset.selected, { text: form.elements.message.value, files: [...form.querySelector('[type=file]').files] });
    }
    function restoreDraft() {
        const form = selected.querySelector('[data-composer]');
        if (!form) return;
        const id = root.dataset.selected, draft = drafts.get(id);
        if (draft) {
            form.elements.message.value = draft.text;
            const files = new DataTransfer();
            draft.files.forEach(file => files.items.add(file));
            form.querySelector('[type=file]').files = files.files;
        }
        if (pending.has(id)) {
            form.elements.message.disabled = true;
            form.querySelector('[type=file]').disabled = true;
            form.querySelector('[type=submit]').textContent = labels.retry;
            form.querySelector('.aw-form-feedback').textContent = labels.send_error;
        }
        renderSelectedFiles(form);
    }
    async function refreshList(url = listUrl) {
        const revision = ++listGeneration;
        const requestUrl = new URL(url);
        if (root.dataset.selected) requestUrl.searchParams.set('account', root.dataset.selected);
        try {
            const data = await request(requestUrl);
            if (stopped || !root.isConnected || revision !== listGeneration) return;
            list.innerHTML = data.list;
            root.querySelector('[data-account-total]').textContent = data.total;
            listUrl = new URL(url);
            highlight();
        } catch (error) { if (revision === listGeneration) notice.textContent = labels.refresh_error; }
    }
    async function openAccount(url, updateHistory = true) {
        saveDraft();
        selectionRequest?.abort();
        selectionRequest = new AbortController();
        const revision = ++generation;
        selected.setAttribute('aria-busy', 'true');
        notice.textContent = labels.loading;
        try {
            const data = await request(url, { signal: selectionRequest.signal });
            if (revision !== generation || !root.isConnected) return;
            selected.querySelectorAll('dialog[open]').forEach(d => d.close());
            clearPreviews();
            selected.innerHTML = data.html;
            root.dataset.selected = String(data.case_id);
            view('conversation');
            highlight();
            restoreDraft();
            selected.querySelector('.aw-chat-identity h2')?.focus({ preventScroll: true });
            if (updateHistory) {
                const next = new URL(listUrl);
                next.searchParams.set('account', data.case_id);
                history.pushState({ activation: true }, '', next);
            }
            notice.textContent = '';
            await refreshMessages(true);
        } catch (error) {
            if (error.name !== 'AbortError' && revision === generation) {
                notice.replaceChildren(node('span', '', labels.load_error), node('button', 'btn btn-link', labels.retry));
                notice.querySelector('button').onclick = () => openAccount(url, updateHistory);
            }
        } finally { if (revision === generation) selected.setAttribute('aria-busy', 'false'); }
    }
    function renderMessages(data, initial = false) {
        const panel = chat(), box = panel?.querySelector('.aw-messages');
        if (!box) return;
        const nearBottom = initial || box.scrollHeight - box.scrollTop - box.clientHeight < 70;
        const fingerprint = JSON.stringify(data.messages);
        if (box._fingerprint !== fingerprint) {
            const fragment = document.createDocumentFragment();
            for (const message of data.messages) {
                const isAdmin = message.sender_type === 'admin';
                const row = node('article', 'aw-message' + (isAdmin ? ' is-admin' : ''));
                const meta = node('div', 'aw-message-meta');
                meta.append(node('strong', '', isAdmin ? labels.admin : message.sender_type === 'system' ? labels.system : panel.dataset.name));
                const time = node('time', '');
                time.dateTime = message.created_at || '';
                // Server dates are presented as supplied; do not silently shift naive dates to the browser timezone.
                time.textContent = String(message.created_at || '').replace('T', ' ').slice(0, 16);
                meta.append(time);
                const bubble = node('div', 'aw-bubble', message.body);
                const files = node('div', 'aw-message-files');
                for (const [index, attachment] of (message.attachments || []).entries()) {
                    let url;
                    try { url = new URL(attachment.url, location.origin); } catch (_) { continue; }
                    if (!['http:', 'https:'].includes(url.protocol)) continue;
                    const item = node('figure', 'aw-sent-attachment');
                    const isImage = /\.(jpe?g|png|gif|webp)$/i.test(url.pathname) || /^image\/(jpeg|png|gif|webp)$/.test(attachment.mime_type || '');
                    const link = node('a', isImage ? 'aw-sent-image-link' : '', isImage ? null : attachment.name || labels.file);
                    link.href = url.href; link.target = '_blank'; link.rel = 'noopener noreferrer';
                    if (isImage) {
                        const image = node('img', 'aw-sent-image');
                        image.src = url.href; image.alt = attachment.name || labels.file; image.loading = 'lazy';
                        link.append(image);
                    } else {
                        const icon = node('i', 'fi fi-rr-document'); icon.setAttribute('aria-hidden', 'true'); link.prepend(icon);
                    }
                    const download = node('a', 'aw-download', labels.download);
                    download.href = panel.dataset.download.replace('MESSAGE', encodeURIComponent(message.id)).replace('/0/download', '/' + (attachment.attachment_index ?? index) + '/download');
                    download.download = attachment.name || '';
                    item.append(link, download); files.append(item);
                }
                if (files.childNodes.length) bubble.append(files);
                row.append(meta, bubble); fragment.append(row);
            }
            if (!data.messages.length) fragment.append(node('p', 'aw-empty-list', labels.no_messages));
            box.replaceChildren(fragment); box._fingerprint = fingerprint;
            if (nearBottom) box.scrollTop = box.scrollHeight;
        }
        panel._lastMessageId = Math.max(0, ...data.messages.map(m => Number(m.id) || 0));
        if (nearBottom) acknowledge(panel);
    }
    async function acknowledge(panel) {
        if (document.hidden || root.dataset.view !== 'conversation' || selected.querySelector('dialog[open]') || !panel || panel._readId === panel._lastMessageId) return;
        const id = panel.dataset.case, revision = generation, messageId = panel._lastMessageId;
        if (messageId === undefined) return;
        panel._readId = messageId;
        const body = new FormData(); body.set('last_message_id', messageId);
        try {
            await request(panel.dataset.read, { method: 'POST', body });
            if (active(id, revision)) list.querySelector('[data-account="' + id + '"] .aw-unread')?.remove();
        } catch (_) { panel._readId = undefined; }
    }
    let feedBusy = false;
    async function refreshMessages(initial = false) {
        const panel = chat();
        if (!panel || (feedBusy && !initial)) return;
        const id = panel.dataset.case, revision = generation;
        feedBusy = true;
        try {
            const data = await request(panel.dataset.feed);
            if (!active(id, revision) || String(data.case_id) !== id) return;
            panel.querySelector('.aw-connection').textContent = '';
            if (panel._status && panel._status !== data.status) {
                if (!selected.querySelector('dialog[open]') && !selected.querySelector('form[data-busy]')) await reloadAccount();
                return;
            }
            panel._status = data.status;
            renderMessages(data, initial);
        } catch (_) { if (active(id, revision)) panel.querySelector('.aw-connection').textContent = labels.refresh_error; }
        finally { feedBusy = false; }
    }
    async function reloadAccount(dialogId) {
        const id = root.dataset.selected;
        const revision = generation, mode = root.dataset.view;
        const data = await request(root.dataset.index.replace(/\/$/, '') + '/' + id);
        if (!active(id, revision)) return;
        saveDraft();
        selected.querySelectorAll('dialog[open]').forEach(d => d.close());
        clearPreviews();
        selected.innerHTML = data.html;
        restoreDraft(); view(mode);
        if (dialogId) selected.querySelector('#' + dialogId)?.showModal();
        await refreshMessages(true);
    }
    root.addEventListener('click', async event => {
        const account = event.target.closest('[data-account]');
        if (account) { event.preventDefault(); event.stopPropagation(); await openAccount(account.href); return; }
        const pageLink = event.target.closest('.aw-pagination a');
        if (pageLink) { event.preventDefault(); event.stopPropagation(); await refreshList(pageLink.href); return; }
        const reset = event.target.closest('[data-reset]');
        if (reset) { event.preventDefault(); event.stopPropagation(); filters.reset(); filters.querySelectorAll('input,select').forEach(el => el.value = ''); await refreshList(new URL(root.dataset.index)); return; }
        const viewButton = event.target.closest('button[data-view]');
        if (viewButton) { view(viewButton.dataset.view); if (viewButton.dataset.view === 'conversation') acknowledge(chat()); return; }
        const open = event.target.closest('[data-dialog]');
        if (open) { selected.querySelector('#' + open.dataset.dialog)?.showModal(); return; }
        if (event.target.closest('[data-close]')) event.target.closest('dialog')?.close();
    });
    root.addEventListener('change', event => {
        if (event.target.matches('[data-document-type]')) {
            const other = event.target.form.querySelector('[data-other-name]');
            other.hidden = event.target.value !== 'other';
            other.querySelector('input').required = !other.hidden;
        }
        if (event.target.matches('[data-composer] [type=file]')) {
            renderSelectedFiles(event.target.form);
        }
    });
    root.addEventListener('scroll', event => {
        if (event.target.matches('.aw-messages') && event.target.scrollHeight - event.target.scrollTop - event.target.clientHeight < 70) acknowledge(chat());
    }, true);
    root.addEventListener('submit', async event => {
        const form = event.target;
        if (form === filters) {
            event.preventDefault();
            const url = new URL(root.dataset.index);
            new FormData(filters).forEach((value, key) => { if (value) url.searchParams.set(key, value); });
            await refreshList(url); return;
        }
        if (!form.matches('[data-composer],[data-account-form]')) return;
        event.preventDefault();
        if (form.dataset.busy) return;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;
        const id = root.dataset.selected, revision = generation, feedback = form.querySelector('.aw-form-feedback');
        const composer = form.matches('[data-composer]');
        let payload;
        if (composer) {
            saveDraft();
            if (!pending.has(id)) {
                payload = new FormData(form);
                const files = [...form.querySelector('[type=file]').files];
                if (!form.elements.message.value.trim() && !files.length) { form.elements.message.focus(); return; }
                if (files.length > 5 || files.some(f => f.size > 10240 * 1024)) { feedback.textContent = labels.attachment_hint; return; }
                if (!files.length) payload.delete('attachments[]');
                payload.set('request_id', crypto.randomUUID());
                pending.set(id, { body: payload, url: form.action });
            }
            payload = pending.get(id).body;
        } else {
            payload = new FormData(form);
            if (event.submitter?.name) payload.set(event.submitter.name, event.submitter.value);
        }
        form.dataset.busy = 'true';
        const controls = [...form.querySelectorAll('button,input,textarea,select')];
        const previous = controls.map(c => c.disabled);
        controls.forEach(c => c.disabled = true);
        feedback.classList.remove('is-error'); feedback.textContent = composer ? labels.sending : labels.saving;
        let succeeded = false;
        try {
            const data = await request(form.action, { method: 'POST', body: payload });
            succeeded = true;
            if (composer) { pending.delete(id); drafts.delete(id); }
            if (!active(id, revision)) return;
            if (composer) {
                form.reset(); form.querySelector('.aw-files').textContent = ''; feedback.textContent = '';
                renderMessages(data, true);
                form.querySelector('[type=submit]').textContent = labels.send;
            } else {
                const dialogId = form.closest('dialog')?.id;
                notice.textContent = data.message || labels.saved;
                await reloadAccount(dialogId);
                selected.querySelector('dialog[open] .aw-help')?.insertAdjacentElement('afterend', node('p', 'aw-form-feedback', labels.saved));
            }
            await refreshList();
        } catch (error) {
            if (composer && error.status && error.status < 500) pending.delete(id);
            if (!active(id, revision)) return;
            feedback.classList.add('is-error');
            feedback.textContent = composer && pending.has(id) ? labels.send_error : error.message;
            if (composer && pending.has(id)) form.querySelector('[type=submit]').textContent = labels.retry;
        } finally {
            delete form.dataset.busy;
            controls.forEach((c, i) => c.disabled = previous[i]);
            if (composer && form.isConnected) {
                form.elements.message.disabled = pending.has(id);
                form.querySelector('[type=file]').disabled = pending.has(id);
                form.querySelector('[type=submit]').disabled = false;
                if (succeeded) form.elements.message.focus();
                renderSelectedFiles(form);
            }
        }
    });
    const onPop = () => {
        const id = new URL(location.href).searchParams.get('account');
        if (id) openAccount(root.dataset.index.replace(/\/$/, '') + '/' + id, false);
        else view('list');
    };
    const onVisible = () => { if (!document.hidden) { refreshMessages(); refreshList(); } };
    window.addEventListener('popstate', onPop);
    document.addEventListener('visibilitychange', onVisible);
    let tick = 0;
    const timer = setInterval(() => {
        if (!root.isConnected) { window.activationWorkspaceCleanup(); return; }
        if (document.hidden) return;
        refreshMessages();
        if (++tick % 2 === 0) refreshList();
    }, 7000);
    window.activationWorkspaceCleanup = () => {
        stopped = true; clearInterval(timer); selectionRequest?.abort(); clearPreviews();
        window.removeEventListener('popstate', onPop); document.removeEventListener('visibilitychange', onVisible);
    };
    highlight();
    if (root.dataset.selected) { restoreDraft(); refreshMessages(true); }
})();
