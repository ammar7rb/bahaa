(() => {
    'use strict';
    const form = document.getElementById('care-credit-form');
    if (!form) return;
    const button = form.querySelector('button');
    const status = document.getElementById('care-credit-status');
    const arabic = document.documentElement.lang.startsWith('ar') || document.documentElement.dir === 'rtl';
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (button.disabled) return;
        button.disabled = true;
        button.textContent = button.dataset.loading;
        form.setAttribute('aria-busy', 'true');
        status.hidden = true;
        let sent = false;
        try {
            const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.request_id) {
                throw new Error([401,403,419].includes(response.status)
                    ? (arabic ? 'حدّث الصفحة وسجّل الدخول لإرسال طلبك.' : 'Refresh the page and sign in to submit your request.')
                    : (response.status === 429
                        ? (arabic ? 'انتظر قليلًا قبل إعادة المحاولة.' : 'Please wait a moment before trying again.')
                        : (arabic ? 'تعذّر إرسال طلبك. حاول مرة أخرى.' : 'Unable to submit your request. Please try again.')));
            }
            sent = true;
            status.textContent = arabic ? 'تم تلقي طلبك، وستقوم إدارة الائتمان بالتواصل معكم في أقرب وقت.' : 'Your request has been received. Our credit team will contact you as soon as possible.';
            button.hidden = true;
        } catch (error) {
            status.textContent = error.message || (arabic ? 'تحقق من اتصالك وحاول مرة أخرى.' : 'Check your connection and try again.');
        } finally {
            form.removeAttribute('aria-busy');
            if (!sent) {button.disabled = false; button.textContent = button.dataset.label;}
            status.hidden = false;
            status.focus();
        }
    });
})();
