document.querySelectorAll('.vf-page .vendor-status-form').forEach(form => {
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('button');
        const feedback = form.closest('.vf-order').querySelector('.vf-feedback');
        button.disabled = true;
        feedback.hidden = true;
        try {
            const result = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json'}});
            const body = await result.json();
            if (result.ok && (body.success === 1 || body.status === 1)) { location.reload(); return; }
            feedback.textContent = body.message || 'تعذر تحديث الحالة؛ راجع شروط الشحن وإثبات التسليم.';
            feedback.hidden = false;
        } catch (error) {
            feedback.textContent = 'تعذر الاتصال. حدّث الصفحة للتحقق من الحالة قبل المحاولة مرة أخرى.';
            feedback.hidden = false;
        } finally { button.disabled = false; }
    });
});
