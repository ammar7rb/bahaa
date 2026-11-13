<?php

namespace Tests\Unit;

use App\Http\Requests\Request;
use Tests\TestCase;

class MedicalDevicesPageUrlTest extends TestCase
{
    public function test_page_has_clean_url_and_old_business_page_url_redirects_to_it(): void
    {
        $this->assertSame('/medical-devices-accessories', parse_url(route('medical-devices-accessories'), PHP_URL_PATH));
        $request = Request::create('/business-page/medical-devices-accessories');
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $response = app(\App\Http\Controllers\Web\PageController::class)->getPageView($request);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(route('medical-devices-accessories'), $response->getTargetUrl());
    }

    public function test_payment_notice_strip_is_no_longer_included_in_layouts(): void
    {
        foreach (['resources/themes/theme_sigma_care/theme-views/layouts/app.blade.php', 'resources/themes/theme_aster/theme-views/layouts/app.blade.php', 'resources/themes/default/layouts/front-end/app.blade.php'] as $layout) {
            $this->assertStringNotContainsString('customer-payment-notices', file_get_contents(base_path($layout)));
        }
    }

    public function test_business_page_view_compiles_and_grid_lists_only_published_devices(): void
    {
        $compiled = \Illuminate\Support\Facades\Blade::compileString(file_get_contents(base_path('resources/themes/theme_sigma_care/theme-views/pages/business_page.blade.php')));
        $this->assertIsArray(token_get_all($compiled, TOKEN_PARSE));
        $this->assertStringContainsString("medical-devices._grid", $compiled);
        \Illuminate\Support\Facades\Schema::create('company_medical_devices', function ($t) {
            $t->id(); $t->string('name'); $t->text('description')->nullable(); $t->text('specifications')->nullable(); $t->json('images')->nullable();
            $t->boolean('is_published')->default(false); $t->unsignedInteger('sort_order')->default(10); $t->decimal('price', 24, 3)->nullable();
            $t->decimal('report_price', 24, 3)->nullable(); $t->string('device_condition')->nullable(); $t->string('origin_country')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        \App\Models\CompanyMedicalDevice::create(['name' => 'جهاز ظاهر', 'is_published' => true]);
        \App\Models\CompanyMedicalDevice::create(['name' => 'جهاز مخفي', 'is_published' => false]);
        $html = view('medical-devices._grid', ['devices' => \App\Models\CompanyMedicalDevice::published()->paginate(24)])->render();
        $this->assertStringContainsString('جهاز ظاهر', $html);
        $this->assertStringNotContainsString('جهاز مخفي', $html);
    }
}
