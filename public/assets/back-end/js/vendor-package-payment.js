'use strict';
document.querySelectorAll('[data-package-payment-open]').forEach(button => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.packagePaymentOpen);
        if (dialog && !dialog.open) dialog.showModal();
    });
});
document.querySelectorAll('.vendor-payment-dialog').forEach(dialog => {
    const originalDisabled = new Map([...dialog.querySelectorAll('button')].map(button => [button, button.disabled]));
    const options = dialog.querySelector('.vendor-payment-options');
    const title = dialog.querySelector('[data-payment-title]');
    const back = dialog.querySelector('[data-payment-back]');
    const titles = {balance: 'تأكيد الدفع', instapay: 'الدفع عبر إنستا باي', wallet: 'الدفع بمحفظة إلكترونية'};
    const resetChoice = () => {
        dialog.classList.remove('is-transfer');
        options.hidden = false;
        back.hidden = true;
        title.textContent = 'اختر طريقة الدفع';
        dialog.querySelectorAll('[data-payment-panel]').forEach(panel => { panel.hidden = true; });
        dialog.querySelectorAll('[data-payment-choice]').forEach(option => option.setAttribute('aria-pressed', 'false'));
    };
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        delete dialog.dataset.submitting;
        originalDisabled.forEach((disabled, button) => { button.disabled = disabled; });
        resetChoice();
    });
    back.addEventListener('click', () => { if (!dialog.dataset.submitting) { resetChoice(); options.querySelector('button:not(:disabled)')?.focus(); } });
    dialog.querySelector('[data-payment-close]').addEventListener('click', () => { if (!dialog.dataset.submitting) dialog.close(); });
    dialog.addEventListener('cancel', event => { if (dialog.dataset.submitting) event.preventDefault(); });
    dialog.addEventListener('click', event => { if (event.target === dialog && !dialog.dataset.submitting) { const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close(); } });
    dialog.querySelectorAll('[data-payment-choice]').forEach(button => button.addEventListener('click', () => {
        dialog.classList.toggle('is-transfer', button.dataset.paymentChoice !== 'balance');
        options.hidden = true;
        back.hidden = false;
        title.textContent = titles[button.dataset.paymentChoice];
        dialog.querySelectorAll('[data-payment-choice]').forEach(option => option.setAttribute('aria-pressed', String(option === button)));
        dialog.querySelectorAll('[data-payment-panel]').forEach(panel => { panel.hidden = panel.dataset.paymentPanel !== button.dataset.paymentChoice; });
        back.focus();
    }));
    dialog.querySelectorAll('[data-payment-copy]').forEach(button => button.addEventListener('click', async () => {
        const value = document.getElementById(button.dataset.paymentCopy)?.textContent || '';
        const status = button.closest('.vendor-payment-destination').querySelector('[aria-live]');
        try { await navigator.clipboard.writeText(value); status.textContent = 'تم نسخ بيانات المستلم.'; }
        catch (_) { status.textContent = 'يمكنك تحديد بيانات المستلم ونسخها يدويًا.'; }
    }));
    dialog.querySelectorAll('[data-transfer-method]').forEach(select => select.addEventListener('change', () => {
        select.closest('[data-payment-panel]').querySelectorAll('[data-transfer-form]').forEach(form => { form.hidden = form.dataset.transferForm !== select.value; });
    }));
    dialog.querySelectorAll('[name="payment_proof"]').forEach(input => input.addEventListener('change', () => {
        const file = input.files[0];
        input.setCustomValidity(file && file.size > 5 * 1024 * 1024 ? 'الصورة أكبر من 5 ميجابايت. اختر صورة أصغر.' : '');
        input.closest('.vendor-payment-proof').querySelector('[data-payment-file-name]').textContent = file ? file.name : 'لم يتم اختيار صورة';
    }));
    dialog.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
        if (dialog.dataset.submitting) { event.preventDefault(); return; }
        dialog.dataset.submitting = 'true';
        dialog.querySelectorAll('button').forEach(button => { button.disabled = true; });
    }));
});
