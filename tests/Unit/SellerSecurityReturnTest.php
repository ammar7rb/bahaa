<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Seller;
use App\Services\SellerLedgerService;
use App\Services\SellerOrderInsuranceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SellerSecurityReturnTest extends SellerOrderInsuranceServiceTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('seller_order_insurances', function (Blueprint $table): void {
            $table->decimal('confiscated_amount', 24, 3)->default(0);
            $table->string('confiscation_status')->nullable();
        });
    }

    public function test_only_the_unconfiscated_amount_returns_once_to_security_credit(): void
    {
        $seller = Seller::create(['status' => 'approved']);
        $order = Order::create(['seller_id' => $seller->id, 'seller_is' => 'seller', 'order_amount' => 2000]);
        $service = app(SellerOrderInsuranceService::class);
        $insurance = $service->markPaid($service->getOrCreate($order, $seller), 'offline_payment');
        $insurance->forceFill(['confiscated_amount' => 35, 'reusable_at' => now()->subMinute()])->save();
        $this->assertSame(1, $service->releaseReusableCredits());
        $this->assertSame(0, $service->releaseReusableCredits());
        $balances = app(SellerLedgerService::class)->summary($seller->id);
        $this->assertSame(65.0, $balances[SellerLedgerService::ORDER_INSURANCE_CREDIT]);
        $this->assertSame(0.0, $balances[SellerLedgerService::AVAILABLE]);
        $this->assertSame(0.0, $balances[SellerLedgerService::OPERATING]);
    }
}
