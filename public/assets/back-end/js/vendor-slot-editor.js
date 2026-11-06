(function () {
    'use strict';
    const editor = document.querySelector('[data-slot-editor]');
    if (!editor) return;
    let submitting = false;
    const form = editor.closest('form');
    form?.addEventListener('submit', () => { submitting = true; }, true);
    form?.addEventListener('input', () => { submitting = false; }, {passive: true});
    window.addEventListener('pagehide', () => {
        if (submitting || editor.dataset.saved === '1') return;
        const data = new URLSearchParams({_token: document.querySelector('meta[name="csrf-token"]').content,
            reservation_token: editor.dataset.token, background: '1'});
        navigator.sendBeacon(editor.dataset.cancel, data);
    });
    window.addEventListener('pageshow', async event => {
        if (!event.persisted) return;
        submitting = false;
        try {
            const response = await fetch(editor.dataset.reserve, {method:'POST', credentials:'same-origin',
                headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                body:JSON.stringify({reservation_token:editor.dataset.token})});
            if (!response.ok) {
                editor.classList.add('alert','alert-danger');
                editor.textContent = 'تغير حجز المساحة. بيانات النموذج باقية هنا؛ راجع المساحات قبل الحفظ.';
            }
        } catch (_) { /* Preserve form contents; the server rejects invalid reservations at save. */ }
    });
})();
