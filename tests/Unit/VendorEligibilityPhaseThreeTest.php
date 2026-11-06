<?php

namespace Tests\Unit;

use App\Http\Middleware\VendorOrderBoundary;
use App\Models\Order;
use App\Services\VendorFulfillmentEligibilityService;
use App\Services\VendorInboxService;
use App\Services\VendorOrderPresentationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendorEligibilityPhaseThreeTest extends TestCase
{
    public function test_deferred_rollout_blocks_new_snapshots_but_keeps_existing_locks_protected(): void
    {
        $order = $this->order();
        $this->service()->forward($order);
        config(['vendor_order_eligibility.enabled' => false]);
        $this->assertTrue($this->service()->hasRestrictions($order));
        $new = $order->replicate(); $new->save();
        $this->assertTrue($this->service()->forward($new)->isEmpty());
        $this->assertSame(0, DB::table('vendor_fulfillment_requests')->where('order_id', $new->id)->count());
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['vendor_product_workspace.enabled' => false]);
        Schema::create('seller_order_insurances', fn (Blueprint $t) => [$t->id(), $t->integer('order_id'), $t->timestamps()]);
        Schema::create('storages', fn (Blueprint $t) => [$t->id(), $t->integer('data_id'), $t->string('data_type'), $t->string('key')->nullable(), $t->string('value')->nullable(), $t->timestamps()]);
        Schema::create('sellers', fn (Blueprint $t) => [$t->id(), $t->string('activation_status')->nullable(), $t->timestamps()]);
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->string('seller_is')->default('seller');
            $t->integer('customer_id')->default(44);
            $t->string('order_status')->default('confirmed'); $t->string('payment_status')->default('paid');
            $t->string('commerce_flow_status')->nullable(); $t->string('commerce_flow_version')->nullable();
            $t->string('admin_order_review_status')->default('approved'); $t->string('post_purchase_status')->nullable();
            $t->string('seller_restricted_access_token')->nullable();
            $t->string('shipping_fulfillment_mode')->default('seller_shipping');
            $t->decimal('shipping_seller_entitlement', 24, 8)->default(20);
            $t->decimal('order_amount', 24, 8)->default(9999);
            $t->text('shipping_address_data')->nullable(); $t->string('verification_code')->default('SECRET-CODE');
            $t->timestamp('admin_order_reviewed_at')->nullable(); $t->timestamps();
        });
        Schema::create('order_details', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('seller_id'); $t->integer('product_id');
            $t->integer('qty'); $t->decimal('price', 24, 8); $t->decimal('vendor_unit_price_snapshot', 24, 8)->nullable();
            $t->integer('vendor_content_revision')->nullable(); $t->text('product_details')->nullable(); $t->timestamps();
        });
        Schema::create('order_vendor_pricings', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('seller_id'); $t->decimal('procurement_amount', 24, 8); $t->timestamps();
        });
        Schema::create('seller_package_subscriptions', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->string('payment_status'); $t->string('status'); $t->timestamp('starts_at'); $t->timestamp('expires_at');
        });
        Schema::create('vendor_advertising_slots', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->integer('product_id'); $t->integer('seller_package_subscription_id'); $t->timestamp('starts_at'); $t->timestamp('expires_at');
        });
        Schema::create('vendor_product_workspaces', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->integer('product_id'); $t->integer('private_reference'); $t->json('seller_content');
        });
        Schema::create('vendor_product_submission_revisions', fn (Blueprint $t) => [$t->id(), $t->integer('workspace_id'), $t->integer('revision'), $t->json('seller_content')]);
        Schema::create('order_shipping_decisions', fn (Blueprint $t) => [$t->id(), $t->integer('order_id'), $t->string('instructions')->nullable()]);
        Schema::create('notifications', function (Blueprint $t) {
            $t->id(); $t->string('sent_by'); $t->string('sent_to'); $t->integer('seller_id')->nullable();
            $t->string('title'); $t->text('description'); $t->string('image')->nullable(); $t->integer('notification_count'); $t->integer('status'); $t->timestamps();
        });
        (require database_path('migrations/2026_11_06_000001_create_vendor_fulfillment_requests.php'))->up();
        DB::table('sellers')->insert(['id' => 7, 'activation_status' => 'active']);
        DB::table('orders')->insert(['id' => 1, 'seller_id' => 7, 'created_at' => now()->subDays(10), 'updated_at' => now(),
            'shipping_address_data' => '{"phone":"SECRET-PHONE","address":"SECRET-ADDRESS"}']);
        DB::table('order_details')->insert(['id' => 1, 'order_id' => 1, 'seller_id' => 7, 'product_id' => 11,
            'qty' => 2, 'price' => 777, 'vendor_unit_price_snapshot' => 70, 'vendor_content_revision' => 1,
            'product_details' => '{"name":"SECRET-EDITORIAL"}']);
        DB::table('order_vendor_pricings')->insert(['order_id' => 1, 'seller_id' => 7, 'procurement_amount' => 140]);
        DB::table('vendor_product_workspaces')->insert(['id' => 1, 'seller_id' => 7, 'product_id' => 11,
            'private_reference' => 900000000011, 'seller_content' => '{"name":"Eligible original","unit_price":70}']);
    }

    private function order(): Order { return Order::findOrFail(1); }
    private function service(): VendorFulfillmentEligibilityService { return app(VendorFulfillmentEligibilityService::class); }

    private function mixed(): void
    {
        DB::table('seller_package_subscriptions')->insert(['id' => 1, 'seller_id' => 7, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay()]);
        DB::table('vendor_advertising_slots')->insert(['seller_id' => 7, 'product_id' => 11, 'seller_package_subscription_id' => 1, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay()]);
        DB::table('order_details')->insert(['id' => 2, 'order_id' => 1, 'seller_id' => 7, 'product_id' => 22,
            'qty' => 13, 'price' => 888, 'vendor_unit_price_snapshot' => 10, 'vendor_content_revision' => 1,
            'product_details' => '{"name":"SECRET-LOCKED-PRODUCT"}']);
        DB::table('order_vendor_pricings')->update(['procurement_amount' => 270]);
    }

    private function boundary(string $method, string $path, string $action, array $parameters = [], array $data = [])
    {
        $request = Request::create($path, $method, $data);
        $route = new Route($method, $path, ['uses' => $action, 'controller' => $action]);
        $route->bind($request);
        foreach ($parameters as $key => $value) $route->setParameter($key, $value);
        $request->setRouteResolver(fn () => $route);
        return app(VendorOrderBoundary::class)->handle($request, fn () => response()->json(['unexpected' => true]), 7);
    }

    public function test_forwarding_is_idempotent_and_timer_ignores_customer_creation_time(): void
    {
        $first = $this->service()->forward($this->order())->first();
        $this->assertSame('locked', $first->status);
        $this->assertEqualsWithDelta(24 * 3600, $first->forwarded_at->diffInSeconds($first->pending_due_at), 1);
        $this->assertSame(0, $this->service()->classifyPending());
        $this->travel(2)->hours();
        $second = $this->service()->forward($this->order())->first();
        $this->assertTrue($first->forwarded_at->equalTo($second->forwarded_at));
        $this->assertSame(1, DB::table('vendor_fulfillment_requests')->count());
        $this->assertSame(1, DB::table('vendor_fulfillment_events')->count());
    }

    public function test_exact_24_hours_classifies_once_without_changing_order_or_payment(): void
    {
        $this->freezeTime();
        $row = $this->service()->forward($this->order())->first();
        $before = $this->order()->getAttributes();
        $this->travel(24 * 60 - 1)->minutes();
        $this->assertSame(0, $this->service()->classifyPending());
        $this->travel(1)->minutes();
        $this->assertSame(1, $this->service()->classifyPending());
        $this->assertSame(0, $this->service()->classifyPending());
        $this->assertEquals($before, $this->order()->getAttributes());
        $this->assertSame('pending_vendor', $row->fresh()->status);
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame(2, DB::table('vendor_fulfillment_events')->count());
        $this->assertTrue($row->forwarded_at->equalTo($row->fresh()->forwarded_at));
    }

    public function test_fully_locked_projection_has_only_vendor_total_and_no_fulfillment_data(): void
    {
        $this->service()->forward($this->order());
        $dto = app(VendorOrderPresentationService::class)->order($this->order());
        $this->assertTrue($dto['details_locked']);
        $this->assertEquals(160, $dto['order_amount']);
        $this->assertSame([], $dto['order_details']);
        $json = json_encode($dto);
        foreach (['SECRET', '777', '9999', 'shipping_address_data', 'verification_code', 'product_id', 'qty'] as $secret) $this->assertStringNotContainsString($secret, $json);
    }

    public function test_mixed_projection_contains_only_eligible_lines_and_separate_restricted_amount(): void
    {
        $this->mixed();
        DB::table('order_shipping_decisions')->insert(['order_id' => 1, 'instructions' => 'SECRET-LOCKED-INSTRUCTIONS']);
        $this->service()->forward($this->order(), ['accessible_shipping_amount' => 8]);
        $dto = app(VendorOrderPresentationService::class)->order($this->order());
        $this->assertFalse($dto['details_locked']);
        $this->assertTrue($dto['partially_locked']);
        $this->assertEquals(148, $dto['order_amount']);
        $this->assertEquals(142, $dto['restricted_amount']);
        $this->assertCount(1, $dto['order_details']);
        $this->assertSame('Eligible original', $dto['order_details'][0]['product_details']['name']);
        $this->assertNull($dto['verification_code']);
        $this->assertSame([], $dto['shipping_assignment']['allowed_seller_statuses']);
        foreach (['SECRET-LOCKED', 'SECRET-EDITORIAL', '888', '777', '9999'] as $secret) $this->assertStringNotContainsString($secret, json_encode($dto));
    }

    public function test_mixed_adjusted_amount_requires_explicit_allocation(): void
    {
        $this->mixed();
        DB::table('order_vendor_pricings')->update(['procurement_amount' => 300]);
        $this->expectException(\DomainException::class);
        $this->service()->forward($this->order(), ['accessible_shipping_amount' => 8]);
    }

    public function test_mixed_explicit_allocation_preserves_total_and_does_not_reprice_customer(): void
    {
        $this->mixed();
        DB::table('order_vendor_pricings')->update(['procurement_amount' => 300]);
        $requests = $this->service()->forward($this->order(), ['accessible_procurement_amount' => 150, 'accessible_shipping_amount' => 8]);
        $this->assertEquals(300, $requests->sum('vendor_amount'));
        $this->assertEquals(20, $requests->sum('shipping_amount'));
        $this->assertEquals(9999, $this->order()->order_amount);
    }

    public function test_slot_expiry_does_not_relock_and_package_payment_alone_does_not_unlock(): void
    {
        $this->mixed();
        $this->service()->forward($this->order(), ['accessible_shipping_amount' => 0]);
        DB::table('seller_package_subscriptions')->update(['expires_at' => now()->subSecond()]);
        $this->assertTrue($this->service()->lineAllowed($this->order()->details->firstWhere('id', 1)));
        DB::table('seller_package_subscriptions')->update(['expires_at' => now()->addMonth()]);
        DB::table('vendor_advertising_slots')->insert(['seller_id' => 7, 'product_id' => 22, 'seller_package_subscription_id' => 1, 'starts_at' => now()->subDay(), 'expires_at' => now()->addMonth()]);
        $this->assertFalse($this->service()->lineAllowed($this->order()->details->firstWhere('id', 2)));
    }

    public function test_api_details_filters_locked_lines_and_embedded_order_is_safe(): void
    {
        $this->mixed(); $this->service()->forward($this->order(), ['accessible_shipping_amount' => 0]);
        $response = $this->boundary('GET', '/api/v3/seller/orders/1', 'App\Http\Controllers\RestAPI\v3\seller\OrderController@details', ['id' => 1]);
        $this->assertCount(1, $response->getData(true));
        $this->assertStringNotContainsString('SECRET-LOCKED', $response->getContent());
        $this->assertEquals(140, $response->getData(true)[0]['order']['order_amount']);
    }

    public function test_locked_details_and_shared_proof_download_reject_access_before_controller(): void
    {
        $this->service()->forward($this->order());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->boundary('GET', '/api/v3/seller/orders/1/shipping-proofs', 'App\Http\Controllers\RestAPI\v3\seller\ShippingProofController@index', ['order' => 1]);
    }

    public function test_nested_alternate_payload_cannot_restore_locked_product_details(): void
    {
        $this->service()->forward($this->order());
        $safe = app(VendorOrderPresentationService::class)->sanitize(['result' => [['id' => 1, 'order_id' => 1, 'product_details' => ['name' => 'SECRET']]]], 7);
        $this->assertSame(['result' => []], $safe);
    }

    public function test_notification_content_is_replaced_before_vendor_delivery(): void
    {
        $this->service()->forward($this->order());
        $notification = new \App\Models\Notification(['title' => 'SECRET', 'description' => '#1 SECRET-LOCKED', 'image' => 'SECRET.jpg']);
        $safe = app(VendorInboxService::class)->protectNotification(\App\Models\Seller::findOrFail(7), $notification);
        $this->assertSame('#1 — '.VendorFulfillmentEligibilityService::MESSAGE, $safe->description);
        $this->assertNull($safe->image);
        $this->assertStringNotContainsString('SECRET', $safe->title);
    }

    public function test_pending_list_works_for_mobile_post_and_does_not_include_products(): void
    {
        $this->service()->forward($this->order());
        $this->travel(24)->hours();
        $this->service()->classifyPending();
        $response = $this->boundary('POST', '/api/v3/seller/orders/list', 'App\Http\Controllers\RestAPI\v3\seller\OrderController@list', [], ['status' => 'pending_vendor']);
        $this->assertSame(1, $response->getData(true)['total_size']);
        $this->assertTrue($response->getData(true)['orders'][0]['fulfillment_eligibility']['pending_on_vendor_side']);
        $this->assertStringNotContainsString('SECRET', $response->getContent());
    }

    public function test_unknown_or_foreign_lines_cannot_join_a_forwarded_request(): void
    {
        $this->service()->forward($this->order());
        $foreign = new \App\Models\OrderDetail(['id' => 99, 'order_id' => 1, 'seller_id' => 8, 'product_id' => 77]);
        $this->assertFalse($this->service()->lineAllowed($foreign));
    }

    public function test_global_status_updates_cannot_progress_locked_items(): void
    {
        $this->service()->forward($this->order());
        try {
            $this->boundary('POST', '/api/v3/seller/orders/status', 'App\Http\Controllers\RestAPI\v3\seller\OrderController@order_detail_status', [], ['id' => 1, 'order_status' => 'processing']);
            $this->fail('Restricted request was progressed.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(423, $exception->getStatusCode());
        }
        $this->assertSame('confirmed', $this->order()->order_status);
    }

    public function test_order_and_notification_badges_survive_reading_until_restriction_is_resolved(): void
    {
        Schema::create('notification_seens', fn (Blueprint $t) => [$t->id(), $t->integer('seller_id'), $t->integer('notification_id'), $t->timestamps()]);
        Schema::create('vendor_inbox_reads', fn (Blueprint $t) => [$t->id(), $t->integer('seller_id'), $t->string('kind'), $t->integer('record_id'), $t->timestamps()]);
        Schema::create('vendor_support_threads', fn (Blueprint $t) => [$t->id(), $t->integer('seller_id')]);
        Schema::create('vendor_support_messages', fn (Blueprint $t) => [$t->id(), $t->integer('vendor_support_thread_id')]);
        Schema::create('business_settings', fn (Blueprint $t) => [$t->id(), $t->string('type'), $t->text('value')]);
        DB::table('business_settings')->insert(['type' => 'language', 'value' => '[{"code":"ar","default":true}]']);
        DB::table('sellers')->update(['created_at' => now()->subDay()]);
        $this->service()->forward($this->order());
        $this->travel(24)->hours();
        $this->service()->classifyPending();
        $seller = \App\Models\Seller::findOrFail(7);
        $inbox = app(VendorInboxService::class);
        $snapshot = $inbox->snapshot($seller);
        $this->assertSame(1, $snapshot['counts']['orders']);
        $this->assertSame(1, $snapshot['counts']['notifications']);
        $this->assertSame('طلبات معلقة من جهة التاجر', $snapshot['notifications'][0]['title']);
        $inbox->read($seller, 'order_alert', '1');
        $this->assertSame(1, $inbox->snapshot($seller)['counts']['orders']);
        $this->assertSame(0, $inbox->snapshot($seller)['counts']['notifications']);
    }

    public function test_two_scheduler_processes_cannot_duplicate_transition_or_notification(): void
    {
        $this->service()->forward($this->order());
        DB::table('vendor_fulfillment_requests')->update(['pending_due_at' => now()->subSecond()]);
        $folder = storage_path('framework/testing');
        if (!is_dir($folder)) mkdir($folder, 0777, true);
        $file = $folder.'/vendor-pending-'.bin2hex(random_bytes(8)).'.sqlite';
        DB::statement('VACUUM INTO ?', [$file]);
        try {
            $workers = [new \Symfony\Component\Process\Process([PHP_BINARY, base_path('tests/Support/classify-vendor-worker.php'), $file]),
                new \Symfony\Component\Process\Process([PHP_BINARY, base_path('tests/Support/classify-vendor-worker.php'), $file])];
            foreach ($workers as $worker) $worker->start();
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            }
            $this->assertSame(1, array_sum(array_map(fn ($worker) => (int) trim($worker->getOutput()), $workers)));
            $pdo = new \PDO('sqlite:'.$file);
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn());
            $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM vendor_fulfillment_events WHERE event_type = 'pending_vendor'")->fetchColumn());
            $pdo = null;
        } finally {
            if (is_file($file)) unlink($file);
        }
    }

    public function test_other_vendor_cannot_open_fulfillment_summary(): void
    {
        $this->service()->forward($this->order());
        $request = Request::create('/api/v3/seller/fulfillment-requests/order/1');
        $request->merge(['seller' => new \App\Models\Seller(['id' => 8])]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(\App\Http\Controllers\RestAPI\v3\seller\VendorFulfillmentRequestController::class)->show($request, '1');
    }

    public function test_unreleased_order_stays_hidden_from_summary_endpoint(): void
    {
        DB::table('orders')->update(['admin_order_review_status' => 'pending_admin_review']);
        $request = Request::create('/api/v3/seller/fulfillment-requests/order/1');
        $request->merge(['seller' => \App\Models\Seller::findOrFail(7)]);
        try {
            app(\App\Http\Controllers\RestAPI\v3\seller\VendorFulfillmentRequestController::class)->show($request, '1');
            $this->fail('Unreleased order was exposed.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_csv_exports_only_allowed_amounts_and_no_locked_item_metadata(): void
    {
        $this->service()->forward($this->order());
        $response = $this->boundary('GET', '/vendor/orders/export', 'App\Http\Controllers\Vendor\Order\OrderController@exportList');
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('160', $csv);
        foreach (['SECRET', '777', '9999', 'product_id', 'qty'] as $secret) $this->assertStringNotContainsString($secret, $csv);
    }

    public function test_locked_invoice_is_denied_before_pdf_generation(): void
    {
        $this->service()->forward($this->order());
        try {
            $this->boundary('GET', '/vendor/orders/generate-invoice/1', 'App\Http\Controllers\Vendor\Order\OrderController@generateInvoice', ['id' => 1]);
            $this->fail('Locked document generated.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(423, $exception->getStatusCode());
        }
    }

    public function test_scheduler_command_is_registered_and_repeated_runs_are_safe(): void
    {
        $this->service()->forward($this->order());
        $this->travel(24)->hours();
        $this->artisan('vendors:classify-pending-requests')->expectsOutput('Classified: 1')->assertExitCode(0);
        $this->artisan('vendors:classify-pending-requests')->expectsOutput('Classified: 0')->assertExitCode(0);
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_scheduler_rechecks_unlocking_and_ignores_closed_parent_orders(): void
    {
        $this->service()->forward($this->order());
        $this->travel(24)->hours();
        DB::table('vendor_fulfillment_requests')->update(['status' => 'unlocked']);
        $this->assertSame(0, $this->service()->classifyPending());
        DB::table('vendor_fulfillment_requests')->update(['status' => 'locked']);
        DB::table('orders')->update(['order_status' => 'canceled']);
        $this->assertSame(0, $this->service()->classifyPending());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_search_and_recipient_filters_cannot_infer_locked_details_from_counts(): void
    {
        $this->service()->forward($this->order());
        foreach ([[[], 'SECRET-PHONE'], [['customer_id' => 44], null], [['delivery_man_id' => 7], null]] as [$filters, $search]) {
            $query = Order::where('seller_id', 7);
            $this->service()->constrainVendorFilters($query, $filters, $search);
            $this->assertSame(0, $query->count());
        }
        $unfiltered = Order::where('seller_id', 7);
        $this->service()->constrainVendorFilters($unfiltered, [], null);
        $this->assertSame(1, $unfiltered->count());
        $repository = app(\App\Repositories\OrderRepository::class);
        $this->assertSame(['confirmed' => 1], $repository->getStatusCounts(['seller_visible_only' => true, 'seller_id' => 7]));
        $this->assertSame([], $repository->getStatusCounts(['seller_visible_only' => true, 'seller_id' => 7, 'customer_id' => 44]));
    }
}

