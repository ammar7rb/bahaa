<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RouteRegistryIntegrityTest extends TestCase
{
    public function test_routes_can_be_listed_and_have_real_public_controller_actions(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id(); $table->string('type'); $table->text('value')->nullable(); $table->timestamps();
        });
        Schema::create('addon_settings', function (Blueprint $table) {
            $table->id(); $table->string('key_name'); $table->string('settings_type');
        });
        $this->assertSame(0, Artisan::call('route:list', ['--json' => true]));
        $this->assertGreaterThan(1000, count(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)));
        $missing = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction('controller');
            if (!$action || $route->getAction('uses') instanceof \Closure) continue;
            [$controller, $method] = array_pad(explode('@', $action, 2), 2, '__invoke');
            if (!class_exists($controller) || !method_exists($controller, $method)
                || !(new \ReflectionMethod($controller, $method))->isPublic()) {
                $missing[] = $route->uri().' => '.$action;
            }
        }
        $this->assertSame([], $missing);
        foreach (['admin.customer-withdrawals.index', 'admin.seller-payouts.index', 'admin.business-settings.vendor-settings.index', 'vendor.balance.operating-refund'] as $name) {
            $this->assertTrue(Route::has($name), $name);
        }
    }
}
