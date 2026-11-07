(() => {
    'use strict';
    const deletion = document.getElementById('deletion-dialog');
    const frame = deletion?.querySelector('[data-deletion-frame]');
    document.querySelectorAll('[data-settings-dialog]').forEach(link => link.addEventListener('click', event => {
        const dialog = document.getElementById(link.dataset.settingsDialog);
        if (!dialog || typeof dialog.showModal !== 'function') return;
        event.preventDefault();
        if (dialog === deletion && frame) {
            const url = new URL(link.href, location.href);
            url.searchParams.set('modal', '1');
            frame.src = url.href;
        }
        dialog.showModal();
    }));
    document.querySelectorAll('[data-dialog-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog')?.close()));
    window.addEventListener('message', event => {
        if (event.origin === location.origin && event.source === frame?.contentWindow && event.data?.type === 'sigma-close-deletion') deletion.close();
    });
    frame?.addEventListener('load', () => {
        try {
            const url = new URL(frame.contentWindow.location.href);
            if (url.origin === location.origin && !url.pathname.startsWith('/customer/account-deletion')) window.location.assign(url.href);
        } catch (_) { /* The embedded flow remains safely isolated. */ }
    });
})();
