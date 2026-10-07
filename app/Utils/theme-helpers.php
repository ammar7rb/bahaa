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
        return env('WEB_THEME') == null ? 'default' : env('WEB_THEME');
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
