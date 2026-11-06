<?php

namespace Tests\Unit;

use App\Http\Middleware\VendorProductWorkspaceBoundary;
use App\Models\Product;
use App\Models\Seller;
use App\Models\VendorProductWorkspace;
use App\Services\VendorAdvertisingAccessService;
use App\Services\VendorProductWorkspaceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendorProductWorkspaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session()->put('local', 'ar');
        if (!defined('DOMAIN_POINTED_DIRECTORY')) define('DOMAIN_POINTED_DIRECTORY', 'public');
        Schema::create('sellers', fn(Blueprint $t) => [$t->id(), $t->string('activation_status')->nullable(), $t->timestamps()]);
        Schema::create('products', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->string('added_by');
            $t->string('name'); $t->text('details')->nullable(); $t->string('slug')->nullable();
            $t->decimal('unit_price', 18, 3); $t->string('thumbnail')->nullable();
            $t->json('images')->nullable(); $t->integer('status')->default(1);
            $t->integer('request_status')->default(1); $t->integer('current_stock')->default(10);
            $t->string('seller_review_status')->nullable(); $t->timestamp('seller_submitted_at')->nullable(); $t->timestamps();
            $t->integer('featured')->default(0); $t->integer('is_homepage_visible')->default(0);
            $t->text('seller_review_reason')->nullable(); $t->text('denied_note')->nullable(); $t->json('seller_review_changes')->nullable();
            $t->timestamp('seller_reviewed_at')->nullable(); $t->unsignedBigInteger('seller_reviewed_by')->nullable();
            $t->date('production_date')->nullable(); $t->date('expiry_date')->nullable(); $t->boolean('no_production_expiry_dates')->default(true);
        });
        Schema::create('translations', function (Blueprint $t) {
            $t->id(); $t->string('translationable_type'); $t->unsignedBigInteger('translationable_id');
            $t->string('locale'); $t->string('key'); $t->text('value');
        });
        Schema::create('seller_product_review_decisions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('product_id'); $t->unsignedBigInteger('seller_id'); $t->string('status');
            $t->text('reason')->nullable(); $t->json('changed_fields')->nullable(); $t->unsignedBigInteger('admin_id')->nullable(); $t->timestamp('decided_at'); $t->timestamps();
        });
        Schema::create('order_details', fn(Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->decimal('price', 18, 3)]);
        Schema::create('seller_package_subscriptions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->string('payment_status'); $t->string('status');
            $t->timestamp('starts_at')->nullable(); $t->timestamp('expires_at')->nullable();
        });
        Schema::create('vendor_advertising_slots', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('seller_package_subscription_id'); $t->timestamp('starts_at'); $t->timestamp('expires_at');
        });
        (require database_path('migrations/2026_10_17_230000_create_vendor_product_workspaces.php'))->up();
        (require database_path('migrations/2026_10_22_210000_create_vendor_product_submission_revisions.php'))->up();
        config(['vendor_advertising.enabled' => false, 'vendor_product_workspace.enabled' => true]);
    }

    public function test_dashboard_subscription_warning_follows_paid_current_package_without_mutations(): void
    {
        $access = app(VendorAdvertisingAccessService::class);
        $this->assertTrue($access->requiresSubscription(1));
        $id = DB::table('seller_package_subscriptions')->insertGetId(['seller_id' => 1, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay()]);
        $this->assertFalse($access->requiresSubscription(1));
        $this->assertTrue($access->requiresSubscription(2));
        DB::table('seller_package_subscriptions')->where('id', $id)->update(['payment_status' => 'pending']);
        $this->assertTrue($access->requiresSubscription(1));
        DB::table('seller_package_subscriptions')->where('id', $id)->update(['payment_status' => 'paid', 'status' => 'scheduled', 'starts_at' => now()->addDay()]);
        $this->assertTrue($access->requiresSubscription(1));
        DB::table('seller_package_subscriptions')->where('id', $id)->update(['starts_at' => now()->subDay(), 'expires_at' => now()]);
        $this->assertTrue($access->requiresSubscription(1));
        $this->assertSame(1, DB::table('seller_package_subscriptions')->count());
        config(['vendor_product_workspace.enabled' => false]);
        $this->assertFalse($access->requiresSubscription(1));
    }

    public function test_dashboard_banner_has_subscription_link_and_is_hidden_for_eligible_sellers(): void
    {
        $sellerOverview = ['subscription' => ['publishing_requires_subscription' => true]];
        $html = view('vendor-views.dashboard.partials.subscription-banner', compact('sellerOverview'))->render();
        $this->assertStringContainsString('مبروك حسابك الجديد، ابدأ أول 3 إعلانات مجانًا!!! وخلّينا نساعدك في تحقيق أعلى عائد مبيعات.', $html);
        $this->assertStringContainsString('/vendor/packages', $html);
        $folder = 'E:/project/docs/vendor-subscription-dashboard-banner-20261018';
        if (is_dir($folder)) file_put_contents($folder.'/notice-fixture.html', '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public/assets/back-end/css/bootstrap.min.css"><link rel="stylesheet" href="/public/assets/back-end/css/theme.minc619.css"><link rel="stylesheet" href="/public/assets/back-end/css/vendor-workspace.css"><body><div class="app-v2 vendor-shell" style="display:block"><main class="v2-main content container-fluid">'.$html.'</main></div></body></html>');
        $sellerOverview['subscription']['publishing_requires_subscription'] = false;
        $this->assertStringNotContainsString('vendor-subscription-banner', view('vendor-views.dashboard.partials.subscription-banner', compact('sellerOverview'))->render());
    }

    public function test_slot_overview_preserves_private_content_and_reports_only_owned_cycle_statistics(): void
    {
        Schema::table('seller_package_subscriptions', fn (Blueprint $t) => [$t->string('package_name')->nullable(), $t->integer('product_limit')->default(1)]);
        Schema::table('vendor_advertising_slots', fn (Blueprint $t) => [$t->integer('position')->default(1), $t->unsignedBigInteger('extra_ad_purchase_id')->nullable()]);
        Schema::table('order_details', fn (Blueprint $t) => [$t->unsignedBigInteger('seller_id')->nullable(), $t->unsignedBigInteger('order_id')->nullable(), $t->string('delivery_status')->nullable(), $t->timestamp('created_at')->nullable()]);
        Schema::create('vendor_extra_ad_settings', fn (Blueprint $t) => [$t->id(), $t->boolean('enabled'), $t->decimal('unit_price')->nullable(), $t->integer('max_slots')]);
        Schema::create('seller_package_product_metrics', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('seller_id'), $t->unsignedBigInteger('seller_package_subscription_id'), $t->unsignedBigInteger('product_id'), $t->integer('visits')]);
        $product = $this->product();
        $subscription = DB::table('seller_package_subscriptions')->insertGetId(['seller_id' => 1, 'package_name' => 'Configured package', 'product_limit' => 2, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay()]);
        DB::table('vendor_advertising_slots')->insert(['seller_id' => 1, 'product_id' => $product->id, 'seller_package_subscription_id' => $subscription, 'position' => 1, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay()]);
        DB::table('vendor_advertising_slots')->insert(['seller_id' => 1, 'product_id' => null, 'seller_package_subscription_id' => $subscription, 'position' => 2, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay()]);
        DB::table('seller_package_product_metrics')->insert(['seller_id' => 1, 'seller_package_subscription_id' => $subscription, 'product_id' => $product->id, 'visits' => 7]);
        foreach ([[1, 'delivered', now()], [2, 'canceled', now()], [3, 'delivered', now()->subDays(3)]] as [$orderId, $status, $created]) DB::table('order_details')->insert(['product_id' => $product->id, 'seller_id' => 1, 'order_id' => $orderId, 'price' => 100, 'delivery_status' => $status, 'created_at' => $created]);
        DB::table('products')->where('id', $product->id)->update(['name' => 'Public editorial secret', 'unit_price' => 180]);
        try {
            $overview = app(\App\Services\VendorAdvertisingOverviewService::class)->build(Seller::findOrFail(1));
            $this->assertSame(['total' => 2, 'used' => 1, 'available' => 1, 'reserved' => 0, 'included' => 2, 'additional' => 0], $overview['capacity']);
            $this->assertSame('occupied', $overview['slots'][0]['capacity_status']);
            $this->assertSame('available', $overview['slots'][1]['capacity_status']);
            $this->assertSame('Vendor original', $overview['slots'][0]['product']['name']);
            $this->assertSame(100.0, $overview['slots'][0]['product']['unit_price']);
            $this->assertSame(['visits' => 7, 'orders' => 1, 'scope' => 'product_during_subscription'], $overview['slots'][0]['statistics']);
            $this->assertFalse($overview['extra_settings']['enabled']);
            $this->assertNull($overview['extra_settings']['unit_price']);
            $this->assertSame(2, DB::table('vendor_advertising_slots')->count());
            DB::table('seller_package_subscriptions')->where('id', $subscription)->update(['expires_at' => now()]);
            $expired = app(\App\Services\VendorAdvertisingOverviewService::class)->build(Seller::findOrFail(1));
            $this->assertSame('expired', $expired['slots'][0]['capacity_status']);
            $this->assertSame(0, $expired['capacity']['available']);
        } finally { request()->attributes->remove('vendor_workspace_seller_id'); }
    }

    public function test_administration_rejection_releases_extra_slot_and_retains_private_product(): void
    {
        Schema::table('seller_package_subscriptions', fn (Blueprint $t) => [$t->integer('used_product_limit')->default(1), $t->timestamps()]);
        Schema::table('vendor_advertising_slots', fn (Blueprint $t) => [$t->unsignedBigInteger('extra_ad_purchase_id')->nullable(), $t->unsignedInteger('assignment_revision')->default(0), $t->timestamps()]);
        Schema::create('vendor_advertising_slot_history', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('slot_id'), $t->unsignedBigInteger('seller_id'), $t->unsignedBigInteger('previous_product_id')->nullable(), $t->unsignedBigInteger('product_id')->nullable(), $t->timestamp('created_at')]);
        $product = $this->product(1, false);
        $cycle = DB::table('seller_package_subscriptions')->insertGetId(['seller_id' => 1, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay()]);
        DB::table('vendor_advertising_slots')->insert(['seller_id' => 1, 'product_id' => $product->id, 'extra_ad_purchase_id' => 99, 'seller_package_subscription_id' => $cycle, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay()]);
        config(['vendor_advertising.creation_slots_enabled' => true]);
        app(\App\Services\SellerProductReviewService::class)->transition($product, 'rejected', 'Content needs correction', 9);
        $this->assertNull(DB::table('vendor_advertising_slots')->value('product_id'));
        $this->assertSame(0, (int) DB::table('products')->value('status'));
        $this->assertSame('rejected', VendorProductWorkspace::first()->review_state);
        $this->assertSame('Vendor original', VendorProductWorkspace::first()->seller_content['name']);
        $this->assertSame(1, DB::table('products')->count());
        $this->assertFalse(app(VendorAdvertisingAccessService::class)->visible($product));
    }

    private function product(int $seller = 1, bool $legacy = true): Product
    {
        DB::table('sellers')->insertOrIgnore(['id' => $seller, 'activation_status' => 'active']);
        $id = DB::table('products')->insertGetId([
            'user_id' => $seller, 'added_by' => 'seller', 'name' => 'Vendor original',
            'unit_price' => 100, 'details' => 'Private details', 'slug' => 'public-product-secret',
            'thumbnail' => 'original.webp', 'status' => 1, 'request_status' => 1,
        ]);
        $product = Product::withoutGlobalScopes()->findOrFail($id);
        app(VendorProductWorkspaceService::class)->capture($product, $legacy);
        return $product;
    }

    public function test_free_submission_reaches_review_and_publishes_a_separate_customer_copy(): void
    {
        config(['vendor_advertising.creation_slots_enabled' => true]);
        DB::table('sellers')->insert(['id' => 1, 'activation_status' => 'active']);
        $product = Product::create(['user_id' => 1, 'added_by' => 'seller', 'name' => 'Vendor original',
            'details' => 'Private details', 'unit_price' => 100, 'status' => 0, 'request_status' => 0]);
        $review = app(\App\Services\SellerProductReviewService::class);
        $review->submit($product);
        $state = VendorProductWorkspace::firstOrFail();
        $this->assertSame('submitted', $state->review_state);
        $this->assertSame('default_plan_outside_slot', $state->baseline_source);
        $this->assertNull(DB::table('vendor_product_submission_revisions')->value('slot_id'));
        $this->assertSame(0, DB::table('vendor_advertising_slots')->count());
        $this->assertFalse(app(VendorAdvertisingAccessService::class)->visible($product));
        $workspace = app(VendorProductWorkspaceService::class);
        $workspace->saveEditorialDraft($product, ['name' => 'Customer editorial secret', 'unit_price' => 180], 1);
        $published = $review->transition($product, 'approved_published', adminId: 9);
        $this->assertTrue(app(VendorAdvertisingAccessService::class)->visible($published, true));
        $this->assertSame(180.0, (float) DB::table('products')->value('unit_price'));
        $this->assertSame(100.0, (float) $state->fresh()->seller_content['unit_price']);
        request()->attributes->set('vendor_workspace_seller_id', 1);
        $payload = $workspace->privatePayload($product->id, $published->getAttributes());
        $safe = app(VendorProductWorkspaceBoundary::class)->sanitize($payload, [$product->id => $state->private_reference], true);
        $this->assertSame(100.0, (float) $safe['unit_price']);
        $this->assertSame('Vendor original', $safe['name']);
        $this->assertSame($state->private_reference, $safe['id']);
        $this->assertStringNotContainsString('Customer editorial secret', json_encode($safe));
        request()->attributes->remove('vendor_workspace_seller_id');
    }

    public function test_free_submission_still_requires_account_activation(): void
    {
        DB::table('sellers')->insert(['id' => 1, 'activation_status' => 'pending']);
        $this->expectException(ValidationException::class);
        app(VendorAdvertisingAccessService::class)->assertCanCreate(1);
    }

    public function test_api_create_without_slot_runs_atomically_and_returns_only_private_reference(): void
    {
        config(['vendor_advertising.creation_slots_enabled' => true]);
        DB::table('sellers')->insert(['id' => 1, 'activation_status' => 'active']);
        $request = Request::create('/api/v3/seller/products/add', 'POST');
        $route = new \Illuminate\Routing\Route('POST', 'api/v3/seller/products/add', [
            'uses' => 'App\Http\Controllers\RestAPI\v3\seller\ProductController@add_new',
        ]);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        app()->instance('request', $request);
        $response = app(VendorProductWorkspaceBoundary::class)->handle($request, function () {
            $this->assertGreaterThan(0, DB::transactionLevel());
            $product = Product::create(['user_id' => 1, 'added_by' => 'seller', 'name' => 'Free product',
                'unit_price' => 100, 'status' => 0, 'request_status' => 0]);
            app(\App\Services\SellerProductReviewService::class)->submit($product);
            return response()->json(['id' => $product->id, 'name' => $product->name, 'unit_price' => 100]);
        }, 1);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(VendorProductWorkspace::firstOrFail()->private_reference, $response->getData(true)['id']);
        $this->assertSame(0, DB::table('vendor_advertising_slots')->count());
        $this->assertSame('submitted', VendorProductWorkspace::first()->review_state);
    }

    public function test_permanent_legacy_exemption_survives_expiry_without_changing_status(): void
    {
        $product = $this->product();
        $access = app(VendorAdvertisingAccessService::class);
        $this->assertTrue($access->visible($product));
        DB::table('seller_package_subscriptions')->insert(['seller_id' => 1, 'payment_status' => 'paid', 'status' => 'expired', 'starts_at' => now()->subDays(40), 'expires_at' => now()->subDays(10)]);
        $this->assertTrue($access->visible($product));
        $this->assertSame(1, (int) DB::table('products')->value('status'));
        $access->assertCanCreate(1);
        $this->assertSame(1, DB::table('products')->count());
    }

    public function test_catalog_publication_is_independent_of_advertising_slot_expiry(): void
    {
        $product = $this->product(1, false);
        $this->assertTrue(app(VendorAdvertisingAccessService::class)->visible($product));
        $this->assertFalse(app(VendorProductWorkspaceService::class)->hasAdvertisingSlot($product));
        $subscription = DB::table('seller_package_subscriptions')->insertGetId(['seller_id' => 1, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay()]);
        DB::table('vendor_advertising_slots')->insert(['seller_id' => 1, 'product_id' => $product->id, 'seller_package_subscription_id' => $subscription, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay()]);
        $this->assertTrue(app(VendorAdvertisingAccessService::class)->visible($product));
        $this->assertTrue(app(VendorProductWorkspaceService::class)->hasAdvertisingSlot($product));
        DB::table('seller_package_subscriptions')->update(['expires_at' => now()]);
        $this->assertTrue(app(VendorAdvertisingAccessService::class)->visible($product));
        $this->assertFalse(app(VendorProductWorkspaceService::class)->hasAdvertisingSlot($product));
    }

    public function test_permanent_legacy_exemption_does_not_override_explicit_advertisement_suspension(): void
    {
        $product = $this->product();
        VendorProductWorkspace::where('product_id', $product->id)->update(['publication_suspended' => true]);
        $this->assertFalse(app(VendorAdvertisingAccessService::class)->visible($product));
        $this->assertTrue((bool) VendorProductWorkspace::where('product_id', $product->id)->value('legacy_exempt'));
    }

    public function test_pending_price_and_content_do_not_change_published_advertisement(): void
    {
        $product = $this->product();
        $state = app(VendorProductWorkspaceService::class)->saveSellerDraft($product, ['name' => 'Vendor revision', 'unit_price' => 110]);
        $this->assertSame(2, $state->revision);
        $this->assertSame('Vendor original', DB::table('products')->value('name'));
        $this->assertSame(100.0, (float) DB::table('products')->value('unit_price'));
        $this->assertSame(0, (int) DB::table('products')->value('request_status'));
        $this->assertSame(0, (int) DB::table('products')->value('status'));
        $this->assertSame(2, DB::table('vendor_product_submission_revisions')->count());
        $snapshot = app(VendorProductWorkspaceService::class)->snapshotOrderLine(['product_id' => $product->id, 'price' => 150]);
        $this->assertSame(100.0, (float) $snapshot['vendor_unit_price_snapshot']);
        $this->assertSame(150, $snapshot['price']);
    }

    public function test_vendor_projection_keeps_its_details_when_admin_changes_live_product(): void
    {
        $product = $this->product();
        DB::table('products')->where('id', $product->id)->update(['name' => 'Editorial name', 'unit_price' => 150, 'details' => 'Editorial details']);
        request()->attributes->set('vendor_workspace_seller_id', 1);
        $private = Product::withoutGlobalScopes()->findOrFail($product->id);
        $this->assertSame('Vendor original', $private->name);
        $this->assertSame(100.0, $private->unit_price);
        $this->assertSame('Private details', $private->details);
        $this->assertStringStartsWith('AD-', $private->administrative_reference);
        request()->attributes->remove('vendor_workspace_seller_id');
        $this->assertSame('Editorial name', Product::withoutGlobalScopes()->findOrFail($product->id)->getRawOriginal('name'));
    }

    public function test_approval_preserves_both_prices_and_previous_order_snapshots(): void
    {
        $product = $this->product(); $service = app(VendorProductWorkspaceService::class);
        $old = $service->snapshotOrderLine(['product_id' => $product->id, 'price' => 150]);
        $service->saveSellerDraft($product, ['unit_price' => 110]);
        $service->saveEditorialDraft($product, ['name' => 'Customer version', 'unit_price' => 170], 2);
        DB::transaction(function () use ($service, $product) { $service->approve($product); $product->save(); });
        $new = $service->snapshotOrderLine(['product_id' => $product->id, 'price' => 170]);
        $this->assertSame(100.0, (float) $old['vendor_unit_price_snapshot']);
        $this->assertSame(110.0, (float) $new['vendor_unit_price_snapshot']);
        $this->assertSame(170.0, (float) DB::table('products')->value('unit_price'));
        $this->assertSame(2, $new['vendor_content_revision']);
    }

    public function test_stale_editorial_revision_cannot_approve_a_new_vendor_price(): void
    {
        $product = $this->product(); $service = app(VendorProductWorkspaceService::class);
        $service->saveEditorialDraft($product, ['unit_price' => 150], 1);
        $service->saveSellerDraft($product, ['unit_price' => 120]);
        $this->expectException(ValidationException::class);
        $service->approve($product);
    }

    public function test_admin_must_save_an_editorial_copy_for_the_current_revision(): void
    {
        $product = $this->product();
        $this->expectException(ValidationException::class);
        app(VendorProductWorkspaceService::class)->saveEditorialDraft($product, ['unit_price' => 150], 9);
    }

    public function test_private_reference_is_unrelated_to_public_id_and_scoped_to_owner(): void
    {
        $product = $this->product();
        $state = VendorProductWorkspace::firstOrFail();
        $this->assertNotSame($product->id, $state->private_reference);
        $request = Request::create('/vendor/products/view/'.$state->private_reference);
        $route = new \Illuminate\Routing\Route('GET', 'vendor/products/view/{id}', ['uses' => 'App\Http\Controllers\Vendor\Product\ProductController@getView']);
        $route->bind($request); $request->setRouteResolver(fn() => $route);
        $response = app(VendorProductWorkspaceBoundary::class)->handle($request, function ($request) use ($product) {
            $this->assertSame($product->id, $request->route('id'));
            return response()->json(['id' => $product->id, 'name' => 'Vendor original', 'unit_price' => 100, 'slug' => 'secret']);
        }, 1);
        $this->assertSame($state->private_reference, $response->getData(true)['id']);
        $this->assertArrayNotHasKey('slug', $response->getData(true));
        $this->assertNull($request->attributes->get('vendor_workspace_seller_id'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(VendorProductWorkspaceBoundary::class)->handle($request, fn() => response()->json([]), 2);
    }

    public function test_boundary_does_not_rewrite_category_ids_and_scrubs_order_product_snapshots(): void
    {
        $data = ['product' => ['id' => 7, 'name' => 'Private', 'unit_price' => 100, 'slug' => 'secret'], 'category' => ['id' => 7, 'name' => 'Category'], 'product_id' => 7,
            'product_details' => json_encode(['id' => 7, 'name' => 'Private', 'slug' => 'secret'])];
        $out = app(VendorProductWorkspaceBoundary::class)->sanitize($data, [7 => 900000000001]);
        $this->assertSame(7, $out['category']['id']);
        $this->assertSame(900000000001, $out['product']['id']);
        $this->assertStringNotContainsString('secret', $out['product_details']);
    }

    public function test_existing_orders_are_not_blocked_by_expired_advertising_subscription(): void
    {
        $order = new \App\Models\Order(['seller_is' => 'seller', 'seller_id' => 1]);
        $this->assertSame('advertising_only_restriction', app(\App\Services\SellerOrderSubscriptionEligibilityService::class)->reason($order));
    }

    public function test_baseline_retries_cannot_exempt_future_products(): void
    {
        $product = $this->product();
        $this->artisan('vendor:prepare-product-workspaces')->assertExitCode(0);
        $new = $this->product(1, false);
        $this->artisan('vendor:prepare-product-workspaces')->assertExitCode(0);
        $this->assertFalse(VendorProductWorkspace::where('product_id', $new->id)->value('legacy_exempt'));
        $this->assertTrue((bool) VendorProductWorkspace::where('product_id', $product->id)->value('legacy_exempt'));
    }

    public function test_published_revision_is_in_pending_review_filter_and_hidden_from_customers(): void
    {
        $product = $this->product();
        app(VendorProductWorkspaceService::class)->saveSellerDraft($product, ['name' => 'Pending revision']);
        $this->assertTrue(Product::withoutGlobalScopes()->reviewRequestStatus(0)->whereKey($product->id)->exists());
        $this->assertFalse(Product::withoutGlobalScopes()->reviewRequestStatus(1)->whereKey($product->id)->exists());
        $this->assertSame(0, (int) DB::table('products')->value('status'));
        $this->assertFalse(app(VendorAdvertisingAccessService::class)->visible($product));
    }

    public function test_order_product_payload_uses_vendor_content_without_changing_database_snapshot(): void
    {
        $product = $this->product();
        request()->attributes->set('vendor_workspace_seller_id', 1);
        $snapshot = json_encode(['id' => $product->id, 'name' => 'Editorial name', 'unit_price' => 150, 'slug' => 'customer-link']);
        $line = new \App\Models\OrderDetail();
        $line->setRawAttributes(['product_id' => $product->id, 'product_details' => $snapshot], true);
        $private = json_decode($line->product_details, true);
        $this->assertSame('Vendor original', $private['name']);
        $this->assertSame(100.0, (float) $private['unit_price']);
        $this->assertArrayNotHasKey('slug', $private);
        $this->assertSame($snapshot, $line->getRawOriginal('product_details'));
        request()->attributes->remove('vendor_workspace_seller_id');
    }

    public function test_ajax_html_uses_private_links_and_does_not_change_unrelated_category_values(): void
    {
        $boundary = app(VendorProductWorkspaceBoundary::class);
        $html = '<a href="/vendor/products/update/7">Edit</a><a href="/vendor/products/delete-image?id=7&amp;name=a.webp">Remove</a><input name="product_id" value="7"><option value="7">Category</option><a href="/product/secret">View</a>';
        $out = $boundary->sanitize(['htmlView' => $html], [7 => 900000000001]);
        $this->assertStringContainsString('/update/900000000001', $out['htmlView']);
        $this->assertStringContainsString('?id=900000000001', $out['htmlView']);
        $this->assertStringContainsString('<option value="7">', $out['htmlView']);
        $this->assertStringNotContainsString('/product/secret', $out['htmlView']);
    }

    public function test_vendor_cannot_reverse_administrative_suspension(): void
    {
        $product = $this->product();
        DB::table('products')->update(['status' => 0]);
        VendorProductWorkspace::query()->update(['review_state' => 'suspended']);
        request()->attributes->set('vendor_workspace_seller_id', 1);
        $product = Product::withoutGlobalScopes()->findOrFail($product->id);
        $product->status = 1;
        try {
            $product->save();
            $this->fail('An administrative suspension must require administration approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('publication', $exception->errors());
            $this->assertSame(0, (int) DB::table('products')->value('status'));
        } finally {
            request()->attributes->remove('vendor_workspace_seller_id');
        }
    }

    public function test_customer_catalog_cache_is_bypassed_at_subscription_expiry(): void
    {
        $catalog = new class {
            use \App\Traits\CacheManagerTrait;
            public function read(callable $callback) { return $this->rememberAdvertisingCatalog('phase3-catalog-test', 3600, $callback); }
        };
        \Illuminate\Support\Facades\Cache::put('phase3-catalog-test', 'expired advertisement', 3600);
        $this->assertSame('fresh eligibility', $catalog->read(fn () => 'fresh eligibility'));
    }

    public function test_active_vendor_without_package_can_submit_through_api_boundary(): void
    {
        $this->product();
        $request = Request::create('/api/v3/seller/products/add', 'POST');
        $response = app(VendorProductWorkspaceBoundary::class)->handle($request, function () {
            app(VendorAdvertisingAccessService::class)->assertCanCreate(1);
            return response()->json([]);
        }, 1);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayNotHasKey('action', $response->getData(true));
    }

    public function test_order_calculations_keep_public_price_while_response_uses_private_vendor_copy(): void
    {
        $product = $this->product();
        $reference = VendorProductWorkspace::firstOrFail()->private_reference;
        DB::table('products')->update(['unit_price' => 150]);
        $request = Request::create('/api/v3/seller/pos/place-order', 'POST', ['cart' => json_encode([['id' => $reference, 'quantity' => 1]])]);
        $route = new \Illuminate\Routing\Route('POST', 'api/v3/seller/pos/place-order', ['uses' => 'App\Http\Controllers\RestAPI\v3\seller\POSController@place_order']);
        $route->bind($request); $request->setRouteResolver(fn () => $route);
        $originalRequest = app('request');
        app()->instance('request', $request);
        try {
            $response = app(VendorProductWorkspaceBoundary::class)->handle($request, function ($request) use ($product) {
                $this->assertSame($product->id, json_decode($request->input('cart'), true)[0]['id']);
                $domainProduct = Product::withoutGlobalScopes()->findOrFail($product->id);
                $this->assertSame(150.0, $domainProduct->unit_price);
                return response()->json(['product' => ['id' => $product->id, 'unit_price' => 150, 'name' => 'Public name']]);
            }, 1);
            $this->assertSame(100.0, (float) $response->getData(true)['product']['unit_price']);
            $this->assertSame($reference, $response->getData(true)['product']['id']);
        } finally {
            app()->instance('request', $originalRequest);
        }
    }

    public function test_auxiliary_editorial_action_cannot_stand_in_for_full_administration_review(): void
    {
        $product = $this->product(); $service = app(VendorProductWorkspaceService::class);
        $service->saveEditorialDraft($product, ['unit_price' => 150], 1);
        $service->saveEditorialDraft($product, ['thumbnail' => 'replacement.webp'], 1, false);
        $this->expectException(ValidationException::class);
        $service->approve($product);
    }

    public function test_editorial_approval_publishes_exact_price_and_replaces_category(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->unsignedBigInteger('sub_sub_category_id')->nullable();
            $table->text('category_ids')->nullable();
        });
        $product = $this->product();
        DB::table('products')->where('id', $product->id)->update([
            'unit_price' => 149.99, 'category_id' => 10, 'category_ids' => json_encode([['id' => '10', 'position' => 1]]),
        ]);
        $workspace = app(VendorProductWorkspaceService::class);
        $workspace->saveEditorialDraft($product, [
            'unit_price' => 1, 'category_id' => 20, 'sub_category_id' => null,
            'sub_sub_category_id' => null, 'category_ids' => json_encode([['id' => '20', 'position' => 1]]),
        ], 1);
        app(\App\Services\SellerProductReviewService::class)->transition($product, 'approved_published', adminId: 9);
        $published = DB::table('products')->where('id', $product->id)->first();
        $this->assertSame(1.0, (float) $published->unit_price);
        $this->assertSame(20, (int) $published->category_id);
        $this->assertSame([['id' => '20', 'position' => 1]], json_decode($published->category_ids, true));
        $this->assertNull($published->sub_category_id);
    }

    public function test_full_administration_review_updates_editorial_translation_and_keeps_vendor_price(): void
    {
        $product = $this->product(); $workspace = app(VendorProductWorkspaceService::class);
        DB::table('translations')->insert(['translationable_type' => Product::class, 'translationable_id' => $product->id, 'locale' => 'ar', 'key' => 'name', 'value' => 'Old name']);
        $workspace->saveSellerDraft($product, ['unit_price' => 110]);
        $workspace->saveEditorialDraft($product, ['name' => 'Reviewed customer name', 'unit_price' => 170], 2);
        app(\App\Services\SellerProductReviewService::class)->transition($product, 'approved_published', adminId: 9);
        $this->assertSame('Reviewed customer name', DB::table('translations')->value('value'));
        $this->assertSame(170.0, (float) DB::table('products')->value('unit_price'));
        $this->assertSame(110.0, (float) VendorProductWorkspace::first()->approved_seller_content['unit_price']);
        $this->assertDatabaseHas('seller_product_review_decisions', ['status' => 'approved_published', 'admin_id' => 9]);
    }

    public function test_content_approval_does_not_restore_stock_sold_during_review(): void
    {
        $product = $this->product(); $workspace = app(VendorProductWorkspaceService::class);
        $workspace->saveEditorialDraft($product, ['unit_price' => 170], 1);
        DB::table('products')->update(['current_stock' => 9]);
        $product = Product::withoutGlobalScopes()->findOrFail($product->id);
        DB::transaction(function () use ($workspace, $product) { $workspace->approve($product); $product->save(); });
        $this->assertSame(9, (int) DB::table('products')->value('current_stock'));
        $this->assertSame(170.0, (float) DB::table('products')->value('unit_price'));
    }

    public function test_partial_product_selection_does_not_expose_editorial_name_in_slot_choices(): void
    {
        $product = $this->product();
        DB::table('products')->update(['name' => 'Customer editorial name']);
        request()->attributes->set('vendor_workspace_seller_id', 1);
        try {
            $partial = Product::withoutGlobalScopes()->find($product->id, ['id', 'name']);
            $this->assertSame('Vendor original', $partial->name);
            $html = app(VendorProductWorkspaceBoundary::class)->sanitizeHtml('<select name="product_id"><option value="'.$product->id.'">Vendor original</option></select>', [$product->id => 900000000001]);
            $this->assertStringContainsString('value="900000000001"', $html);
            $radio = app(VendorProductWorkspaceBoundary::class)->sanitizeHtml('<input type="radio" name="product_id" value="'.$product->id.'" required>', [$product->id => 900000000001]);
            $this->assertStringContainsString('value="900000000001"', $radio);
            $this->assertStringNotContainsString('value="'.$product->id.'"', $radio);
        } finally {
            request()->attributes->remove('vendor_workspace_seller_id');
        }
    }

    public function test_subscription_notice_renders_with_private_reference_and_renewal_link(): void
    {
        $product = $this->product(1, false);
        $html = view('vendor-views.product.partials._workspace-notice', compact('product'))->render();
        $this->assertStringContainsString('AD-'.VendorProductWorkspace::first()->private_reference, $html);
        $this->assertStringContainsString('/vendor/packages', $html);
        $this->assertStringNotContainsString('public-product-secret', $html);
        $folder = 'E:/project/docs/vendor-product-workspace-phase3-20261017';
        if (is_dir($folder)) file_put_contents($folder.'/notice-fixture.html', '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public/assets/back-end/css/bootstrap.min.css"><link rel="stylesheet" href="/public/assets/back-end/css/theme.minc619.css"><link rel="stylesheet" href="/public/assets/back-end/css/vendor-workspace.css"><body><div class="app-v2 vendor-shell" style="display:block"><main class="v2-main content container-fluid">'.$html.'</main></div></body></html>');
    }

    public function test_resubmitting_content_does_not_clear_an_administrative_publication_suspension(): void
    {
        $product = $this->product();
        app(\App\Services\SellerProductReviewService::class)->transition($product, 'suspended', 'Administration hold', 9);
        app(VendorProductWorkspaceService::class)->saveSellerDraft($product, ['name' => 'Revised original']);
        $this->assertTrue(VendorProductWorkspace::first()->publication_suspended);
        request()->attributes->set('vendor_workspace_seller_id', 1);
        try {
            $product = Product::withoutGlobalScopes()->findOrFail($product->id);
            $product->status = 1;
            $product->save();
            $this->fail('A content resubmission must not release an administrative hold.');
        } catch (ValidationException $exception) {
            $this->assertSame(0, (int) DB::table('products')->value('status'));
        } finally {
            request()->attributes->remove('vendor_workspace_seller_id');
        }
    }

    public function test_vendor_edit_preserves_immutable_original_and_records_both_revisions_once(): void
    {
        $product = $this->product();
        $service = app(VendorProductWorkspaceService::class);
        $state = $service->saveSellerDraft($product, ['name' => 'Second submission', 'unit_price' => 125]);
        $service->recordSubmission($state);
        $rows = DB::table('vendor_product_submission_revisions')->orderBy('revision')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('Vendor original', json_decode($rows[0]->seller_content, true)['name']);
        $this->assertSame(100.0, (float) json_decode($rows[0]->seller_content, true)['unit_price']);
        $this->assertSame('Second submission', json_decode($rows[1]->seller_content, true)['name']);
        $this->assertSame($product->id, $rows[1]->product_id);
        $this->assertSame(1, $rows[1]->seller_id);
    }

    public function test_initial_submission_is_recorded_as_revision_one_immediately_after_capture(): void
    {
        $product = $this->product();
        $state = app(VendorProductWorkspaceService::class)->capture($product);
        $this->assertSame(1, $state->revision);
        $this->assertSame(1, DB::table('vendor_product_submission_revisions')->where('product_id', $product->id)->count());
        $this->assertSame(1, DB::table('vendor_product_submission_revisions')->where('product_id', $product->id)->value('revision'));
    }

    public function test_vendor_slot_remains_active_while_customer_publication_is_hidden(): void
    {
        $product = $this->product();
        VendorProductWorkspace::where('product_id', $product->id)->update(['legacy_exempt' => false]);
        $subscription = DB::table('seller_package_subscriptions')->insertGetId([
            'seller_id' => 1, 'payment_status' => 'paid', 'status' => 'active',
            'starts_at' => now()->subDay(), 'expires_at' => now()->addDay(),
        ]);
        $slot = DB::table('vendor_advertising_slots')->insertGetId([
            'seller_id' => 1, 'product_id' => $product->id, 'seller_package_subscription_id' => $subscription,
            'starts_at' => now()->subDay(), 'expires_at' => now()->addDay(),
        ]);
        $service = app(VendorProductWorkspaceService::class);
        $state = $service->saveSellerDraft($product, ['name' => 'Seller edit']);
        $this->assertTrue($service->vendorActive($state));
        $this->assertFalse(app(VendorAdvertisingAccessService::class)->visible($product));
        $this->assertSame($slot, DB::table('vendor_product_submission_revisions')->where('revision', 2)->value('slot_id'));
        $html = view('vendor-views.product.partials._workspace-notice', compact('product'))->render();
        $this->assertStringContainsString('نشط', $html);
        $this->assertStringNotContainsString('under_admin_review', $html);
        $this->assertStringNotContainsString('مراجعة', $html);
    }

    public function test_private_payload_strips_review_and_editorial_only_attributes(): void
    {
        $product = $this->product();
        request()->attributes->set('vendor_workspace_seller_id', 1);
        try {
            $payload = app(VendorProductWorkspaceBoundary::class)->sanitize([
                'id' => $product->id, 'unit_price' => 999, 'name' => 'Editorial secret',
                'video_url' => 'editorial-only-url', 'seller_review_reason' => 'Internal secret',
                'vendor_workspace' => ['review_state' => 'under_review'],
                'digital_variation' => [['price' => 999]],
            ], [$product->id => 900000000001], true);
            $this->assertSame('Vendor original', $payload['name']);
            $this->assertSame(100.0, (float) $payload['unit_price']);
            $this->assertNull($payload['video_url']);
            $this->assertArrayNotHasKey('seller_review_reason', $payload);
            $this->assertArrayNotHasKey('review_state', $payload['vendor_workspace']);
            $this->assertArrayNotHasKey('digital_variation', $payload);
        } finally {
            request()->attributes->remove('vendor_workspace_seller_id');
        }
    }

    public function test_vendor_edit_notifies_administration_once_per_revision(): void
    {
        Schema::create('notifications', function (Blueprint $t) {
            $t->id(); $t->string('sent_by'); $t->string('sent_to'); $t->unsignedBigInteger('seller_id');
            $t->string('title'); $t->text('description'); $t->integer('notification_count'); $t->integer('status'); $t->timestamps();
        });
        $product = $this->product();
        $service = app(VendorProductWorkspaceService::class);
        $state = $service->saveSellerDraft($product, ['name' => 'Updated vendor original']);
        $service->notifyAdminSubmission($state);
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame('admin', DB::table('notifications')->value('sent_to'));
        $this->assertStringContainsString('تعديل منتج من البائع', DB::table('notifications')->value('description'));
    }

    public function test_catalog_list_routes_require_seller_authentication_and_review_queue_requires_catalog_permission(): void
    {
        $routes = app('router')->getRoutes();
        $admin = $routes->getByName('admin.products.review-queue');
        $this->assertNotNull($admin);
        $this->assertContains('admin', $admin->gatherMiddleware());
        $this->assertContains('module:catalog', $admin->gatherMiddleware());
        foreach (['all-products', 'edit-order-all-products'] as $action) {
            $route = $routes->match(Request::create('/api/v3/seller/products/1/'.$action, 'GET'));
            $this->assertContains('seller_api_auth', $route->gatherMiddleware());
        }
    }

    public function test_vendor_catalog_enumeration_rejects_another_seller_before_reading_products(): void
    {
        $this->product();
        $request = Request::create('/api/v3/seller/products/2/all-products', 'GET');
        $request['seller'] = Seller::findOrFail(1);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        app(\App\Http\Controllers\RestAPI\v3\seller\ProductController::class)->getVendorAllProducts(2, $request);
    }

    public function test_admin_form_uses_live_stock_and_rejects_stock_changed_since_opening_form(): void
    {
        $product = $this->product();
        DB::table('products')->update(['current_stock' => 9]);
        $product = Product::withoutGlobalScopes()->findOrFail($product->id);
        $workspace = app(VendorProductWorkspaceService::class);
        $workspace->projectForEditorial($product);
        $this->assertSame(9, $product->current_stock);
        DB::table('products')->update(['current_stock' => 8]);
        $this->expectException(ValidationException::class);
        $workspace->saveEditorialDraft($product, ['unit_price' => 170], 1, true, 9);
    }
}
