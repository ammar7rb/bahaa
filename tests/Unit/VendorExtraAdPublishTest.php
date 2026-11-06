<?php

namespace Tests\Unit;

use App\Http\Middleware\VendorProductWorkspaceBoundary;
use App\Models\Seller;
use App\Models\SellerPackage;
use App\Services\SellerLedgerService;
use App\Services\SellerPackagePurchaseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VendorExtraAdPublishTest extends SellerPackagePaymentsRetryTest
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['vendor_advertising.enabled' => false]);
        (require database_path('migrations/2026_10_14_000001_create_vendor_advertising_slots.php'))->up();
        (require database_path('migrations/2026_10_15_000001_create_vendor_extra_ad_purchases.php'))->up();
        DB::table('vendor_extra_ad_settings')->insert(['id' => 1, 'unit_price' => 200, 'max_slots' => 100, 'enabled' => true]);
        Schema::create('products', function (Blueprint $table) {
            $table->id(); $table->string('added_by'); $table->unsignedBigInteger('user_id'); $table->integer('request_status')->default(1); $table->integer('status')->default(1);
        });
        (require database_path('migrations/2026_10_18_030000_add_vendor_slot_reservations.php'))->up();
        Schema::create('vendor_product_workspaces', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seller_id'); $table->unsignedBigInteger('product_id'); $table->unsignedBigInteger('private_reference');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('vendor_product_workspaces');
        Schema::dropIfExists('vendor_slot_creation_receipts');
        Schema::dropIfExists('products');
        (require database_path('migrations/2026_10_15_000001_create_vendor_extra_ad_purchases.php'))->down();
        (require database_path('migrations/2026_10_14_000001_create_vendor_advertising_slots.php'))->down();
        parent::tearDown();
    }

    private function requestForPublish(float $funds = 4000): Request
    {
        config(['vendor_advertising.enabled' => true]);
        session()->put('local', 'en');
        $seller = Seller::findOrFail(1);
        $plan = SellerPackage::create(['name' => 'Publish', 'slug' => 'publish-'.Str::random(8), 'package_price' => 3000,
            'product_limit' => 5, 'product_duration_days' => 30, 'package_validity_days' => 30, 'search_promotion_limit' => 0,
            'homepage_promotion_limit' => 0, 'search_promotion_duration_days' => 0, 'homepage_promotion_duration_days' => 0, 'coupon_limit' => 0, 'status' => true]);
        app(SellerLedgerService::class)->post(1, 'funding', 'publish-fund', [['bucket' => 'operating', 'direction' => 'credit', 'amount' => $funds]]);
        $cycle = app(SellerPackagePurchaseService::class)->purchaseWithPaymentsBalance($seller, $plan, (string) Str::uuid())['subscription'];
        config(['vendor_product_workspace.enabled' => true, 'vendor_advertising.creation_slots_enabled' => true, 'vendor_advertising.extra_publish_enabled' => true]);
        $request = Request::create('/api/v3/seller/product/add-new', 'POST', ['extra_ad_publish' => 1, 'extra_ad_subscription_id' => $cycle->id,
            'extra_ad_expected_price' => 200, 'extra_ad_request_key' => (string) Str::uuid()]);
        $route = new Route('POST', 'api/v3/seller/product/add-new', ['uses' => 'App\\Http\\Controllers\\RestAPI\\v3\\seller\\ProductController@add_new']);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        return $request;
    }

    public function test_failed_product_response_rolls_back_charge_and_extra_slot(): void
    {
        $request = $this->requestForPublish();
        $response = app(VendorProductWorkspaceBoundary::class)->handle($request, fn () => response()->json(['message' => 'invalid'], 422), 1);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(1000.0, app(SellerLedgerService::class)->summary(1)['operating']);
        $this->assertSame(0, DB::table('vendor_extra_ad_purchases')->count());
        $this->assertSame(5, DB::table('vendor_advertising_slots')->count());
    }

    public function test_successful_publication_charges_once_and_retry_is_idempotent(): void
    {
        $request = $this->requestForPublish();
        $boundary = app(VendorProductWorkspaceBoundary::class);
        $next = function (Request $request) {
            DB::table('vendor_slot_creation_receipts')->insert(['seller_id' => 1, 'slot_id' => $request->input('slot_id'),
                'token_hash' => hash('sha256', $request->input('slot_reservation_token')), 'product_id' => 10, 'created_at' => now()]);
            return response()->json(['saved' => true]);
        };
        $this->assertSame(200, $boundary->handle($request, $next, 1)->getStatusCode());
        $this->assertSame(200, $boundary->handle($request, fn () => throw new \RuntimeException('retry executed'), 1)->getStatusCode());
        $this->assertSame(800.0, app(SellerLedgerService::class)->summary(1)['operating']);
        $this->assertSame(1, DB::table('vendor_extra_ad_purchases')->count());
    }

    public function test_insufficient_balance_does_not_open_a_slot_or_create_a_product(): void
    {
        $request = $this->requestForPublish(3100);
        $response = app(VendorProductWorkspaceBoundary::class)->handle($request, fn () => throw new \RuntimeException('product creation ran'), 1);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('extra_ad_payment', $response->getData(true)['errors'][0]['code']);
        $this->assertSame(100.0, app(SellerLedgerService::class)->summary(1)['operating']);
        $this->assertSame(0, DB::table('vendor_extra_ad_purchases')->count());
        $this->assertSame(5, DB::table('vendor_advertising_slots')->count());
    }
}
