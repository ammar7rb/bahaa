'use strict';
document.querySelectorAll('[data-extra-ad-purchase]').forEach(form => {
    const quantity = form.querySelector('[name="quantity"]');
    const total = form.querySelector('[data-extra-total]');
    const price = Number(form.dataset.unitPrice);
    const render = () => { total.textContent = (price * Math.max(0, Number(quantity.value) || 0)).toFixed(2); };
    quantity.addEventListener('input', render);
    render();
    form.addEventListener('submit', event => {
        if (!window.confirm(`تأكيد طلب ${quantity.value} مساحة بإجمالي نهائي ${total.textContent} EGP؟ تنتهي الإضافات مع الاشتراك الحالي.`)) event.preventDefault();
    });
});
