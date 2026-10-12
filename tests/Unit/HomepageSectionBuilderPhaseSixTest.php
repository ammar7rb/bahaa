<?php

namespace Tests\Unit;

use App\Models\HomepageLayoutVersion;
use App\Services\HomepageSectionBuilderService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomepageSectionBuilderPhaseSixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('homepage_layout_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('theme');
            $table->unsignedInteger('version');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('published_by_admin_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['theme', 'version']);
        });
        Schema::create('homepage_layout_sections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('homepage_layout_version_id');
            $table->string('section_key');
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source_mode')->default('automatic');
            $table->string('automatic_source')->nullable();
            $table->json('product_ids')->nullable();
            $table->json('banner_ids')->nullable();
            $table->string('locale')->default('all');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['homepage_layout_version_id', 'section_key']);
        });
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Schema::dropIfExists('homepage_layout_sections');
        Schema::dropIfExists('homepage_layout_versions');
        parent::tearDown();
    }

    public function test_draft_can_be_configured_published_and_served_in_saved_order(): void
    {
        $builder = app(HomepageSectionBuilderService::class);
        $draft = $builder->ensureDraft('default', 7);
        $input = $draft->sections->mapWithKeys(fn ($section) => [$section->section_key => [
            'is_visible' => $section->section_key === 'categories',
            'sort_order' => $section->section_key === 'categories' ? 5 : $section->sort_order,
            'source_mode' => 'automatic',
            'automatic_source' => $section->automatic_source,
            'locale' => 'all',
            'limit' => 12,
        ]])->all();

        $builder->saveDraft('default', $input, 7, 'Ready for publication');
        $published = $builder->publish('default', 7);
        $layout = $builder->layout('default');

        $this->assertSame(HomepageLayoutVersion::STATUS_PUBLISHED, $published->status);
        $this->assertTrue($builder->readiness('default')['ready']);
        $this->assertTrue($layout['sections']['categories']['is_visible']);
        $this->assertSame(5, $layout['sections']['categories']['sort_order']);
        $this->assertFalse($layout['sections']['main_banner']['is_visible']);
    }

    public function test_publish_rejects_a_layout_without_any_visible_section(): void
    {
        $builder = app(HomepageSectionBuilderService::class);
        $draft = $builder->ensureDraft('theme_aster', 1);
        $input = $draft->sections->mapWithKeys(fn ($section) => [$section->section_key => [
            'is_visible' => false,
            'sort_order' => $section->sort_order,
            'source_mode' => 'automatic',
            'automatic_source' => $section->automatic_source,
            'locale' => 'all',
        ]])->all();
        $builder->saveDraft('theme_aster', $input, 1);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('homepage_layout_requires_one_visible_section');
        $builder->publish('theme_aster', 1);
    }

    public function test_rollback_creates_a_new_published_version_without_mutating_history(): void
    {
        $builder = app(HomepageSectionBuilderService::class);
        $firstDraft = $builder->ensureDraft('default', 1);
        $firstInput = $this->onlyVisible($firstDraft, 'categories', 10);
        $builder->saveDraft('default', $firstInput, 1, 'first');
        $first = $builder->publish('default', 1);

        $secondDraft = $builder->ensureDraft('default', 2);
        $secondInput = $this->onlyVisible($secondDraft, 'featured_deals', 20);
        $builder->saveDraft('default', $secondInput, 2, 'second');
        $builder->publish('default', 2);
        $restored = $builder->rollback('default', $first->fresh(), 3);

        $this->assertGreaterThan($first->version, $restored->version);
        $this->assertSame('published', $restored->status);
        $this->assertTrue($builder->layout('default')['sections']['categories']['is_visible']);
        $this->assertFalse($builder->layout('default')['sections']['featured_deals']['is_visible']);
        $this->assertSame('archived', $first->fresh()->status);
    }

    public function test_both_public_templates_and_theme_activation_use_the_builder_contract(): void
    {
        $default = File::get(resource_path('themes/default/web-views/home.blade.php'));
        $aster = File::get(resource_path('themes/theme_aster/theme-views/home.blade.php'));
        $controller = File::get(app_path('Http/Controllers/Web/HomeController.php'));
        $themeService = File::get(app_path('Services/ThemeService.php'));
        $routes = File::get(base_path('routes/admin/routes.php'));

        $this->assertStringContainsString('data-homepage-section="main_banner"', $default);
        $this->assertStringContainsString('data-homepage-section="main_banner"', $aster);
        $this->assertStringContainsString("layout('default')", $controller);
        $this->assertStringContainsString("layout('theme_aster')", $controller);
        $this->assertStringContainsString('$this->homepageBuilder->readiness', $themeService);
        $this->assertStringContainsString("prefix('homepage-builder')", $routes);
    }

    private function onlyVisible(HomepageLayoutVersion $draft, string $visibleKey, int $order): array
    {
        return $draft->sections->mapWithKeys(fn ($section) => [$section->section_key => [
            'is_visible' => $section->section_key === $visibleKey,
            'sort_order' => $section->section_key === $visibleKey ? $order : $section->sort_order,
            'source_mode' => 'automatic',
            'automatic_source' => $section->automatic_source,
            'locale' => 'all',
        ]])->all();
    }
}
