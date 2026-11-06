<?php

if (!function_exists('theme_asset')) {
    function theme_asset($path = null): string
    {
        $themeName = theme_root_path();
        if ($themeName === 'theme_sigma_care' && str_starts_with((string) $path, 'public/assets/')) {
            return dynamicAsset(path: $path);
        }
        // Sigma Care owns its visual assets and shares Aster's maintained commerce runtime.
        if ($themeName === 'theme_sigma_care' && !is_file(base_path('resources/themes/theme_sigma_care/public/' . $path))) {
            $themeName = 'theme_aster';
        }
        if ($themeName == 'default') {
            return dynamicAsset(path: $path);
        } else {
            if (DOMAIN_POINTED_DIRECTORY == 'public') {
                return dynamicAsset(path: 'public/themes/' . $themeName . '/public/' . $path);
            } else {
                return dynamicAsset(path: 'resources/themes/' . $themeName . '/public/' . $path);
            }
        }
    }
}

if (!function_exists('theme_base_path')) {
    function theme_base_path(?string $theme = null): string
    {
        $theme ??= theme_root_path();
        return $theme === 'theme_sigma_care' ? 'theme_aster' : $theme;
    }
}

if (!function_exists('theme_root_path')) {
    function theme_root_path(): string
    {
        $activeTheme = env('WEB_THEME') ?: 'default';
        // A short-lived, encrypted admin preview changes only this browser's storefront.
        // Theme selection for admin, APIs and the merchant portal stays unchanged.
        if (app()->runningInConsole() || !is_string($_COOKIE['sigma_storefront_preview'] ?? null)) {
            return $activeTheme;
        }
        return storefront_preview_theme($_COOKIE['sigma_storefront_preview'], request()->getPathInfo()) ?? $activeTheme;
    }
}

if (!function_exists('storefront_preview_theme')) {
    function storefront_preview_theme(string $cookie, string $requestPath): ?string
    {
        $path = '/' . trim($requestPath, '/');
        if (preg_match('~^/(admin|api)(/|$)~', $path)
            || (preg_match('~^/vendor(/|$)~', $path) && !str_starts_with($path, '/vendor/auth/registration'))) {
            return null;
        }
        try {
            $encrypter = app('encrypter');
            $decrypted = $encrypter->decrypt($cookie, false);
            $value = \Illuminate\Cookie\CookieValuePrefix::validate('sigma_storefront_preview', $decrypted, $encrypter->getAllKeys());
            $preview = json_decode($value ?? '', true, flags: JSON_THROW_ON_ERROR);
            if (($preview['theme'] ?? '') === 'theme_sigma_care' && ($preview['expires'] ?? 0) > time()
                && is_file(base_path('resources/themes/theme_sigma_care/file_names.php'))) {
                return 'theme_sigma_care';
            }
        } catch (\Throwable) {
            // Invalid or expired preview cookies fall back to the actual active theme.
        }
        return null;
    }
}

if (!function_exists('getHexToRGBColorCode')) {
    function getHexToRGBColorCode($hex): ?string
    {
        $result = preg_match('/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i', $hex, $matches);
        return $result ? hexdec($matches[1]) . ', ' . hexdec($matches[2]) . ', ' . hexdec($matches[3]) : null;
    }
}

if (!function_exists('getSystemDynamicPartials')) {
    function getSystemDynamicPartials($type = null): mixed
    {
        if ($type == 'analytics_script') {
            return view("system-partials._analytics_script");
        }
        return null;
    }
}

if (!function_exists('formatCompactNumber')) {
    function formatCompactNumber(int|float|null $value = 0): string
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        if ($value >= 1000000000) {
            return round($value / 1000000000, 2) . 'B+';
        } elseif ($value >= 1000000) {
            return round($value / 1000000, 2) . 'M+';
        } elseif ($value >= 1000) {
            return round($value / 1000, 2) . 'K+';
        }

        return (string) $value;
    }
}
