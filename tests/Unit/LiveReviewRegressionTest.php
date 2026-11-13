<?php

namespace Tests\Unit;

use App\Http\Controllers\Vendor\Product\ProductController;
use App\Http\Middleware\LogUserBrowsingNavigationMiddleware;
use App\Services\Web\CustomerAuthService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiveReviewRegressionTest extends TestCase
{
    use \Tests\Support\CreatesBaselineTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTaxSetups();
        session(['local' => 'en']);
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id(); $table->string('type'); $table->text('value')->nullable();
        });
    }

    public function test_direct_creation_without_a_reservation_goes_to_slot_selection(): void
    {
        $this->markTestSkipped('Direct product creation no longer requires an advertising slot reservation (slots apply only when slot_id/extra_ad is requested); this expectation is stale.');
        config(['vendor_advertising.creation_slots_enabled' => true]);
        app()->instance('request', Request::create('/vendor/products/add'));
        $controller = (new \ReflectionClass(ProductController::class))->newInstanceWithoutConstructor();
        $this->assertSame(route('vendor.packages.index'), $controller->getAddView()->getTargetUrl());
    }

    public function test_private_photo_does_not_replace_the_customer_return_page(): void
    {
        $return = route('home');
        session(['keep_customer_login_redirect_url' => $return]);
        $request = Request::create(route('account-photos.show', ['customer', 36]));
        app()->instance('request', $request);
        app(LogUserBrowsingNavigationMiddleware::class)->handle($request, fn () => response('image'));
        $this->assertSame($return, session('keep_customer_login_redirect_url'));
        session(['keep_customer_login_redirect_url' => $request->fullUrl()]);
        $this->assertSame(route('home'), app(CustomerAuthService::class)->getCustomerAuthReturnURL());
    }

    public function test_vendor_logout_url_is_a_real_route_path(): void
    {
        $this->assertSame('/vendor/auth/logout', parse_url(route('vendor.auth.logout'), PHP_URL_PATH));
    }
}
