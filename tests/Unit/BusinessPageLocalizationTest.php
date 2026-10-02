<?php

namespace Tests\Unit;

use App\Http\Requests\Admin\BusinessPageAddRequest;
use App\Models\BusinessPage;
use App\Services\BusinessPageService;
use App\Repositories\BusinessPageRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BusinessPageLocalizationTest extends TestCase
{
    private function content(): array
    {
        return [
            'ar' => ['title' => 'من نحن', 'description' => '<p>تفاصيل الصفحة بالعربية</p>'],
            'en' => ['title' => 'About Us', 'description' => '<p>English page details</p>'],
        ];
    }

    public function test_cached_model_and_api_serialization_follow_each_request_language(): void
    {
        $page = new BusinessPage(['title' => 'Legacy title', 'description' => 'Legacy content', 'translations' => $this->content()]);
        $page->setAppends([]);
        $cached = unserialize(serialize($page));
        foreach (['ar', 'en', 'sa', 'ar-EG', 'en-US'] as $locale) {
            app()->setLocale($locale);
            $language = in_array($locale, ['ar', 'sa', 'ar-EG']) ? 'ar' : 'en';
            $this->assertSame($this->content()[$language]['title'], $cached->title);
            $this->assertSame($this->content()[$language]['description'], $cached->toArray()['description']);
            $this->assertSame($this->content(), $cached->toArray()['translations']);
        }
    }

    public function test_existing_content_is_preserved_until_translations_are_entered(): void
    {
        $page = new BusinessPage(['title' => 'About Us', 'description' => '<p>Existing text</p>']);
        app()->setLocale('ar');
        $this->assertSame('About Us', $page->title);
        $this->assertSame('<p>Existing text</p>', $page->description);
    }

    public function test_public_api_returns_the_requested_language_from_the_same_cache(): void
    {
        $page = new BusinessPage(['id' => 1, 'title' => 'About Us', 'description' => 'Legacy', 'slug' => 'about-us', 'default_status' => 1, 'translations' => $this->content()]);
        $page->setAppends([]);
        \Illuminate\Support\Facades\Cache::put(CACHE_FOR_BUSINESS_PAGES_LIST, new \Illuminate\Database\Eloquent\Collection([$page]), 60);
        foreach (['ar', 'en'] as $language) {
            $request = \App\Http\Requests\Request::create('/api/v1/business-pages?type=default');
            $request->headers->set('lang', $language);
            $response = (new \App\Http\Middleware\APILocalizationMiddleware)->handle($request, function ($request) {
                return response()->json((new \App\Http\Controllers\RestAPI\v1\ConfigController)->getBusinessPagesList($request));
            });
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame($this->content()[$language]['title'], $response->getData(true)[0]['title']);
            $this->assertSame($this->content()[$language]['description'], $response->getData(true)[0]['description']);
        }
    }

    public function test_update_preserves_custom_and_default_urls_and_required_status(): void
    {
        foreach (['about-us', 'privacy-policy', 'my-existing-custom-page'] as $slug) {
            $request = new Request(['title' => 'New English Title', 'description' => 'English content', 'slug' => $slug, 'translations' => $this->content()]);
            $data = (new BusinessPageService)->getPageUpdateData($request);
            $this->assertSame($slug, $data['slug']);
            $this->assertSame($this->content(), $data['translations']);
            if ($slug !== 'my-existing-custom-page') {
                $this->assertSame(1, $data['status']);
            }
        }
    }

    public function test_both_languages_are_required_and_empty_editor_markup_is_rejected(): void
    {
        $request = new class extends BusinessPageAddRequest {
            public function exposedRules(): array { return $this->translationRules(); }
        };
        $valid = ['translations' => $this->content()];
        $validator = Validator::make($valid, $request->exposedRules());
        $request->replace($valid);
        $request->withValidator($validator);
        $this->assertTrue($validator->passes());

        $invalid = $valid;
        $invalid['translations']['ar']['description'] = '<p><br></p><p>&nbsp;</p>';
        $request->replace($invalid);
        $validator = Validator::make($invalid, $request->exposedRules());
        $request->withValidator($validator);
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('translations.ar.description'));
        unset($invalid['translations']['en']);
        $this->assertTrue(Validator::make($invalid, $request->exposedRules())->fails());
    }

    public function test_repository_persists_json_translations_and_migration_is_reversible(): void
    {
        Schema::create('business_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->text('description');
            $table->timestamps();
        });
        $migration = require database_path('migrations/2026_10_02_000001_add_translations_to_business_pages.php');
        try {
            $migration->up();
            $page = BusinessPage::withoutEvents(fn () => BusinessPage::create(['title' => 'About Us', 'slug' => 'about-us', 'description' => 'Original']));
            BusinessPage::withoutEvents(fn () => (new BusinessPageRepository(new BusinessPage))->updateWhere(['id' => $page->id], ['translations' => $this->content()]));
            $page->refresh();
            $this->assertSame($this->content(), $page->translations);
            $this->assertSame('about-us', $page->slug);
            $this->assertCount(1, (new BusinessPageRepository(new BusinessPage))->getListWhere(searchValue: 'من نحن', dataLimit: 'all'));
            $migration->down();
            $this->assertFalse(Schema::hasColumn('business_pages', 'translations'));
        } finally {
            Schema::dropIfExists('business_pages');
        }
    }
}
