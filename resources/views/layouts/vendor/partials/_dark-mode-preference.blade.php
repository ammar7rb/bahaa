<script>
    (function () {
        try {
            var preference = localStorage.getItem('ba_storefront_theme');
            var theme = preference === 'dark' ? 'dark' : 'light';
            document.documentElement.dataset.vendorTheme = theme;
            document.documentElement.dataset.baStorefrontTheme = theme;
            document.documentElement.style.colorScheme = theme;
            document.documentElement.style.backgroundColor = theme === 'dark' ? '#0f1525' : '#f7f9fc';
        } catch (error) {
            // Private browsing or a disabled storage must not block the page.
        }
    })();
</script>
