<?php

namespace Tests\Unit;

use App\Models\BusinessPage;
use App\Services\LegalContentSanitizer;
use Tests\TestCase;

class LegalContentSanitizerTest extends TestCase
{
    public function test_legal_html_preserves_basic_formatting_and_removes_active_content(): void
    {
        $html = '<p onclick="alert(1)">نص <strong>مهم</strong> <a href="javascript:alert(2)">رابط</a></p><script>alert(3)</script>';
        $clean = app(LegalContentSanitizer::class)->clean($html);
        $this->assertStringContainsString('<strong>مهم</strong>', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $page = new BusinessPage(['slug' => 'privacy-policy', 'description' => $html,
            'translations' => ['ar' => ['description' => $html]]]);
        $this->assertStringNotContainsString('<script', $page->translatedContent('description', 'ar'));
        $this->assertStringNotContainsString('javascript:', $page->translations['ar']['description']);
    }
}
