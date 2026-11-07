document.addEventListener('DOMContentLoaded', () => {
    const inputs = [...document.querySelectorAll('[data-vendor-extra-image]')];
    const count = document.querySelector('[data-vendor-image-count]');
    inputs.forEach((input) => {
        let previewUrl;
        input.addEventListener('change', () => {
            const image = input.parentElement.querySelector('[data-vendor-image-preview]');
            const placeholder = input.parentElement.querySelector('[data-vendor-image-placeholder]');
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = input.files.length ? URL.createObjectURL(input.files[0]) : null;
            image.hidden = !previewUrl;
            placeholder.hidden = !!previewUrl;
            image.style.backgroundImage = previewUrl ? `url("${previewUrl}")` : '';
            if (count) count.textContent = inputs.filter((field) => field.files.length).length;
        });
    });
    const form = document.getElementById('product_form');
    if (form) form.addEventListener('reset', () => {
        window.setTimeout(() => inputs.forEach((input) => input.dispatchEvent(new Event('change'))), 0);
    });
    const saleType = document.getElementById('sale_unit_type');
    const options = [...document.querySelectorAll('[data-vendor-sale-option]')];
    const syncSaleOptions = () => options.forEach((option) => option.setAttribute('aria-pressed', String(option.dataset.vendorSaleOption === saleType.value)));
    if (saleType && options.length) {
        saleType.hidden = true;
        options[0].parentElement.hidden = false;
        options.forEach((option) => option.addEventListener('click', () => {
            saleType.value = option.dataset.vendorSaleOption;
            saleType.dispatchEvent(new Event('change', { bubbles: true }));
        }));
        saleType.addEventListener('change', syncSaleOptions);
        if (form) form.addEventListener('reset', () => window.setTimeout(() => saleType.dispatchEvent(new Event('change', { bubbles: true })), 0));
        syncSaleOptions();
    }
});
