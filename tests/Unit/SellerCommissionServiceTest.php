<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Services\SellerCommissionService;
use App\Utils\Helpers;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerCommissionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('approved');
            $table->decimal('sales_commission_percentage', 40, 20)->nullable();
            $table->timestamps();
        });

        DB::table('business_settings')->insert([
            'type' => 'sales_commission',
            'value' => '12.5',
        ]);
        DB::table('sellers')->insert([
            'id' => 1,
            'status' => 'approved',
            'sales_commission_percentage' => null,
        ]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sellers');
        Schema::dropIfExists('business_settings');
        Cache::flush();

        parent::tearDown();
    }

    public function test_system_default_is_used_when_vendor_has_no_override(): void
    {
        $service = app(SellerCommissionService::class);
        $summary = $service->getSummary(Seller::findOrFail(1));

        $this->assertFalse($summary['custom_enabled']);
        $this->assertSame(12.5, $summary['effective_rate']);
        $this->assertSame('system_default', $summary['source']);
        $this->assertSame(25.0, $service->calculate('seller', 1, 200));
    }

    public function test_vendor_override_takes_priority_over_system_default(): void
    {
        $seller = Seller::findOrFail(1);
        $service = app(SellerCommissionService::class);
        $service->updateOverride($seller, true, 4.0);

        $summary = $service->getSummary($seller->fresh());

        $this->assertTrue($summary['custom_enabled']);
        $this->assertSame(4.0, $summary['effective_rate']);
        $this->assertSame('vendor_override', $summary['source']);
        $this->assertSame(8.0, $service->calculate('seller', 1, 200));
    }

    public function test_zero_override_exempts_vendor_and_existing_helper_respects_it(): void
    {
        $seller = Seller::findOrFail(1);
        $service = app(SellerCommissionService::class);
        $service->updateOverride($seller, true, 0.0);

        $summary = $service->getSummary($seller->fresh());

        $this->assertTrue($summary['custom_enabled']);
        $this->assertTrue($summary['exempt']);
        $this->assertSame(0.0, $summary['effective_rate']);
        $this->assertSame('0.00', Helpers::seller_sales_commission('seller', 1, 500));
    }

    public function test_disabling_override_returns_vendor_to_system_default(): void
    {
        $seller = Seller::findOrFail(1);
        $service = app(SellerCommissionService::class);
        $service->updateOverride($seller, true, 3.0);
        $seller = $service->updateOverride($seller->fresh(), false, null);

        $summary = $service->getSummary($seller);

        $this->assertNull($seller->sales_commission_percentage);
        $this->assertFalse($summary['custom_enabled']);
        $this->assertSame(12.5, $summary['effective_rate']);
    }

    public function test_in_house_orders_never_receive_vendor_commission(): void
    {
        $this->assertSame(0.0, app(SellerCommissionService::class)->calculate('admin', 0, 500));
        $this->assertSame(0, Helpers::seller_sales_commission('admin', 0, 500));
    }
}
