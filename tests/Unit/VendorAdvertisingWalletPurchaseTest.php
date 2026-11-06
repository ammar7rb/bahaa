<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Models\SellerPackage;
use App\Models\SellerPackageSubscription;
use App\Models\VendorAdvertisingSlot;
use App\Services\SellerLedgerService;
use App\Services\SellerPackagePurchaseService;
use Illuminate\Support\Facades\DB;

class VendorAdvertisingWalletPurchaseTest extends SellerPackagePaymentsRetryTest
{
    protected function setUp(): void
    {
        parent::setUp();
        (require base_path('database/migrations/2026_10_14_000001_create_vendor_advertising_slots.php'))->up();
    }
    protected function tearDown(): void
    {
        (require base_path('database/migrations/2026_10_14_000001_create_vendor_advertising_slots.php'))->down();
        parent::tearDown();
    }
    private function slotPlan(): SellerPackage
    {
        config(['vendor_advertising.enabled' => true]);
        return SellerPackage::create(['name' => 'Final price', 'slug' => 'final-price', 'package_price' => 3000, 'product_limit' => 5,
            'product_duration_days' => 30, 'package_validity_days' => 30, 'search_promotion_limit' => 0, 'homepage_promotion_limit' => 0,
            'search_promotion_duration_days' => 0, 'homepage_promotion_duration_days' => 0, 'coupon_limit' => 0, 'status' => true]);
    }
    public function test_current_cycle_and_queued_renewal_charge_exact_final_prices_once_each(): void
    {
        $ledger = app(SellerLedgerService::class);
        $ledger->post(1, 'funding', 'fund-two-cycles', [['bucket' => 'operating', 'direction' => 'credit', 'amount' => 6000]]);
        $service = app(SellerPackagePurchaseService::class);
        $seller = Seller::findOrFail(1); $package = $this->slotPlan();
        $first = $service->purchaseWithPaymentsBalance($seller, $package, 'first-cycle')['subscription'];
        $second = $service->purchaseWithPaymentsBalance($seller, $package, 'next-cycle')['subscription'];
        $service->purchaseWithPaymentsBalance($seller, $package, 'first-cycle');
        $service->purchaseWithPaymentsBalance($seller, $package, 'next-cycle');
        $this->assertSame('active', $first->fresh()->status);
        $this->assertSame('scheduled', $second->fresh()->status);
        $this->assertSame(0.0, $ledger->summary(1)['operating']);
        $this->assertSame(3, DB::table('seller_ledger_entries')->count());
        $this->assertSame(2, SellerPackageSubscription::count());
        $this->assertSame(10, VendorAdvertisingSlot::count());
        $this->assertSame(6000, (int) DB::table('seller_package_transactions')->where('transaction_type', 'package_purchase')->sum('paid_amount'));
    }

    public function test_changed_price_is_not_debited_and_paid_retry_keeps_its_original_receipt(): void
    {
        $ledger = app(SellerLedgerService::class);
        $ledger->post(1, 'funding', 'fund-price-test', [['bucket' => 'operating', 'direction' => 'credit', 'amount' => 6000]]);
        $seller = Seller::findOrFail(1); $package = $this->slotPlan();
        $service = app(SellerPackagePurchaseService::class);
        try {
            $service->purchaseWithPaymentsBalance($seller, $package, 'wrong-price', 2000);
            $this->fail('Changed price accepted');
        } catch (\DomainException $e) { $this->assertSame(6000.0, $ledger->summary(1)['operating']); }
        $this->assertSame(0, SellerPackageSubscription::count());
        $paid = $service->purchaseWithPaymentsBalance($seller, $package, 'confirmed-price', 3000)['subscription'];
        $package->update(['package_price' => 4000]);
        $retry = $service->purchaseWithPaymentsBalance($seller, $package, 'confirmed-price', 3000)['subscription'];
        $this->assertSame($paid->id, $retry->id);
        $this->assertSame(3000.0, $ledger->summary(1)['operating']);
        $this->assertSame(1, SellerPackageSubscription::count());
    }
}
