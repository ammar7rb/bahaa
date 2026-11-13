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
}
