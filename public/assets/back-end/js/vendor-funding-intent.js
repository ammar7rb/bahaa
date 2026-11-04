'use strict';
document.querySelectorAll('[data-funding-intent]').forEach(root => {
    const kind = root.querySelector('[data-purchase-kind]');
    const plan = root.querySelector('[data-funding-package]');
    const update = () => {
        ['package', 'extra'].forEach(type => {
            const group = root.querySelector('[data-purchase-' + type + ']');
            group.hidden = kind.value !== type;
            group.disabled = kind.value !== type;
        });
        root.querySelector('[data-purchase-amount]').value = plan.selectedOptions[0]?.dataset.price || '';
    };
    kind.addEventListener('change', update);
    plan.addEventListener('change', update);
    update();
});
