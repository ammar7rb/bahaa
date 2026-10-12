<?php
namespace Tests\Unit;

use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedicalCategoryIconTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('DOMAIN_POINTED_DIRECTORY')) define('DOMAIN_POINTED_DIRECTORY', 'public');
    }

    public function test_library_icons_exist_and_resolve_for_categories_without_uploaded_images(): void
    {
        foreach (config('medical_category_icons') as $key => $definition) {
            if (!isset($definition['asset'])) continue;
            $this->assertFileExists(base_path($definition['asset']));
            $this->assertSame('image/png', getimagesize(base_path($definition['asset']))['mime']);
            $category = new Category(['medical_icon' => $key, 'icon' => null]);
            $url = $category->icon_full_url;
            $this->assertSame(200, $url['status']);
            $this->assertSame(dynamicAsset($definition['asset']), $url['path']);
        }
    }

    public function test_unknown_library_key_preserves_uploaded_icon_fallback(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('category/test.png', 'image');
        $category = new Category(['medical_icon' => 'unknown', 'icon' => 'test.png']);
        $this->assertSame(200, $category->icon_full_url['status']);
        $this->assertSame('test.png', $category->icon_full_url['key']);
        $this->assertSame(404, (new Category(['medical_icon' => 'unknown']))->icon_full_url['status']);
    }
}
