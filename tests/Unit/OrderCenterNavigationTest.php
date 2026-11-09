<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\Order\OrderController;
use Illuminate\Http\Request;
use Tests\TestCase;

class OrderCenterNavigationTest extends TestCase
{
    public function test_existing_order_list_opens_the_center_and_preserves_search(): void
    {
        $request = Request::create('/admin/orders/list/all', 'GET', ['searchValue'=>'100001', 'seller_id'=>'7']);
        $response = app(OrderController::class)->index($request, 'all');
        $this->assertSame(route('admin.orders.center', ['queue'=>'all','q'=>'100001','seller_id'=>'7']), $response->getTargetUrl());
    }

    public function test_delivery_list_preserves_its_filter_in_the_center(): void
    {
        $request = Request::create('/admin/orders/list/out_for_delivery', 'GET');
        $response = app(OrderController::class)->index($request, 'out_for_delivery');
        $this->assertSame(route('admin.orders.center', ['queue'=>'out_for_delivery']), $response->getTargetUrl());
    }

    public function test_changed_blade_views_compile(): void
    {
        foreach (['admin-views.order.center.index', 'layouts.admin.partials.v2._orders-center', 'layouts.admin.partials.v2._side-bar', 'admin-views.order.partials._order-services', 'admin-views.order.partials._timeline', 'admin-views.order.partials._approval-form', 'admin-views.order.admin-gate.show', 'admin-views.order.order-details'] as $view) {
            $compiled = app('blade.compiler')->compileString(file_get_contents(dirname(__DIR__, 2).'/resources/views/'.str_replace('.', '/', $view).'.blade.php'));
            $this->assertNotEmpty($compiled);
            $path = tempnam(sys_get_temp_dir(), 'orders-view-');
            try {
                file_put_contents($path, $compiled);
                exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $code);
                $this->assertSame(0, $code, implode("\n", $output));
            } finally { unlink($path); }
        }
    }
}
