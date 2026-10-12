(function () {
  'use strict';
  var root = document.documentElement;
  root.dataset.vendorTheme = 'light';
  root.dataset.baStorefrontTheme = 'light';
  root.style.colorScheme = 'light';
  function applyLight() {
    var shell = document.querySelector('.app-v2');
    if (shell) shell.classList.remove('v2-theme-dark');
    document.querySelectorAll('[data-vendor-theme-toggle], #v2-theme-toggle').forEach(function (button) { button.remove(); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', applyLight);
  else applyLight();
})();
