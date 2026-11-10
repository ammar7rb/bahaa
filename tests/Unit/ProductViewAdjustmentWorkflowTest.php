<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\Settings\ProductViewsController;
use App\Models\Admin;
use App\Services\ProductViewCountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductViewAdjustmentWorkflowTest extends TestCase
{
    public function test_confirmed_increment_is_per_product_and_repeated_submission_does_not_repeat_it(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id(); $t->string('added_by'); $t->unsignedBigInteger('user_id'); $t->string('name'); $t->timestamps();
        });
        Schema::create('sellers', fn (Blueprint $t) => $t->id());
        Schema::create('product_view_adjustments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('product_id'); $t->unsignedBigInteger('admin_id');
            $t->unsignedInteger('amount'); $t->timestamps();
        });
        $migration = require base_path('database/migrations/2026_11_11_000001_extend_product_view_adjustments.php');
        $migration->up();
        try {
            DB::table('products')->insert([
                ['id' => 1, 'added_by' => 'seller', 'user_id' => 5, 'name' => 'A'],
                ['id' => 2, 'added_by' => 'seller', 'user_id' => 5, 'name' => 'B'],
            ]);
            DB::table('sellers')->insert(['id' => 5]);
            $admin = new Admin(); $admin->id = 9;
            auth('admin')->setUser($admin);
            $key = '5afcde46-1415-4fa6-a2ca-d073b4ba9758';
            $preview = ['key' => $key, 'vendor_id' => 5, 'selection' => 'selected',
                'amount' => 7, 'reason' => 'تصحيح إداري موثق', 'created_at' => now()->timestamp,
                'products' => [
                    ['id' => 1, 'name' => 'A', 'actual' => 0, 'added' => 0, 'total' => 0, 'result' => 7],
                    ['id' => 2, 'name' => 'B', 'actual' => 0, 'added' => 0, 'total' => 0, 'result' => 7],
                ]];
            $session = new Store('test', new ArraySessionHandler(60));
            $session->start();
            $request = Request::create('/admin/settings/product-views/apply', 'POST', ['key' => $key]);
            $request->setLaravelSession($session);
            $session->put('product_view_preview', $preview);
            $controller = new ProductViewsController();
            $controller->apply($request, app(ProductViewCountService::class));
            $this->assertSame(2, DB::table('product_view_adjustments')->count());
            $this->assertSame(7, app(ProductViewCountService::class)->totals(1)['added']);
            $this->assertSame(7, app(ProductViewCountService::class)->totals(2)['added']);
            $session->put('product_view_preview', $preview);
            $controller->apply($request, app(ProductViewCountService::class));
            $this->assertSame(2, DB::table('product_view_adjustments')->count());
            $this->assertSame(1, DB::table('product_view_adjustment_batches')->count());
            $this->assertSame('تصحيح إداري موثق', DB::table('product_view_adjustments')->first()->reason);
            DB::table('vendor_product_unpromoted_visits')->insert([
                'product_id' => 1, 'seller_id' => 5, 'visits' => 4,
            ]);
            $this->assertSame(['actual' => 4, 'added' => 7, 'total' => 11],
                app(ProductViewCountService::class)->totals(1));
            for ($id = 3; $id <= 30; $id++) DB::table('products')->insert([
                'id' => $id, 'added_by' => 'seller', 'user_id' => 5, 'name' => 'P'.$id,
            ]);
            $pageRequest = Request::create('/admin/settings/product-views/preview', 'POST', [
                'vendor_id' => 5, 'selection' => 'page', 'page' => 2, 'amount' => 1,
                'reason' => 'معاينة الصفحة الثانية',
            ]);
            $pageRequest->setLaravelSession($session);
            $controller->preview($pageRequest, app(ProductViewCountService::class));
            $this->assertEqualsCanonicalizing([1, 2, 3, 4, 5],
                array_column($session->get('product_view_preview')['products'], 'id'));
        } finally {
            auth('admin')->forgetUser();
            $migration->down(); Schema::dropIfExists('product_view_adjustments');
            Schema::dropIfExists('products'); Schema::dropIfExists('sellers');
        }
    }
}
