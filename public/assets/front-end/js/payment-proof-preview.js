(() => {
    const dialog = document.getElementById('payment-proof-preview');
    if (!dialog) return;
    const image = dialog.querySelector('[data-proof-image]');
    const loading = dialog.querySelector('[data-proof-loading]');
    const error = dialog.querySelector('[data-proof-error]');
    let trigger;
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-proof-open]');
        if (button) {
            const url = new URL(button.dataset.proofOpen, location.href);
            if (url.origin !== location.origin) return;
            trigger = button;
            image.hidden = true;
            error.hidden = true;
            loading.hidden = false;
            image.onload = () => { loading.hidden = true; image.hidden = false; };
            image.onerror = () => { loading.hidden = true; error.hidden = false; };
            image.src = url.href;
            dialog.showModal();
            document.body.classList.add('proof-preview-open');
        }
        if (event.target.closest('[data-proof-close]')) dialog.close();
        if (event.target === dialog) {
            const box = dialog.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
        }
    });
    dialog.addEventListener('close', () => {
        image.onload = image.onerror = null;
        image.removeAttribute('src');
        image.hidden = true;
        document.body.classList.remove('proof-preview-open');
        if (trigger?.isConnected) trigger.focus();
    });
})();
