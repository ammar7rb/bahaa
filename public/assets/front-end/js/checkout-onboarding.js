(() => {
    'use strict';
    document.querySelectorAll('[data-checkout-onboarding-form]').forEach(form => {
        const button = form.querySelector('button[type="submit"]');
        const status = form.querySelector('[data-checkout-form-status]');
        const label = button.textContent;
        let submitted = false;
        form.addEventListener('submit', event => {
            if (submitted) { event.preventDefault(); return; }
            if (!form.checkValidity()) return;
            submitted = true;
            button.disabled = true;
            button.textContent = button.dataset.loadingLabel;
            form.setAttribute('aria-busy', 'true');
            if (status) status.textContent = button.dataset.loadingLabel;
        });
        window.addEventListener('pageshow', () => {
            submitted = false;
            button.disabled = false;
            button.textContent = label;
            form.removeAttribute('aria-busy');
            if (status) status.textContent = '';
        });
    });
})();
