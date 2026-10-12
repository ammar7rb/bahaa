<meta name="color-scheme" content="light">
<script>
    (function () {
        var root = document.documentElement;
        root.dataset.vendorTheme = 'light';
        root.dataset.baStorefrontTheme = 'light';
        root.style.colorScheme = 'light';
        root.style.backgroundColor = '#f7f9fc';
        try {
            localStorage.setItem('ba_vendor_theme', JSON.stringify('light'));
            localStorage.setItem('6v_vendor_v2_theme', JSON.stringify('light'));
            localStorage.setItem('ba_storefront_theme', 'light');
        } catch (error) {}
    })();
</script>
