<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SettingsRoutePermissionTest extends TestCase
{
    public function test_sensitive_operations_have_server_side_permission_middleware(): void
    {
        foreach ([
            'admin.settings.index' => 'module:business_settings',
            'admin.business-settings.web-config.update' => 'module:developer_configuration',
            'admin.system-setup.environment-setup' => 'module:developer_configuration',
            'admin.third-party.offline-payment-method.index' => 'module:payment_destinations_manage',
            'admin.monthly-packages.save' => 'module:package_pricing_manage',
            'admin.vendors.packages.update' => 'module:package_pricing_manage',
            'admin.business-settings.shipping-method.egypt-zone-rates.update' => 'module:shipping_pricing_manage',
            'admin.settings.notifications.send' => 'module:notification_broadcast',
            'admin.settings.product-views.apply' => 'module:product_view_adjust',
            'admin.customer.reset-password' => 'module:customer_password_reset',
            'admin.vendors.reset-password' => 'module:vendor_password_reset',
        ] as $name => $permission) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains($permission, $route->gatherMiddleware(), $name);
        }
    }
}
