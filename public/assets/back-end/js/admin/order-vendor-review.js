document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('unified-order-review');
    if (!form) return;
    const mode = form.querySelector('[name="vendor_pricing_mode"]');
    const amount = form.querySelector('[name="vendor_order_amount"]');
    const percent = form.querySelector('[name="company_commission_percent"]');
    const shipping = form.querySelector('[name="shipping_seller_entitlement"]');
    const output = form.querySelector('[data-vendor-total]');
    if (!mode || !amount || !percent || !shipping || !output) return;
    const money = new Intl.NumberFormat('ar-EG', {maximumFractionDigits: 2});
    const update = () => {
        const net = mode.value === 'percent'
            ? Number(output.dataset.sale) * (1 - Number(percent.value) / 100)
            : Number(amount.value);
        const total = net + Number(shipping.value);
        output.textContent = Number.isFinite(total) && net >= 0 && total >= 0 ? money.format(total) : '—';
    };
    [mode, amount, percent, shipping].forEach(input => input.addEventListener('input', update));
    update();
});
