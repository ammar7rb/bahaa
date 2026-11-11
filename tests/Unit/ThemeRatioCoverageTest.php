<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ThemeRatioCoverageTest extends TestCase
{
    public function test_every_installed_theme_has_image_ratio_hints_used_by_admin_forms(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Library/Constant.php';

        $themes = array_map('basename', glob(dirname(__DIR__, 2) . '/resources/themes/*', GLOB_ONLYDIR));
        $this->assertContains('theme_sigma_care', $themes);

        foreach ($themes as $theme) {
            $this->assertArrayHasKey($theme, THEME_RATIO, "THEME_RATIO is missing {$theme}; admin category/brand/product forms would fail with HTTP 500.");
            foreach (['Category Image', 'Brand Image', 'Product Image', 'Meta Thumbnail', 'Store cover Image'] as $key) {
                $this->assertArrayHasKey($key, THEME_RATIO[$theme], "{$theme} lacks {$key}");
            }
        }
    }
}
