<?php

namespace Tests\Unit;

use App\Services\ThemeAssetPublisher;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

class ThemeAssetPublisherTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'theme-assets-' . bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->temporaryRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    public function test_it_synchronizes_theme_assets_to_the_public_directory(): void
    {
        $themesRoot = $this->temporaryRoot . DIRECTORY_SEPARATOR . 'resources-themes';
        $publicThemesRoot = $this->temporaryRoot . DIRECTORY_SEPARATOR . 'public-themes';
        $sourcePublic = $themesRoot . DIRECTORY_SEPARATOR . 'theme_aster' . DIRECTORY_SEPARATOR . 'public';

        File::ensureDirectoryExists($sourcePublic . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css');
        File::ensureDirectoryExists($sourcePublic . DIRECTORY_SEPARATOR . 'addon');
        File::put($sourcePublic . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css', 'body{display:block}');
        File::put($sourcePublic . DIRECTORY_SEPARATOR . 'addon' . DIRECTORY_SEPARATOR . 'theme_routes.php', '<?php return [];');

        $publisher = new ThemeAssetPublisher($themesRoot, $publicThemesRoot);
        $publisher->sync('theme_aster');

        $this->assertTrue($publisher->isReady('theme_aster'));
        $this->assertSame(
            'body{display:block}',
            File::get($publicThemesRoot . DIRECTORY_SEPARATOR . 'theme_aster' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css')
        );
    }

    public function test_it_rejects_a_theme_name_that_can_escape_the_theme_directory(): void
    {
        $publisher = new ThemeAssetPublisher($this->temporaryRoot, $this->temporaryRoot);

        $this->expectException(InvalidArgumentException::class);

        $publisher->sync('../theme_aster');
    }

    public function test_sigma_care_publishes_its_assets_and_requires_the_inherited_aster_runtime(): void
    {
        $themes = $this->temporaryRoot.'/source';
        $public = $this->temporaryRoot.'/published';
        File::ensureDirectoryExists($themes.'/theme_aster/public/assets');
        File::ensureDirectoryExists($themes.'/theme_aster/public/addon');
        File::ensureDirectoryExists($themes.'/theme_sigma_care/public/assets/css');
        File::put($themes.'/theme_aster/public/addon/theme_routes.php', '<?php return [];');
        File::put($themes.'/theme_sigma_care/public/assets/css/sigma-care.css', 'body{color:#123}');
        $publisher = new ThemeAssetPublisher($themes, $public);
        $this->assertFalse($publisher->isReady('theme_sigma_care'));
        $publisher->sync('theme_sigma_care');
        $this->assertTrue($publisher->isReady('theme_aster'));
        $this->assertTrue($publisher->isReady('theme_sigma_care'));
        $this->assertSame('body{color:#123}', File::get($public.'/theme_sigma_care/public/assets/css/sigma-care.css'));
        File::delete($public.'/theme_aster/public/addon/theme_routes.php');
        $this->assertFalse($publisher->isReady('theme_sigma_care'));
    }
}
