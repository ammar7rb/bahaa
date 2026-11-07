(() => {
    'use strict';
    document.addEventListener('change', (event) => {
        if (event.target.matches('#vendor-avatar-input') && event.target.files.length) {
            event.target.form.requestSubmit();
        }
    });
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy-vendor-reference]');
        if (!button) return;
        const strip = button.closest('.vendor-identity-strip');
        const value = strip.querySelector('[data-vendor-reference]').textContent.trim();
        const status = strip.querySelector('[data-vendor-copy-status]');
        const ar = document.documentElement.dir === 'rtl';
        button.disabled = true;
        try {
            let copied = false;
            if (navigator.clipboard?.writeText) {
                try { await navigator.clipboard.writeText(value); copied = true; } catch (_) {}
            }
            if (!copied) {
                const input = document.createElement('textarea');
                input.value = value; input.readOnly = true;
                input.style.cssText = 'position:fixed;inset:0;opacity:0;pointer-events:none';
                document.body.append(input);
                try { input.select(); copied = document.execCommand('copy'); } finally { input.remove(); button.focus({preventScroll:true}); }
            }
            if (!copied) throw new Error('copy_failed');
            status.textContent = ar ? 'تم النسخ' : 'Copied';
        } catch (_) {
            status.textContent = ar ? 'تعذر النسخ، حاول مجددًا' : 'Could not copy. Try again.';
        } finally {
            button.disabled = false;
            window.setTimeout(() => { status.textContent = ''; }, 4000);
        }
    });
})();
