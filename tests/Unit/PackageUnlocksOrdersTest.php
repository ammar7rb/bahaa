<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\SellerPackageSubscription;
use App\Services\VendorFulfillmentEligibilityService;
use App\Services\VendorFulfillmentUnlockService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Buying a package opens every restricted order of the seller automatically. */
class PackageUnlocksOrdersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('orders', function (Blueprint $t) { $t->id(); $t->integer('seller_id'); $t->string('order_status')->default('pending'); $t->timestamps(); });
        Schema::create('vendor_fulfillment_requests', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('seller_id'); $t->string('portion'); $t->string('status');
            $t->decimal('vendor_amount', 24, 8)->default(0); $t->decimal('shipping_amount', 24, 8)->default(0);
            $t->timestamp('forwarded_at')->nullable(); $t->timestamp('pending_due_at')->nullable(); $t->timestamp('pending_at')->nullable();
            $t->timestamp('unlocked_at')->nullable(); $t->json('unlock_snapshot')->nullable(); $t->json('penalty_rule_snapshot')->nullable(); $t->timestamps();
        });
        Schema::create('vendor_fulfillment_events', function (Blueprint $t) {
            $t->id(); $t->integer('request_id'); $t->string('event_type'); $t->json('metadata')->nullable(); $t->timestamp('created_at')->nullable();
            $t->unique(['request_id', 'event_type']);
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id(); $t->string('customer_event_key')->nullable()->unique(); $t->string('sent_by')->nullable(); $t->string('sent_to')->nullable();
            $t->integer('seller_id')->nullable(); $t->string('title')->nullable(); $t->text('description')->nullable(); $t->string('image')->nullable();
            $t->integer('status')->default(1); $t->integer('notification_count')->default(0); $t->timestamps();
        });
        Schema::create('seller_package_subscriptions', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->string('payment_status'); $t->string('status');
            $t->timestamp('starts_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamps();
        });
    }

    private function request(int $orderId, string $status, int $seller = 5): int
    {
        return DB::table('vendor_fulfillment_requests')->insertGetId(['order_id' => $orderId, 'seller_id' => $seller, 'portion' => 'outside_slot',
            'status' => $status, 'forwarded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function subscription(array $over = []): SellerPackageSubscription
    {
        $id = DB::table('seller_package_subscriptions')->insertGetId($over + ['seller_id' => 5, 'payment_status' => 'paid', 'status' => 'active',
            'starts_at' => now()->subDay(), 'expires_at' => now()->addMonth(), 'created_at' => now(), 'updated_at' => now()]);
        return SellerPackageSubscription::query()->findOrFail($id);
    }

    public function test_active_package_opens_locked_and_pending_orders_once(): void
    {
        $open = DB::table('orders')->insertGetId(['seller_id' => 5, 'order_status' => 'pending']);
        $closed = DB::table('orders')->insertGetId(['seller_id' => 5, 'order_status' => 'canceled']);
        $other = DB::table('orders')->insertGetId(['seller_id' => 6, 'order_status' => 'pending']);
        $locked = $this->request($open, 'locked');
        $pending = $this->request($open, 'pending_vendor');
        $closedRow = $this->request($closed, 'locked');
        $otherSeller = $this->request($other, 'locked', 6);
        $service = app(VendorFulfillmentUnlockService::class);

        $service->notifyPackageConfirmed($this->subscription());
        $service->notifyPackageConfirmed(SellerPackageSubscription::query()->first());

        foreach ([$locked, $pending] as $id) {
            $row = DB::table('vendor_fulfillment_requests')->find($id);
            $this->assertSame('available', $row->status);
            $this->assertNotNull($row->unlocked_at);
            $this->assertSame(1, DB::table('vendor_fulfillment_events')->where('request_id', $id)->where('event_type', 'unlocked')->count());
        }
        $this->assertSame('locked', DB::table('vendor_fulfillment_requests')->find($closedRow)->status, 'closed orders are left alone');
        $this->assertSame('locked', DB::table('vendor_fulfillment_requests')->find($otherSeller)->status, 'other sellers are untouched');
        $this->assertSame(2, DB::table('notifications')->where('seller_id', 5)->where('sent_to', 'seller')->count());
        $this->assertFalse(app(VendorFulfillmentEligibilityService::class)->hasRestrictions(Order::find($open)));
    }

    public function test_orders_stay_locked_without_a_running_paid_package(): void
    {
        $order = DB::table('orders')->insertGetId(['seller_id' => 5, 'order_status' => 'pending']);
        $row = $this->request($order, 'locked');
        $service = app(VendorFulfillmentUnlockService::class);

        $this->assertFalse($service->hasActivePackage(5));
        foreach ([['payment_status' => 'unpaid'], ['status' => 'scheduled'], ['expires_at' => now()->subMinute()], ['starts_at' => now()->addDay()]] as $case) {
            $service->notifyPackageConfirmed($this->subscription($case));
        }
        $this->assertSame('locked', DB::table('vendor_fulfillment_requests')->find($row)->status);
        $this->assertTrue(app(VendorFulfillmentEligibilityService::class)->hasRestrictions(Order::find($order)));
    }

    public function test_seller_with_an_existing_package_is_released_lazily(): void
    {
        $order = DB::table('orders')->insertGetId(['seller_id' => 5, 'order_status' => 'pending']);
        $row = $this->request($order, 'pending_vendor');
        $service = app(VendorFulfillmentUnlockService::class);

        $service->releaseForSeller(5);
        $this->assertSame('pending_vendor', DB::table('vendor_fulfillment_requests')->find($row)->status);

        $this->subscription();
        $service->releaseForSeller(5);
        $this->assertSame('available', DB::table('vendor_fulfillment_requests')->find($row)->status);
    }
}
