const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

for (const source of [
    'public/assets/front-end/js/customer-dark-mode.js',
    'resources/themes/theme_aster/public/assets/js/customer-dark-mode.js',
]) {
    const listeners = {}, attributes = {}, bodyAttributes = {}, storage = new Map();
    const icon = {}, buttonAttributes = {};
    const button = {setAttribute: (key, value) => buttonAttributes[key] = value,
        querySelectorAll: () => [icon]};
    const window = {localStorage: {getItem: key => storage.get(key),
        setItem: (key, value) => storage.set(key, value)},
        addEventListener: (name, callback) => listeners[name] = callback};
    const document = {readyState: 'complete',
        documentElement: {setAttribute: (key, value) => attributes[key] = value,
            getAttribute: key => attributes[key]},
        body: {setAttribute: (key, value) => bodyAttributes[key] = value},
        querySelectorAll: selector => selector === '[data-storefront-theme-toggle]' ? [button] : [],
        addEventListener: (name, callback) => listeners[name] = callback};
    vm.runInNewContext(fs.readFileSync(source, 'utf8'), {window, document});
    assert.equal(attributes['data-ba-storefront-theme'], 'light');
    storage.set('ba_storefront_theme', 'dark');
    listeners.storage({key: 'ba_storefront_theme'});
    assert.equal(attributes['data-ba-storefront-theme'], 'dark');
    assert.equal(buttonAttributes['aria-pressed'], 'true');
    if (source.includes('theme_aster')) assert.equal(bodyAttributes.theme, 'dark');
    window.baToggleStorefrontTheme();
    assert.equal(storage.get('ba_storefront_theme'), 'light');
    assert.equal(attributes['data-ba-storefront-theme'], 'light');
    storage.set('ba_storefront_theme', 'dark');
    listeners.pageshow();
    assert.equal(attributes['data-ba-storefront-theme'], 'dark');
    console.log(`PASS: theme changes synchronize across tabs and restored pages: ${source}`);
}
