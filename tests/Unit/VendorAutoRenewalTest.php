<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Models\SellerPackage;
use App\Services\SellerLedgerService;
use App\Services\SellerPackagePurchaseService;
use App\Services\VendorAutoRenewalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorAutoRenewalTest extends SellerPackagePaymentsRetryTest
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['vendor_advertising.enabled' => false]);
        (require database_path('migrations/2026_10_14_000001_create_vendor_advertising_slots.php'))->up();
        (require database_path('migrations/2026_10_15_000001_create_vendor_extra_ad_purchases.php'))->up();
        \Illuminate\Support\Facades\Schema::create('products', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id(); $table->string('added_by'); $table->unsignedBigInteger('user_id'); $table->integer('request_status')->default(1); $table->integer('status')->default(1);
        });
        (require database_path('migrations/2026_11_06_000001_create_vendor_auto_renewals.php'))->up();
    }

    protected function tearDown(): void
    {
        (require database_path('migrations/2026_11_06_000001_create_vendor_auto_renewals.php'))->down();
        \Illuminate\Support\Facades\Schema::dropIfExists('products');
        (require database_path('migrations/2026_10_15_000001_create_vendor_extra_ad_purchases.php'))->down();
        (require database_path('migrations/2026_10_14_000001_create_vendor_advertising_slots.php'))->down();
        parent::tearDown();
    }

    private function renewalCycle(float $funds = 6000): array
    {
        config(['vendor_advertising.enabled' => true]);
        $seller = Seller::findOrFail(1);
        $plan = SellerPackage::create(['name' => 'Renewal', 'slug' => 'renew-'.Str::random(8), 'package_price' => 3000,
            'product_limit' => 5, 'product_duration_days' => 30, 'package_validity_days' => 30,
            'search_promotion_limit' => 0, 'homepage_promotion_limit' => 0, 'search_promotion_duration_days' => 0,
            'homepage_promotion_duration_days' => 0, 'coupon_limit' => 0, 'status' => true]);
        app(SellerLedgerService::class)->post(1, 'funding', 'fund-renew', [['bucket' => 'operating', 'direction' => 'credit', 'amount' => $funds]]);
        $cycle = app(SellerPackagePurchaseService::class)->purchaseWithPaymentsBalance($seller, $plan, (string) Str::uuid())['subscription'];
        app(VendorAutoRenewalService::class)->enable($seller, $cycle->id, 3000);
        return [$cycle, $plan];
    }

    public function test_renewal_debits_once_at_expiry(): void
    {
        [$cycle] = $this->renewalCycle();
        $service = app(VendorAutoRenewalService::class);
        $service->process(1);
        $this->assertSame(3000.0, app(SellerLedgerService::class)->summary(1)['operating']);
        $this->travelTo($cycle->expires_at);
        $service->process(1);
        $service->process(1);
        $this->assertSame(0.0, app(SellerLedgerService::class)->summary(1)['operating']);
        $this->assertSame(2, DB::table('seller_package_subscriptions')->where('payment_status', 'paid')->count());
    }

    public function test_insufficient_cash_stops_without_charge(): void
    {
        [$cycle] = $this->renewalCycle(3000);
        \Illuminate\Support\Facades\Schema::create('notifications', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id(); $table->string('sent_by'); $table->string('sent_to'); $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('title')->nullable(); $table->text('description')->nullable(); $table->integer('notification_count')->default(1);
            $table->boolean('status')->default(true); $table->timestamps();
        });
        $this->travelTo($cycle->expires_at);
        app(VendorAutoRenewalService::class)->process(1);
        app(VendorAutoRenewalService::class)->process(1);
        $this->assertFalse((bool) DB::table('vendor_auto_renewals')->value('enabled'));
        $this->assertSame(1, DB::table('seller_package_subscriptions')->where('payment_status', 'paid')->count());
        $this->assertSame(1, DB::table('notifications')->where('seller_id', 1)->where('description', 'like', '%عدم كفاية%')->count());
    }

    public function test_changed_price_stops_without_debit(): void
    {
        [$cycle, $plan] = $this->renewalCycle();
        $plan->update(['package_price' => 4000]);
        $this->travelTo($cycle->expires_at);
        app(VendorAutoRenewalService::class)->process(1);
        $this->assertFalse((bool) DB::table('vendor_auto_renewals')->value('enabled'));
        $this->assertSame(3000.0, app(SellerLedgerService::class)->summary(1)['operating']);
    }

    public function test_prepaid_next_cycle_does_not_trigger_an_extra_charge(): void
    {
        [$cycle, $plan] = $this->renewalCycle(9000);
        $next = app(SellerPackagePurchaseService::class)->purchaseWithPaymentsBalance(Seller::findOrFail(1), $plan, (string) Str::uuid())['subscription'];
        $this->travelTo($cycle->expires_at);
        app(VendorAutoRenewalService::class)->process(1);
        $this->assertSame(3000.0, app(SellerLedgerService::class)->summary(1)['operating']);
        $this->assertSame($next->id, DB::table('vendor_auto_renewals')->value('subscription_id'));
    }

    public function test_enabling_requires_confirmation_of_current_price(): void
    {
        [$cycle, $plan] = $this->renewalCycle();
        $plan->update(['package_price' => 4000]);
        try {
            app(VendorAutoRenewalService::class)->enable(Seller::findOrFail(1), $cycle->id, 3000);
            $this->fail('Changed price accepted');
        } catch (\DomainException $error) {
            $this->assertStringContainsString('تغير سعر الباقة', $error->getMessage());
        }
    }

    public function test_admin_disable_prevents_scheduled_debit(): void
    {
        [$cycle] = $this->renewalCycle();
        $service = app(VendorAutoRenewalService::class);
        $service->disableByAdmin(1, 42);
        $this->travelTo($cycle->expires_at);
        $service->process(1);
        $this->assertSame(3000.0, app(SellerLedgerService::class)->summary(1)['operating']);
        $this->assertSame(42, DB::table('vendor_auto_renewals')->value('disabled_by_admin_id'));
    }

    public function test_scheduled_command_processes_due_renewals(): void
    {
        [$cycle] = $this->renewalCycle();
        config(['vendor_advertising.auto_renewal_enabled' => true]);
        $this->travelTo($cycle->expires_at);
        $this->artisan('vendor:process-auto-renewals')->assertExitCode(0);
        $this->assertSame(0.0, app(SellerLedgerService::class)->summary(1)['operating']);
    }
}
