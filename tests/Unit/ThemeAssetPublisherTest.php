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
}
