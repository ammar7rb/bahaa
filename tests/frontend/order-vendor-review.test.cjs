const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function setup(customized = false) {
    const input = (value, dataset = {}) => ({value, dataset, readOnly: false, events: {}, addEventListener(name, fn) { this.events[name] = fn; }});
    const mode = input('amount'), amount = input('100'), percent = input('10'), shipping = input('25');
    const delivery = input('2026-12-10'), payment = input('2026-12-15'), custom = input('');
    custom.checked = customized;
    const output = {dataset: {sale: '200'}, textContent: ''};
    const lines = [0, 1].map(index => {
        const fields = ['customer-shipping', 'vendor-shipping', 'delivery', 'payment'].map(key => input('original', {lineField:key, default:index === 0 ? '40' : '0'}));
        return {fields, querySelectorAll: () => fields};
    });
    const controls = {'[name="vendor_pricing_mode"]': mode, '[name="vendor_order_amount"]': amount,
        '[name="company_commission_percent"]': percent, '[name="shipping_seller_entitlement"]': shipping,
        '[name="shipping_expected_delivery_date"]': delivery, '[name="shipping_settlement_due_at"]': payment,
        '[data-vendor-total]': output, '[data-custom-lines]': custom};
    const form = {dataset:{}, querySelector: selector => controls[selector], querySelectorAll: () => lines, addEventListener() {}};
    vm.runInNewContext(fs.readFileSync(path.resolve(__dirname, '../../public/assets/back-end/js/admin/order-vendor-review.js'), 'utf8'), {
        document:{addEventListener: (_, fn) => fn(), getElementById: () => form}, Intl,
    });
    return {mode, amount, percent, shipping, delivery, payment, custom, output, lines};
}

test('common dates and shipping reach every line without duplicate shipping', () => {
    const state = setup();
    assert.deepEqual(state.lines[0].fields.map(x => x.value), ['40','25','2026-12-10','2026-12-15']);
    assert.deepEqual(state.lines[1].fields.map(x => x.value), ['0','0','2026-12-10','2026-12-15']);
    state.shipping.value = '35';
    state.delivery.value = '2026-12-20';
    state.shipping.events.input();
    assert.equal(state.lines[0].fields[1].value, '35');
    assert.equal(state.lines[1].fields[1].value, '0');
    assert.equal(state.lines[1].fields[2].value, '2026-12-20');
    assert.equal(state.output.textContent, new Intl.NumberFormat('ar-EG', {maximumFractionDigits:2}).format(135));
});

test('custom line values survive updates and validation redisplay', () => {
    const state = setup(true);
    assert.equal(state.lines[0].fields[1].value, 'original');
    state.lines[0].fields[1].value = '12';
    state.shipping.events.change();
    assert.equal(state.lines[0].fields[1].value, '12');
    assert.equal(state.lines[0].fields[1].readOnly, false);
    state.custom.checked = false;
    state.custom.events.change();
    assert.equal(state.lines[0].fields[1].value, '25');
    assert.equal(state.lines[0].fields[1].readOnly, true);
});

test('percentage mode previews the calculated total and toggles requirements', () => {
    const state = setup();
    state.mode.value = 'percent'; state.mode.events.change();
    assert.equal(state.amount.readOnly, true);
    assert.equal(state.percent.required, true);
    assert.equal(state.output.textContent, new Intl.NumberFormat('ar-EG', {maximumFractionDigits:2}).format(205));
    state.mode.value = 'amount'; state.mode.events.change();
    assert.equal(state.amount.readOnly, false);
    assert.equal(state.amount.required, true);
});
