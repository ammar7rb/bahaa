(function () {
    'use strict';

    var storageKey = 'ba_storefront_theme';
    var root = document.documentElement;

    function getTheme() {
        try {
            return window.localStorage.getItem(storageKey) === 'dark' ? 'dark' : 'light';
        } catch (error) {
            return root.getAttribute('data-ba-storefront-theme') === 'dark' ? 'dark' : 'light';
        }
    }

    function applyTheme(theme, persist) {
        root.setAttribute('data-ba-storefront-theme', theme);
        if (persist) {
            try {
                window.localStorage.setItem(storageKey, theme);
            } catch (error) {
                // Private browsing can block storage; the active page still switches safely.
            }
        }

        document.querySelectorAll('[data-storefront-theme-toggle]').forEach(function (button) {
            var isDark = theme === 'dark';
            button.setAttribute('aria-pressed', String(isDark));
            button.setAttribute('data-theme', theme);
            button.querySelectorAll('[data-theme-icon]').forEach(function (icon) {
                icon.textContent = isDark ? '\u2600' : '\u263e';
            });
        });
    }

    function initialize() {
        applyTheme(getTheme(), false);
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-storefront-theme-toggle]');
            if (!button) return;
            event.preventDefault();
            applyTheme(getTheme() === 'dark' ? 'light' : 'dark', true);
        });
    }

    window.baToggleStorefrontTheme = function () {
        applyTheme(getTheme() === 'dark' ? 'light' : 'dark', true);
    };
    window.addEventListener('storage', function (event) {
        if (event.key === storageKey || event.key === null) applyTheme(getTheme(), false);
    });
    window.addEventListener('pageshow', function () { applyTheme(getTheme(), false); });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}());

