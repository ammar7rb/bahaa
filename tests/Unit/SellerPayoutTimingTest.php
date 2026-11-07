<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Models\SellerWallet;
use App\Services\SellerLedgerService;
use App\Services\SellerWithdrawalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SellerPayoutTimingTest extends SellerWithdrawalReservationTest
{
    private function enableTiming(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id(); $table->string('type'); $table->text('value'); $table->timestamps();
        });
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('withdrawal_method_id')->nullable()->change();
            $table->unsignedBigInteger('vendor_withdraw_method_info_id')->nullable()->change();
        });
        (require database_path('migrations/2026_11_04_161000_add_seller_withdrawal_review_deadlines.php'))->up();
    }

    public function test_operating_refund_is_reserved_once_and_rejection_restores_its_original_bucket(): void
    {
        $this->enableTiming();
        $seller = Seller::create();
        SellerWallet::create(['seller_id' => $seller->id, 'total_earning' => 25]);
        $ledger = app(SellerLedgerService::class);
        $ledger->post($seller->id, 'test-funding', 'payout-operating', [['bucket' => 'operating', 'direction' => 'credit', 'amount' => 100]]);
        $service = app(SellerWithdrawalService::class);
        $row = $service->operatingRefund($seller->id, 60, 'operating-one');
        $this->assertSame($row->id, $service->operatingRefund($seller->id, 60, 'operating-one')->id);
        $this->assertSame(40.0, $ledger->summary($seller->id)['operating']);
        $service->finish($row->id, 2, note: 'Rejected');
        $service->finish($row->id, 2, note: 'Rejected');
        $balances = $ledger->summary($seller->id);
        $this->assertSame(100.0, $balances['operating']);
        $this->assertSame(25.0, $balances['available']);
        $this->assertSame(0.0, $balances['pending_withdraw']);
    }

    public function test_operating_refund_approval_starts_configured_deadline_and_requires_transfer_reference(): void
    {
        $this->enableTiming();
        DB::table('business_settings')->insert(['type' => 'seller_operating_refund_days', 'value' => '15']);
        $seller = Seller::create();
        $ledger = app(SellerLedgerService::class);
        $ledger->post($seller->id, 'test-funding', 'payout-due', [['bucket' => 'operating', 'direction' => 'credit', 'amount' => 100]]);
        $service = app(SellerWithdrawalService::class);
        $row = $service->operatingRefund($seller->id, 60, 'operating-due');
        $this->travel(4)->days();
        $approved = $service->finish($row->id, 1);
        $this->assertSame(now()->addDays(15)->startOfSecond()->toDateTimeString(), $approved->payout_due_at->toDateTimeString());
        $this->assertSame(0, $approved->approved);
        try {
            $service->finish($row->id, 1, payoutReference: 'ref-1');
            $this->fail('Early payout accepted');
        } catch (\DomainException) {
            $this->assertSame(60.0, $ledger->summary($seller->id)['pending_withdraw']);
        }
        $this->travel(15)->days();
        try {
            $service->finish($row->id, 1);
            $this->fail('Payout without transfer reference accepted');
        } catch (\DomainException) {
            $this->assertSame(0, $row->fresh()->approved);
        }
        $service->finish($row->id, 1, payoutReference: 'ref-1');
        $service->finish($row->id, 1, payoutReference: 'ref-1');
        $balances = $ledger->summary($seller->id);
        $this->assertSame(40.0, $balances['operating']);
        $this->assertSame(60.0, $balances['withdrawn']);
        $this->assertSame(0.0, $balances['pending_withdraw']);
        $this->assertSame('ref-1', $row->fresh()->payout_reference);
    }

    public function test_sales_payout_has_its_own_approval_deadline_and_keeps_operating_balance_separate(): void
    {
        $this->enableTiming();
        DB::table('business_settings')->insert(['type' => 'seller_sales_withdrawal_days', 'value' => '20']);
        $seller = Seller::create();
        SellerWallet::create(['seller_id' => $seller->id, 'total_earning' => 100]);
        $definition = \App\Models\WithdrawalMethod::create(['method_name' => 'InstaPay']);
        $method = \App\Models\VendorWithdrawMethodInfo::create([
            'user_id' => $seller->id, 'withdraw_method_id' => $definition->id, 'method_name' => 'Recipient',
            'method_type' => 'instapay', 'approval_status' => 'approved', 'is_active' => true, 'method_info' => ['recipient_name' => 'Seller'],
        ]);
        $service = app(SellerWithdrawalService::class);
        $row = $service->submit($seller->id, $method->id, 40, 'sales-one');
        $approved = $service->finish($row->id, 1);
        $this->assertSame(now()->addDays(20)->startOfSecond()->toDateTimeString(), $approved->payout_due_at->toDateTimeString());
        $this->assertSame(0, $approved->approved);
        $this->travel(20)->days();
        $service->finish($row->id, 1, payoutReference: 'sales-transfer-1');
        $balances = app(SellerLedgerService::class)->summary($seller->id);
        $this->assertSame(60.0, $balances['available']);
        $this->assertSame(40.0, $balances['withdrawn']);
        $this->assertSame(0.0, $balances['operating']);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('business_settings');
        parent::tearDown();
    }
}
