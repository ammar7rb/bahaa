(() => {
    if (window.sigmaPaymentReviewsBound) return;
    window.sigmaPaymentReviewsBound = true;
    document.addEventListener('click', async event => {
        const copy = event.target.closest('[data-pr-copy]');
        if (copy) {
            const scope = copy.closest('dialog') || copy.closest('.pr-workspace');
            const status = scope?.querySelector('[data-pr-copy-status]');
            try {
                await navigator.clipboard.writeText(copy.dataset.prCopy);
                if (status) { status.hidden = false; status.textContent = copy.dataset.copiedLabel; }
            } catch (_) {
                if (status) { status.hidden = false; status.textContent = copy.dataset.copyFailedLabel; }
            }
            return;
        }
        const trigger = event.target.closest('[data-pr-open]');
        if (trigger) {
            const drawer = document.getElementById(trigger.dataset.prOpen);
            if (!drawer) return;
            drawer.showModal();
            drawer.querySelectorAll('[data-pr-proof-src]').forEach(img => {
                const box = img.closest('.pr-proof');
                img.onload = () => { box.querySelector('[data-pr-proof-loading]').hidden = true; img.hidden = false; };
                img.onerror = () => { box.querySelector('[data-pr-proof-loading]').hidden = true; box.querySelector('[data-pr-proof-error]').hidden = false; };
                img.src = img.dataset.prProofSrc;
                img.removeAttribute('data-pr-proof-src');
            });
        }
        const close = event.target.closest('[data-pr-close]');
        if (close) close.closest('dialog').close();
        if (event.target.matches('dialog.pr-drawer')) {
            const rect = event.target.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) event.target.close();
        }
    });
    document.addEventListener('submit', event => {
        const form = event.target.closest('[data-pr-decision], [data-pr-filter]');
        if (!form) return;
        if (form.dataset.submitting) { event.preventDefault(); return; }
        const reason = form.querySelector('textarea[required]');
        if (reason && !reason.value.trim()) { event.preventDefault(); reason.focus(); return; }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        const root = form.closest('dialog') || form;
        root.querySelectorAll('button[type=submit], button:not([type])').forEach(button => { button.disabled = true; });
        const button = form.querySelector('[data-loading-label]');
        if (button) { button.dataset.originalLabel = button.textContent; button.textContent = button.dataset.loadingLabel; }
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-submitting]').forEach(form => {
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            (form.closest('dialog') || form).querySelectorAll('button').forEach(button => {
                button.disabled = false;
                if (button.dataset.originalLabel) button.textContent = button.dataset.originalLabel;
            });
        });
    });
})();
