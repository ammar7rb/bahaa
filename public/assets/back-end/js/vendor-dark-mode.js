(function () {
  'use strict';

  var KEY = 'ba_vendor_theme';
  var STOREFRONT_KEY = 'ba_storefront_theme';
  var root = document.documentElement;

  function isDark() {
    return root.dataset.vendorTheme === 'dark';
  }

  function apply(theme) {
    var dark = theme === 'dark';
    root.dataset.vendorTheme = dark ? 'dark' : 'light';
    document.querySelectorAll('[data-vendor-theme-toggle]').forEach(function (button) {
      button.setAttribute('aria-pressed', dark ? 'true' : 'false');
      button.setAttribute('aria-label', dark ? 'Light mode' : 'Dark mode');
    });
  }

  function persist(theme) {
    try {
      localStorage.setItem(KEY, JSON.stringify(theme));
      localStorage.setItem(STOREFRONT_KEY, theme);
    } catch (error) {}
  }

  function getStorefrontTheme() {
    try {
      return localStorage.getItem(STOREFRONT_KEY) === 'dark' ? 'dark' : 'light';
    } catch (error) {
      return root.getAttribute('data-ba-storefront-theme') === 'dark' ? 'dark' : 'light';
    }
  }

  function syncStorefront(theme) {
    root.setAttribute('data-ba-storefront-theme', theme);
    apply(theme);
  }

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-vendor-theme-toggle]');
    if (!toggle) return;
    var theme = isDark() ? 'light' : 'dark';
    persist(theme);
    syncStorefront(theme);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      apply(getStorefrontTheme());
    });
  } else {
    apply(getStorefrontTheme());
  }

  new MutationObserver(function () {
    apply(root.getAttribute('data-ba-storefront-theme') === 'dark' ? 'dark' : 'light');
  }).observe(root, {attributes: true, attributeFilter: ['data-ba-storefront-theme']});
})();
