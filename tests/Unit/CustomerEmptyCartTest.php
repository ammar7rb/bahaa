<?php
namespace Tests\Unit;

use App\Http\Controllers\Web\WebController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CustomerEmptyCartTest extends TestCase
{
    public function test_a_signed_in_customer_can_open_an_empty_cart_without_being_sent_to_login(): void {
        if (!defined('VIEW_FILE_NAMES')) define('VIEW_FILE_NAMES',include(base_path('resources/themes/default/file_names.php')));
        app('view')->addLocation(base_path('resources/themes/default'));
        $customer=new User();$customer->id=123;$customer->setAppends([]);
        $this->actingAs($customer,'customer');
        Cache::put('cache_cart_list_for_customer_123',collect(),60);
        $controller=(new \ReflectionClass(WebController::class))->newInstanceWithoutConstructor();
        $response=$controller->shop_cart(Request::create('/shop-cart'));
        $this->assertInstanceOf(\Illuminate\Contracts\View\View::class,$response);
        $this->assertSame(VIEW_FILE_NAMES['cart_list'],$response->name());
    }

    public function test_a_guest_can_view_an_empty_cart_when_guest_checkout_is_disabled(): void
    {
        if (!defined('VIEW_FILE_NAMES')) define('VIEW_FILE_NAMES', include(base_path('resources/themes/default/file_names.php')));
        app('view')->addLocation(base_path('resources/themes/default'));
        session()->put('guest_id', 456);
        Cache::put('cache_cart_list_for_guest_456', collect(), 60);
        $controller = (new \ReflectionClass(WebController::class))->newInstanceWithoutConstructor();
        $response = $controller->shop_cart(Request::create('/shop-cart'));
        $this->assertFalse(auth('customer')->check());
        $this->assertInstanceOf(\Illuminate\Contracts\View\View::class, $response);
        $this->assertSame(VIEW_FILE_NAMES['cart_list'], $response->name());
    }

    public function test_cart_template_compiles_to_valid_php(): void
    {
        $template = file_get_contents(base_path('resources/themes/theme_aster/theme-views/cart/cart-details.blade.php'));
        $compiled = app('blade.compiler')->compileString($template);
        $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE));
    }
}
