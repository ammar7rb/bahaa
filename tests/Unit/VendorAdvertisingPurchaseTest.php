<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Models\SellerPackage;
use App\Models\VendorAdvertisingSlot;
use App\Services\SellerPackagePurchaseService;
use App\Services\VendorAdvertisingSlotService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendorAdvertisingPurchaseTest extends SellerPackagePurchaseServiceTest
{
    protected function setUp(): void
    {
        parent::setUp();
        (require base_path('database/migrations/2026_10_14_000001_create_vendor_advertising_slots.php'))->up();
        (require base_path('database/migrations/2026_10_14_000002_create_vendor_advertising_rollouts.php'))->up();
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('added_by');
            $table->unsignedBigInteger('user_id');
            $table->integer('request_status')->default(1);
            $table->integer('status')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('products');
        (require base_path('database/migrations/2026_10_14_000002_create_vendor_advertising_rollouts.php'))->down();
        (require base_path('database/migrations/2026_10_14_000001_create_vendor_advertising_slots.php'))->down();
        parent::tearDown();
    }

    private function advertisingPackage(int $slots = 5): SellerPackage
    {
        config(['vendor_advertising.enabled' => true]);

        return SellerPackage::create([
            'name' => 'Slots '.$slots, 'slug' => 'slots-'.$slots, 'package_price' => 3000,
            'product_limit' => $slots, 'product_duration_days' => 30, 'package_validity_days' => 30,
            'search_promotion_limit' => 0, 'search_promotion_duration_days' => 0,
            'homepage_promotion_limit' => 0, 'homepage_promotion_duration_days' => 0,
            'coupon_limit' => 0, 'status' => true,
        ]);
    }

    public function test_paid_renewal_is_queued_and_repeated_approval_does_not_duplicate_slots_or_transactions(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00');
        $service = app(SellerPackagePurchaseService::class);
        $seller = Seller::findOrFail(1);
        $package = $this->advertisingPackage();
        $first = $service->markPaid($service->getOrCreatePendingSubscription($seller, $package), [])['subscription'];
        $this->assertSame(5, $first->product_limit);
        $this->assertSame(5, VendorAdvertisingSlot::count());
        $second = $service->markPaid($service->getOrCreatePendingSubscription($seller, $package), [])['subscription'];
        $this->assertSame('scheduled', $second->status);
        $this->assertSame('active', $first->fresh()->status);
        $this->assertTrue($first->expires_at->equalTo($second->starts_at));
        $before = DB::table('seller_package_transactions')->count();
        $service->markPaid($second, []);
        $this->assertSame(10, VendorAdvertisingSlot::count());
        $this->assertSame($before, DB::table('seller_package_transactions')->count());
    }

    public function test_replacement_and_removal_preserve_expiry_and_reject_foreign_products(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00');
        $seller = Seller::findOrFail(1);
        $purchase = app(SellerPackagePurchaseService::class);
        $purchase->markPaid($purchase->getOrCreatePendingSubscription($seller, $this->advertisingPackage()), []);
        DB::table('products')->insert([['id' => 1, 'added_by' => 'seller', 'user_id' => 1], ['id' => 2, 'added_by' => 'seller', 'user_id' => 1], ['id' => 3, 'added_by' => 'seller', 'user_id' => 2]]);
        $slot = VendorAdvertisingSlot::firstOrFail();
        $expiry = $slot->expires_at->toIso8601String();
        $service = app(VendorAdvertisingSlotService::class);
        $service->assign($seller, $slot->id, 1);
        Carbon::setTestNow('2026-11-01 12:00:00');
        $service->assign($seller, $slot->id, 2);
        $service->assign($seller, $slot->id, null);
        $this->assertSame($expiry, $slot->fresh()->expires_at->toIso8601String());
        $this->assertNull($slot->fresh()->product_id);
        $this->assertSame(3, DB::table('vendor_advertising_slot_history')->count());
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->assign($seller, $slot->id, 3);
    }

    public function test_offline_payment_starts_on_approval_and_paid_cancellation_is_unavailable(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00');
        $service = app(SellerPackagePurchaseService::class);
        $pending = $service->getOrCreatePendingSubscription(Seller::findOrFail(1), $this->advertisingPackage());
        $service->submitOfflinePayment($pending, ['transaction_id' => 'transfer-proof']);
        $this->assertSame(0, VendorAdvertisingSlot::count());
        Carbon::setTestNow('2026-10-16 12:00:00');
        $active = $service->markPaid($pending, ['payment_method' => 'offline_payment'], 1)['subscription'];
        $this->assertSame('2026-10-16 12:00:00', $active->starts_at->toDateTimeString());
        $this->assertSame(30 * 86400, $active->expires_at->getTimestamp() - $active->starts_at->getTimestamp());
        $this->expectException(DomainException::class);
        $service->cancel($active, 'Requested cancellation', 'seller', 1);
    }

    public function test_downgrade_waits_for_vendor_selection_instead_of_silently_discarding_products(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00');
        $seller = Seller::findOrFail(1);
        $purchase = app(SellerPackagePurchaseService::class);
        $current = $purchase->markPaid($purchase->getOrCreatePendingSubscription($seller, $this->advertisingPackage()), [])['subscription'];
        DB::table('products')->insert([['id' => 1, 'added_by' => 'seller', 'user_id' => 1], ['id' => 2, 'added_by' => 'seller', 'user_id' => 1]]);
        $slots = VendorAdvertisingSlot::where('seller_package_subscription_id', $current->id)->get();
        app(VendorAdvertisingSlotService::class)->assign($seller, $slots[0]->id, 1);
        app(VendorAdvertisingSlotService::class)->assign($seller, $slots[1]->id, 2);
        $next = $purchase->markPaid($purchase->getOrCreatePendingSubscription($seller, $this->advertisingPackage(1)), [])['subscription'];
        $this->assertSame('scheduled', $next->status);
        Carbon::setTestNow($next->starts_at);
        app(VendorAdvertisingSlotService::class)->synchronize($seller);
        $this->assertSame('active', $next->fresh()->status);
        $this->assertTrue($next->fresh()->metadata['slot_selection_required']);
        $this->assertNull(VendorAdvertisingSlot::where('seller_package_subscription_id', $next->id)->firstOrFail()->product_id);
        $this->assertSame(2, VendorAdvertisingSlot::where('seller_package_subscription_id', $current->id)->whereNotNull('product_id')->count());
    }

    public function test_catalog_publication_remains_independent_of_advertising_expiry(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00');
        $purchase = app(SellerPackagePurchaseService::class);
        $seller = Seller::findOrFail(1);
        $subscription = $purchase->markPaid($purchase->getOrCreatePendingSubscription($seller, $this->advertisingPackage()), [])['subscription'];
        DB::table('products')->insert([['id' => 1, 'added_by' => 'seller', 'user_id' => 1, 'status' => 1], ['id' => 2, 'added_by' => 'seller', 'user_id' => 1, 'status' => 1], ['id' => 3, 'added_by' => 'admin', 'user_id' => 1, 'status' => 1]]);
        app(VendorAdvertisingSlotService::class)->assign($seller, VendorAdvertisingSlot::firstOrFail()->id, 1);
        $query = DB::table('products');
        app(\App\Services\VendorAdvertisingAccessService::class)->constrain($query);
        $this->assertSame([1,2,3], $query->orderBy('id')->pluck('id')->all());
        $shop = new \App\Models\Shop(['seller_id' => 1]);
        $this->assertSame([1,2], $shop->products()->withoutGlobalScope('translate')->pluck('id')->all());
        Carbon::setTestNow($subscription->expires_at);
        $this->assertFalse(VendorAdvertisingSlot::firstOrFail()->editable);
        $this->assertSame('frozen', VendorAdvertisingSlot::firstOrFail()->lifecycle_status);
        $query = DB::table('products');
        app(\App\Services\VendorAdvertisingAccessService::class)->constrain($query);
        $this->assertSame([1,2,3], $query->pluck('id')->all());
        $this->assertSame([1,2], $shop->products()->withoutGlobalScope('translate')->pluck('id')->all());
    }

    public function test_advertising_grace_does_not_block_default_plan_submission(): void
    {
        config(['vendor_advertising.enabled' => true]);
        Carbon::setTestNow('2026-10-14 12:00:00');
        DB::table('vendor_advertising_rollouts')->insert(['id' => 1, 'seller_cutoff_id' => 1, 'starts_at' => now(), 'grace_ends_at' => now()->addDays(7)]);
        $access = app(\App\Services\VendorAdvertisingAccessService::class);
        $this->assertTrue($access->inGrace(1));
        $this->assertFalse($access->inGrace(2));
        DB::table('products')->insert([
            ['id' => 1, 'added_by' => 'seller', 'user_id' => 1, 'created_at' => now()->subDay()],
            ['id' => 2, 'added_by' => 'seller', 'user_id' => 1, 'created_at' => now()->addMinute()],
        ]);
        $query = DB::table('products'); $access->constrain($query);
        $this->assertSame([1,2], $query->pluck('id')->all());
        $access->assertCanCreate(1);
        Carbon::setTestNow('2026-10-21 12:00:00');
        $this->assertFalse($access->inGrace(1));
        $access->assertCanCreate(1);
        $this->assertTrue(true);
    }
    public function test_expiry_scheduler_notifies_once_without_creating_financial_transactions(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id(); foreach (['sent_by', 'sent_to', 'title', 'description', 'image'] as $column) $table->string($column);
            $table->integer('seller_id'); $table->integer('status'); $table->integer('notification_count'); $table->timestamps();
        });
        Schema::create('storages', function (Blueprint $table) {
            $table->id(); $table->string('data_type'); $table->integer('data_id'); $table->string('key'); $table->string('value'); $table->timestamps();
        });
        try {
            Carbon::setTestNow('2026-10-14 12:00:00');
            $purchase = app(SellerPackagePurchaseService::class);
            $subscription = $purchase->markPaid($purchase->getOrCreatePendingSubscription(Seller::findOrFail(1), $this->advertisingPackage()), [])['subscription'];
            $transactions = DB::table('seller_package_transactions')->count();
            Carbon::setTestNow($subscription->expires_at);
            app(\App\Console\Commands\SyncVendorAdvertising::class)->handle();
            app(\App\Console\Commands\SyncVendorAdvertising::class)->handle();
            $this->assertSame(1, DB::table('notifications')->where('title', '!=', 'قبول دفع الباقة')->count());
            $this->assertSame(1, DB::table('notifications')->where('title', 'قبول دفع الباقة')->count());
            $this->assertSame('expired', $subscription->fresh()->status);
            $this->assertSame($transactions, DB::table('seller_package_transactions')->count());
        } finally {
            Schema::dropIfExists('storages'); Schema::dropIfExists('notifications');
        }
    }

    public function test_shared_expiry_command_preserves_customer_boundaries_and_vendor_paid_renewal(): void
    {
        Schema::create('customer_purchase_package_subscriptions', function (Blueprint $table) {
            $table->id(); $table->string('status'); $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
        try {
            Carbon::setTestNow('2026-10-14 12:00:00');
            $seller = Seller::findOrFail(1);
            $purchase = app(SellerPackagePurchaseService::class);
            $package = $this->advertisingPackage();
            $first = $purchase->markPaid($purchase->getOrCreatePendingSubscription($seller, $package), [])['subscription'];
            $next = $purchase->markPaid($purchase->getOrCreatePendingSubscription($seller, $package), [])['subscription'];
            $transactions = DB::table('seller_package_transactions')->count();
            Carbon::setTestNow($first->expires_at);
            DB::table('customer_purchase_package_subscriptions')->insert([
                ['id' => 1, 'status' => 'active', 'expires_at' => now()],
                ['id' => 2, 'status' => 'active', 'expires_at' => now()->addSecond()],
                ['id' => 3, 'status' => 'pending', 'expires_at' => now()->subSecond()],
            ]);
            $this->artisan('packages:expire-subscriptions')->assertExitCode(0);
            app(VendorAdvertisingSlotService::class)->synchronize($seller);
            app(VendorAdvertisingSlotService::class)->synchronize($seller);
            $this->assertSame('expired', $first->fresh()->status);
            $this->assertSame('active', $next->fresh()->status);
            $this->assertSame(['expired', 'active', 'pending'], DB::table('customer_purchase_package_subscriptions')->orderBy('id')->pluck('status')->all());
            $this->assertSame($transactions, DB::table('seller_package_transactions')->count());
            $this->assertSame(10, VendorAdvertisingSlot::count());
        } finally {
            Schema::dropIfExists('customer_purchase_package_subscriptions');
        }
    }

    public function test_paid_renewal_is_visible_at_exact_boundary_without_waiting_for_a_scheduler(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00');
        $service = app(SellerPackagePurchaseService::class);
        $seller = Seller::findOrFail(1);
        $package = $this->advertisingPackage();
        $first = $service->markPaid($service->getOrCreatePendingSubscription($seller, $package), [])['subscription'];
        DB::table('products')->insert([['id' => 1, 'added_by' => 'seller', 'user_id' => 1], ['id' => 2, 'added_by' => 'seller', 'user_id' => 1]]);
        $slot = VendorAdvertisingSlot::where('seller_package_subscription_id', $first->id)->firstOrFail();
        app(VendorAdvertisingSlotService::class)->assign($seller, $slot->id, 1);
        $second = $service->markPaid($service->getOrCreatePendingSubscription($seller, $package), [])['subscription'];
        app(VendorAdvertisingSlotService::class)->assign($seller, $slot->id, 2);
        Carbon::setTestNow($first->expires_at);
        $this->assertSame('scheduled', $second->fresh()->status);
        $query = DB::table('products');
        app(\App\Services\VendorAdvertisingAccessService::class)->constrain($query);
        $this->assertSame([1,2], $query->pluck('id')->all());
    }

    public function test_old_status_toggle_cannot_publish_or_sell_a_product_awaiting_admin_review(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00');
        $purchase = app(SellerPackagePurchaseService::class); $seller = Seller::findOrFail(1);
        $purchase->markPaid($purchase->getOrCreatePendingSubscription($seller, $this->advertisingPackage()), []);
        DB::table('products')->insert(['id' => 1, 'added_by' => 'seller', 'user_id' => 1, 'request_status' => 0, 'status' => 0]);
        app(VendorAdvertisingSlotService::class)->assign($seller, VendorAdvertisingSlot::firstOrFail()->id, 1);
        $product = \App\Models\Product::withoutGlobalScopes()->findOrFail(1);
        $this->assertFalse(app(\App\Services\VendorAdvertisingAccessService::class)->visible($product, true));
        try {
            $product->forceFill(['status' => 1])->save();
            $this->fail('Direct status change must not bypass administration approval.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('publication', $exception->errors());
        }
        $product->forceFill(['request_status' => 1, 'status' => 1])->save();
        $this->assertTrue(app(\App\Services\VendorAdvertisingAccessService::class)->visible($product, true));
    }
}
