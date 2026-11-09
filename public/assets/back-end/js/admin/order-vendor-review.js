document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('unified-order-review');
    if (!form) return;
    const mode = form.querySelector('[name="vendor_pricing_mode"]');
    const amount = form.querySelector('[name="vendor_order_amount"]');
    const percent = form.querySelector('[name="company_commission_percent"]');
    const shipping = form.querySelector('[name="shipping_seller_entitlement"]');
    const output = form.querySelector('[data-vendor-total]');
    if (!mode || !amount || !percent || !shipping || !output) return;
    if (form.dataset.reviewReady) return;
    form.dataset.reviewReady = 'true';
    const delivery = form.querySelector('[name="shipping_expected_delivery_date"]');
    const payment = form.querySelector('[name="shipping_settlement_due_at"]');
    const custom = form.querySelector('[data-custom-lines]');
    const lines = Array.from(form.querySelectorAll('[data-review-line]'));
    const amountLocked = amount.readOnly;
    const money = new Intl.NumberFormat('ar-EG', {maximumFractionDigits: 2});
    const update = () => {
        amount.readOnly = amountLocked || mode.value === 'percent';
        amount.required = mode.value !== 'percent';
        percent.required = mode.value === 'percent';
        const net = mode.value === 'percent'
            ? Number(output.dataset.sale) * (1 - Number(percent.value) / 100)
            : Number(amount.value);
        const total = net + Number(shipping.value);
        output.textContent = Number.isFinite(total) && net >= 0 && total >= 0 ? money.format(total) : '—';
        if (custom && delivery && payment) {
            lines.forEach((line, index) => {
                line.querySelectorAll('[data-line-field]').forEach(input => {
                    input.readOnly = !custom.checked;
                    if (custom.checked) return;
                    const values = {
                        'customer-shipping': input.dataset.default,
                        'vendor-shipping': index === 0 ? shipping.value : '0',
                        delivery: delivery.value,
                        payment: payment.value,
                    };
                    input.value = values[input.dataset.lineField] ?? '';
                });
            });
        }
    };
    [mode, amount, percent, shipping, delivery, payment, custom].filter(Boolean).forEach(input => {
        input.addEventListener('input', update);
        input.addEventListener('change', update);
    });
    form.addEventListener('invalid', event => {
        let details = event.target.closest('details');
        while (details) {
            details.open = true;
            details = details.parentElement?.closest('details');
        }
    }, true);
    update();
});
