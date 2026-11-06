(() => {
    'use strict';
    const chat = document.querySelector('[data-support-chat]');
    if (!chat) return;
    const log = chat.querySelector('[data-chat-log]');
    const form = chat.querySelector('[data-chat-form]');
    const input = form.querySelector('textarea');
    const files = form.querySelector('[data-chat-files]');
    const selection = form.querySelector('[data-chat-selection]');
    const error = form.querySelector('[data-chat-error]');
    const send = form.querySelector('[data-chat-send]');
    const status = chat.querySelector('[data-chat-status]');
    const latest = chat.querySelector('[data-chat-new]');
    let polling = false;
    let sending = false;
    let completed = false;
    let pendingFiles = [];
    const previewUrls = new Set();
    function clearPreviews() {
        previewUrls.forEach(url => URL.revokeObjectURL(url));
        previewUrls.clear();
    }
    const seen = new Set(Array.from(log.querySelectorAll('[data-message-id]'), el => Number(el.dataset.messageId)));
    const bottom = () => { log.scrollTop = log.scrollHeight; latest.hidden = true; };
    const showError = message => { error.textContent = message; error.hidden = !message; };
    function append(messages, force = false) {
        const nearBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 90;
        let added = false;
        messages.forEach(message => {
            if (seen.has(Number(message.id))) return;
            seen.add(Number(message.id));
            const article = document.createElement('article');
            article.dataset.messageId = message.id;
            article.className = 'support-chat-message ' + (message.sender_type === 'admin' ? 'is-support' : 'is-own');
            const sender = document.createElement('strong');
            sender.textContent = message.sender_type === 'admin' ? 'فريق الدعم' : 'أنت';
            const body = document.createElement('p');
            body.textContent = message.body || '';
            article.append(sender, body);
            (message.attachments || []).forEach((file, index) => {
                if (!file.path) return;
                let url;
                try { url = new URL(file.path, location.origin); } catch (_) { return; }
                if (!['https:', 'http:'].includes(url.protocol)) return;
                const item = document.createElement('figure'); item.className = 'support-chat-sent-file';
                const isImage = /\.(jpe?g|png|gif|webp)$/i.test(url.pathname);
                const link = document.createElement('a');
                link.href = url.href; link.target = '_blank'; link.rel = 'noopener';
                link.className = isImage ? 'support-chat-image-link' : 'support-chat-file';
                if (isImage) {
                    const image = document.createElement('img');
                    image.src = url.href; image.alt = 'صورة مرفقة'; image.loading = 'lazy';
                    image.className = 'support-chat-sent-image'; link.append(image);
                } else link.textContent = 'عرض المرفق';
                const download = document.createElement('a');
                download.className = 'support-chat-download'; download.textContent = 'تنزيل المرفق';
                download.href = chat.dataset.download.replace('MESSAGE', encodeURIComponent(message.id)).replace('/0/download', '/' + index + '/download');
                download.download = '';
                item.append(link, download); article.append(item);
            });
            const time = document.createElement('time');
            time.textContent = message.created_at;
            article.append(time); log.append(article); added = true;
        });
        if (added) {
            chat.querySelector('[data-chat-empty]').hidden = true;
            if (nearBottom || force) bottom(); else latest.hidden = false;
        }
    }
    async function refresh() {
        if (polling || completed || document.hidden) return;
        polling = true;
        try {
            const response = await fetch(chat.dataset.feed, {headers: {'Accept': 'application/json'}, cache: 'no-store', signal: AbortSignal.timeout(15000)});
            if (!response.ok) throw new Error();
            const data = await response.json();
            if (data.activation_completed || data.activation_approved) {
                completed = true;
                const result = chat.querySelector('[data-activation-result]');
                const approved = !!data.activation_approved;
                status.hidden = true; status.textContent = '';
                chat.querySelectorAll(':scope > :not([data-activation-result])').forEach(el => el.hidden = true);
                clearPreviews(); pendingFiles = [];
                result.hidden = false; result.classList.toggle('is-rejected', !approved);
                result.querySelector('[data-activation-heading]').textContent = data.message || (approved ? 'تم تفعيل الحساب بنجاح' : 'لم تتم الموافقة على تفعيل الحساب');
                result.querySelector('[data-activation-description]').textContent = approved ? 'جارٍ نقلك إلى الرئيسية…' : 'جارٍ العودة إلى المحادثات…';
                const link = result.querySelector('[data-activation-continue]');
                const destination = new URL(data.redirect_url, location.origin);
                if (destination.origin === location.origin) link.href = destination.href;
                result.focus({preventScroll:true});
                window.setTimeout(() => location.replace(link.href), 2200);
                return;
            }
            append(data.messages || []);
            status.hidden = true; status.textContent = '';
        } catch (_) { status.hidden = false; status.textContent = 'تعذر التحديث · نحاول الاتصال مجددًا'; }
        finally { polling = false; }
    }
    function renderFiles() {
        clearPreviews();
        selection.replaceChildren();
        pendingFiles.forEach((file, index) => {
            const item = document.createElement('span');
            item.className = 'support-chat-selected-file';
            if (/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
                const image = document.createElement('img');
                const url = URL.createObjectURL(file);
                previewUrls.add(url);
                image.src = url; image.alt = file.name;
                image.className = 'support-chat-selected-image';
                item.append(image);
            }
            const name = document.createElement('span'); name.textContent = file.name;
            const remove = document.createElement('button');
            remove.type = 'button'; remove.textContent = 'إزالة'; remove.disabled = sending;
            remove.setAttribute('aria-label', 'إزالة ' + file.name);
            remove.addEventListener('click', () => { pendingFiles.splice(index, 1); renderFiles(); });
            item.append(name, remove); selection.append(item);
        });
    }
    files.addEventListener('change', () => {
        pendingFiles.push(...Array.from(files.files)); files.value = ''; renderFiles();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (sending) return;
        showError('');
        if (!input.value.trim() && !pendingFiles.length) { showError('اكتب رسالة أو اختر مرفقًا للإرسال.'); return; }
        if (pendingFiles.some(file => file.size > 6000 * 1024)) {
            showError('حجم كل ملف يجب ألا يتجاوز 6 ميجابايت.'); return;
        }
        sending = true; send.disabled = true; form.setAttribute('aria-busy', 'true');
        renderFiles();
        input.disabled = true; files.disabled = true;
        try {
            // Send files individually so PHP's per-request upload count cannot truncate a large selection.
            let first = true;
            do {
                const payload = new FormData();
                payload.append('_token', form.querySelector('[name="_token"]').value);
                if (first && input.value.trim()) payload.append('comment', input.value);
                if (pendingFiles.length) payload.append('image[]', pendingFiles[0]);
                const response = await fetch(form.action, {method: 'POST', body: payload, headers: {'Accept': 'application/json'}, signal: AbortSignal.timeout(60000)});
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'تعذر الإرسال. تحقق من الملفات المتبقية وحاول مجددًا.');
                if (!data.conversation) throw new Error('تعذر الإرسال. حدّث الصفحة ثم حاول مجددًا.');
                append([data.conversation], true);
                if (first) input.value = '';
                if (pendingFiles.length) pendingFiles.shift();
                first = false; renderFiles();
            } while (pendingFiles.length);
        } catch (failure) { showError(failure.message === 'Failed to fetch' ? 'تعذر الاتصال. تحقق من ظهور الرسالة قبل إعادة الإرسال.' : failure.message); }
        finally { sending = false; renderFiles(); send.disabled = false; input.disabled = false; files.disabled = false; form.removeAttribute('aria-busy'); input.focus(); }
    });
    latest.addEventListener('click', bottom);
    log.addEventListener('scroll', () => { if (log.scrollHeight - log.scrollTop - log.clientHeight < 90) latest.hidden = true; });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('online', refresh);
    window.addEventListener('pagehide', clearPreviews);
    window.addEventListener('pageshow', () => { if (pendingFiles.length) renderFiles(); });
    bottom(); refresh(); window.setInterval(refresh, 5000);
})();
