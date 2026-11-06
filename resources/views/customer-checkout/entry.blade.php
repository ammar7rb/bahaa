<script src="{{ dynamicAsset(path: 'public/assets/front-end/js/egypt-phone-validation.js') }}" defer></script>
@guest('customer')
<script>
document.addEventListener('click', function (event) {
    if (!window.location.pathname.includes('shop-cart')) return;
    const button = event.target.closest('.action-checkout-function, #proceed-to-next-action');
    if (!button || button.disabled || button.classList.contains('custom-disabled')) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    window.location.assign(@json(route('customer.auth.checkout-register')));
}, true);
</script>
@endguest
