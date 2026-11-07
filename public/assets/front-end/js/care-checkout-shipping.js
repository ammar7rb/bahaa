(() => {
    'use strict';
    const form = document.getElementById('address-form');
    const options = form?.querySelector('[data-checkout-shipping-options]');
    if (!form || !options) return;
    window.CareCheckoutShipping = true;
    const status = document.getElementById('care-shipping-status');
    const summary = document.getElementById('care-checkout-summary');
    const controls = () => [...document.querySelectorAll('#proceed-to-next-action,[data-checkout-continue]')];
    const selected = () => form.querySelector('[name="preferred_shipping_option"]:checked');
    const isSelected = () => options.dataset.shippingMode === 'legacy'
        ? [...form.querySelectorAll('[name^="legacy_shipping_methods["]')].every(input => input.value)
        : Boolean(selected());
    let revision = 0, timer, edited = false, draft = false, queue = Promise.resolve(), navigating = false;
    function message(text, failure = false) {
        status.textContent = text;
        status.setAttribute('role', failure ? 'alert' : 'status');
        status.classList.toggle('text-danger', failure);
    }
    function busy(value) {
        controls().forEach(control => {
            control.disabled = value;
            control.setAttribute('aria-disabled', String(value));
            control.dataset.shippingBusy = String(value);
        });
        summary?.setAttribute('aria-busy', String(value));
    }
    function invalidate() {
        form.querySelectorAll('[data-shipping-option-price]').forEach(node => { node.textContent = '—'; });
        summary?.querySelectorAll('[data-checkout-update="shipping"],[data-checkout-update="total"]').forEach(node => { node.textContent = '—'; });
        summary?.querySelectorAll('[data-checkout-update="tax"]').forEach(node => { node.hidden = true; });
    }
    function fields() {
        const data = new URLSearchParams($(form).serialize());
        if (edited && !draft && !form.querySelector('[name="update_address"]')?.checked) data.set('shipping_method_id', '0');
        return data;
    }
    async function update(final, version) {
        if (navigating || version !== revision) return false;
        if (!form.checkValidity() || !isSelected()) {
            if (final) { form.reportValidity(); message('أكمل بيانات العنوان واختر طريقة الشحن.', true); }
            return false;
        }
        busy(true);
        message('جارٍ حساب الشحن وتحديث الإجمالي…');
        try {
            const button = controls()[0];
            const data = fields();
            const result = await $.ajax({url: button.dataset.gotoCheckout, method: 'POST',
                headers: {'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content'), 'Accept': 'application/json'},
                data: {physical_product: 'yes', shipping: data.toString(), billing: '', billing_addresss_same_shipping: true, refresh_summary: final ? 0 : 1}});
            if (result.errors || !result.shipping_selected) throw {responseJSON: result};
            if (version !== revision) return false;
            draft = draft || Number(data.get('shipping_method_id')) === 0;
            const id = form.querySelector('#shipping-method-id,#shipping_method_id');
            id.value = result.address_id;
            edited = false;
            if (final) { navigating = true; window.location.assign(button.dataset.checkoutPayment); return true; }
            if (!result.summary_html || !summary) throw new Error('تعذر تحديث الإجمالي. أعد المحاولة قبل الدفع.');
            const page = new DOMParser().parseFromString(result.summary_html, 'text/html');
            const changes = ['shipping', 'tax', 'total-label', 'total'].map(key => ({
                current: [...summary.querySelectorAll(`[data-checkout-update="${key}"]`)],
                next: [...page.querySelectorAll(`[data-checkout-update="${key}"]`)]
            }));
            if (changes.some(change => !change.current.length || !change.next.length)) throw new Error('تعذر تحديث الإجمالي. حاول مرة أخرى.');
            changes.forEach(({current, next}) => current.forEach((node, index) => {
                const replacement = next[index] || next[0];
                node.innerHTML = replacement.innerHTML;
                node.hidden = replacement.hidden;
            }));
            if (result.shipping_option_prices) {
                form.querySelectorAll('[data-shipping-option-price]').forEach(node => {
                    const key = node.dataset.shippingOptionPrice;
                    const available = Object.hasOwn(result.shipping_option_prices, key);
                    node.textContent = available ? result.shipping_option_prices[key] : 'غير متاح لهذا العنوان';
                    form.querySelector(`[name="preferred_shipping_option"][value="${key}"]`).disabled = !available;
                });
            }
            message('تم تحديث تكلفة الشحن والإجمالي. يمكنك متابعة الدفع.');
            return true;
        } catch (error) {
            if (version === revision) {
                invalidate();
                message(window.CheckoutErrors.message(error, 'تعذر تحديث تكلفة الشحن. راجع العنوان وحاول مرة أخرى.'), true);
            }
            return false;
        } finally { if (!navigating && version === revision) busy(false); }
    }
    function schedule(event) {
        if (navigating) return;
        revision++;
        const name = event?.target?.name || '';
        if (name && !name.startsWith('legacy_shipping_methods[') && !['preferred_shipping_option','save_address','update_address'].includes(name)) edited = true;
        clearTimeout(timer);
        invalidate();
        if (name && !['preferred_shipping_option','save_address','update_address'].includes(name)) {
            form.querySelectorAll('[name="preferred_shipping_option"]').forEach(node => { node.disabled = false; });
        }
        message('أكمل العنوان واختر الشحن لتحديث الإجمالي.');
        if (isSelected() && form.checkValidity()) {
            busy(true);
            const version = revision;
            timer = setTimeout(() => { queue = queue.then(() => update(false, version)); }, name === 'preferred_shipping_option' ? 100 : 450);
        } else busy(false);
    }
    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    form.addEventListener('care:saved-address', () => {
        draft = false; edited = false;
        form.querySelector('[name="checkout_draft_key"]').value = crypto.randomUUID();
        schedule();
    });
    document.addEventListener('click', event => {
        const button = event.target.closest('#proceed-to-next-action,[data-checkout-continue]');
        if (!button) return;
        event.preventDefault(); event.stopImmediatePropagation();
        if (navigating || button.disabled || button.dataset.shippingBusy === 'true' || button.classList.contains('custom-disabled')) return;
        clearTimeout(timer);
        const version = revision;
        busy(true);
        queue = queue.then(() => update(true, version)).finally(() => { if (!navigating && version === revision) busy(false); });
    }, true);
    window.addEventListener('pageshow', event => { if (event.persisted) { navigating = false; busy(false); schedule(); } });
    if (isSelected()) schedule();
})();
