(() => {
  let dialog;
  const format = value => Number(value).toLocaleString('ar-EG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  const checkoutSelector = '#proceed-to-next-action,#proceed-to-payment-action,a[href*="checkout"],button[data-checkout],.proceed-to-checkout';
  function calculate(element, changed) {
    const items = JSON.parse(element.dataset.items || '[]');
    let amount = Number(element.dataset.cart);
    if (items.length) amount = items.reduce((sum, item) => {
      const inputs = [...document.querySelectorAll(`input[data-cart-id="${item.id}"], input[data-cart="${item.id}"]`)];
      const input = inputs.find(node => node.offsetParent !== null) || inputs[0];
      const quantity = changed?.id === item.id ? changed.quantity : (input ? Number(input.value) : item.quantity);
      return sum + item.price * Math.max(0, Number.isFinite(quantity) ? quantity : item.quantity);
    }, 0);
    const available = Number(element.dataset.available), granted = Number(element.dataset.granted), reserved = Number(element.dataset.reserved || 0), projected = Math.round((available - (Math.abs(amount - reserved) < .0005 ? 0 : amount))*1000)/1000;
    const ratio = granted > 0 ? projected / granted : 0;
    element.querySelector('[data-limit-cart]').textContent = format(amount);
    element.querySelector('[data-limit-projected]').textContent = format(projected);
    const status = element.querySelector('[data-limit-status]');
    element.dataset.tone = ratio > .5 ? 'green' : ratio > .1 ? 'orange' : 'red';
    const percent = Math.max(0,Math.min(100,ratio*100));
    element.querySelector('[data-limit-progress]').style.width = `${percent}%`;
    element.querySelector('[role="progressbar"]').setAttribute('aria-valuenow',String(Math.round(percent)));
    status.textContent = projected < 0 ? 'الحد الائتماني غير كافٍ' : ratio > .5 ? 'الحد المتبقي متاح' : ratio > .1 ? 'انخفض الحد المتبقي' : 'الحد المتبقي منخفض';
    element.dataset.insufficient = String(projected < 0);
  }
  const update = changed => {
    document.querySelectorAll('[data-purchasing-limit]').forEach(element => calculate(element, changed));
    const blocked = !!document.querySelector('[data-purchasing-limit][data-insufficient="true"]');
    document.querySelectorAll(checkoutSelector).forEach(control => {
      if (blocked) {
        if (!control.hasAttribute('data-limit-blocked')) control.dataset.limitWasDisabled = String(!!control.disabled);
        control.setAttribute('data-limit-blocked','');control.setAttribute('aria-disabled','true');
        if (control.tagName === 'BUTTON') control.disabled = true;
      } else if (control.hasAttribute('data-limit-blocked')) {
        control.removeAttribute('data-limit-blocked');control.removeAttribute('aria-disabled');
        if (control.tagName === 'BUTTON') control.disabled = control.dataset.limitWasDisabled === 'true' || control.classList.contains('custom-disabled');
        delete control.dataset.limitWasDisabled;
      }
    });
  };
  let refreshTimer;
  function refresh() {
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(async () => {
      if (!document.querySelector('[data-purchasing-limit]')) return;
      try {
        const response = await fetch('/customer/purchasing-limit', {headers: {'Accept':'application/json'}});
        if (!response.ok) return;
        const data = await response.json(); if (!data.limit) return;
        document.querySelectorAll('[data-purchasing-limit]').forEach(element => {
          element.dataset.available = data.limit.available_limit; element.dataset.granted = data.limit.granted_limit;
          element.dataset.cart = data.limit.cart_amount; element.dataset.items = JSON.stringify(data.cart_items || []);
          element.dataset.reserved = data.limit.cart_reserved_amount || 0;
          element.querySelector('[data-limit-available]').textContent = format(data.limit.available_limit);
        }); update();
      } catch (_) { /* Keep the last verified allowance; checkout is validated server-side. */ }
    }, 150);
  }
  document.addEventListener('DOMContentLoaded', () => update());
  document.addEventListener('input', event => { if (event.target.matches('input[data-cart-id],input[data-cart]')) update(); });
  document.addEventListener('click', event => {
    const quantity = event.target.closest('[data-cart-id][data-increment], [data-cart][data-value]');
    if (quantity && quantity.tagName !== 'INPUT') {
      const id = Number(quantity.dataset.cartId ?? quantity.dataset.cart);
      const input = document.querySelector(`input[data-cart-id="${id}"],input[data-cart="${id}"]`);
      if (input) update({id, quantity: Math.max(0, Number(input.value) + Number(quantity.dataset.increment ?? quantity.dataset.value))});
    }
    const proceed = event.target.closest(checkoutSelector);
    const strip = document.querySelector('[data-purchasing-limit][data-insufficient="true"]');
    if (proceed && strip) {
      event.preventDefault(); event.stopImmediatePropagation();
      if (!dialog) {
        dialog = document.createElement('dialog'); dialog.className='credit-limit-dialog'; dialog.setAttribute('dir','rtl'); dialog.setAttribute('aria-label','الحد الائتماني غير كافٍ');
        dialog.innerHTML = '<h2>الحد الائتماني غير كافٍ</h2><p data-available-message></p><p>زد الحد أو اختر باقة أعلى أو قلل المنتجات في سلة المشتريات.</p><a href="/customer/subscription">زيادة الحد أو ترقية الباقة</a> <button type="button">تقليل المنتجات والعودة للسلة</button>';
        dialog.querySelector('button').onclick = () => dialog.close(); document.body.append(dialog);
      }
      dialog.querySelector('[data-available-message]').textContent = `الحد الائتماني المتاح ${format(strip.dataset.available)} ج.م`;
      dialog.showModal();
    }
  }, true);
  if (window.jQuery) window.jQuery(document).ajaxComplete(() => { update(); refresh(); });
  setInterval(refresh, 60000);
})();
