document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-care-cart-remove]');
    if (!button || button.disabled) return;
    button.disabled = true;
    if (typeof removeProductFromCartList === 'function') removeProductFromCartList(button.dataset.careCartRemove);
    else button.disabled = false;
});
