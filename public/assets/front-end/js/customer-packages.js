(() => {
  document.querySelectorAll('[data-credit-increase]').forEach(form => {
    const offers = JSON.parse(form.dataset.offers || '[]');
    const select = form.querySelector('[name=offer_id]');
    const amount = value => Number(value).toLocaleString('en-EG', {maximumFractionDigits: 3});
    const update = () => {
      const offer = offers.find(item => String(item.id) === select.value);
      if (!offer) return;
      form.querySelectorAll('.package-increase-option').forEach(option => {
        const quantity = Number(option.querySelector('input').value);
        option.querySelector('[data-increase-allowance]').textContent = amount(offer.allowance * quantity) + ' ج.م';
        option.querySelector('[data-increase-price]').textContent = amount(offer.price * quantity);
      });
      const quantity = Number(form.querySelector('[name=quantity]:checked')?.value || 1);
      form.querySelector('[data-increase-total]').textContent = amount(offer.price * quantity);
      form.querySelector('[name=quote_token]').value = offer.quote_token;
      form.querySelector('[name=expected_amount]').value = (offer.price * quantity).toFixed(3);
    };
    form.addEventListener('change', update);
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type=submit]');
      button.disabled = true;
      button.textContent = 'جارٍ الخصم والتفعيل…';
    });
    update();
  });
  document.querySelectorAll('[data-package-transaction]').forEach(form => form.addEventListener('submit', event => {
    if (form.dataset.submitting === '1') { event.preventDefault(); return; }
    form.dataset.submitting = '1';
    const button = form.querySelector('button[type=submit]');
    button.disabled = true; button.textContent = 'جارٍ الخصم والتفعيل…';
  }));
})();
