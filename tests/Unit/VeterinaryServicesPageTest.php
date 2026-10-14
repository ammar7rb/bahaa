<?php

namespace Tests\Unit;

use App\Http\Controllers\RestAPI\v1\ConfigController;
use App\Http\Requests\Request;
use App\Models\BusinessPage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VeterinaryServicesPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('business_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->longText('description')->nullable();
            $table->json('translations')->nullable();
            $table->boolean('status')->default(false);
            $table->boolean('default_status')->default(false);
            $table->timestamps();
        });
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_14_200000_add_veterinary_services_business_page.php');
    }

    public function test_page_is_created_once_and_exposed_in_both_api_languages(): void
    {
        Cache::put(CACHE_FOR_BUSINESS_PAGES_LIST, collect(), 60);
        $migration = $this->migration();
        $migration->up();
        $this->assertFalse(Cache::has(CACHE_FOR_BUSINESS_PAGES_LIST));
        $migration->up();
        $this->assertSame(1, DB::table('business_pages')->count());
        $page = BusinessPage::first();
        $page->setAppends([]);
        $this->assertSame(1, $page->status);
        $this->assertSame(0, $page->default_status);
        Cache::put(CACHE_FOR_BUSINESS_PAGES_LIST, new \Illuminate\Database\Eloquent\Collection([$page]), 60);
        foreach (['ar' => 'خدمات بيطرية', 'en' => 'Veterinary Services'] as $locale => $title) {
            app()->setLocale($locale);
            $pages = (new ConfigController)->getBusinessPagesList(new Request(['type' => 'pages']));
            $this->assertCount(1, $pages);
            $this->assertSame($title, $pages->first()->title);
            $this->assertSame('veterinary-services', $pages->first()->slug);
            $this->assertStringContainsString('<h2>', $pages->first()->description);
        }
    }

    public function test_existing_admin_content_and_unpublished_status_survive_rerun_and_rollback(): void
    {
        DB::table('business_pages')->insert([
            'title' => 'Admin title', 'slug' => 'veterinary-services',
            'description' => 'Admin content', 'status' => 0,
        ]);
        $migration = $this->migration();
        $migration->up();
        $migration->down();
        $this->assertSame(1, DB::table('business_pages')->count());
        $page = DB::table('business_pages')->first();
        $this->assertSame('Admin content', $page->description);
        $this->assertSame('Admin title', $page->title);
        $this->assertEquals(0, $page->status);
    }

    public function test_matching_existing_page_with_another_slug_requires_review_without_duplication(): void
    {
        DB::table('business_pages')->insert(['title' => 'الخدمات البيطرية', 'slug' => 'existing-vet-page']);
        try {
            $this->migration()->up();
            $this->fail('Duplicate page should require review.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('different slug', $exception->getMessage());
            $this->assertSame(1, DB::table('business_pages')->count());
            $this->assertSame('existing-vet-page', DB::table('business_pages')->value('slug'));
        }
    }
}
