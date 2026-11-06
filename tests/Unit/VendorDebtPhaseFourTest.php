<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Models\SellerBalanceDeposit;
use App\Services\SellerBalanceDepositService;
use App\Services\SellerLedgerService;
use App\Services\VendorDebtService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendorDebtPhaseFourTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('sellers', fn (Blueprint $t) => [$t->id(), $t->string('email')->nullable(), $t->timestamps()]);
        Schema::create('seller_wallets', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->decimal('total_earning',40,20)->default(0);
            $t->decimal('pending_withdraw',40,20)->default(0); $t->decimal('withdrawn',40,20)->default(0); $t->timestamps();
        });
        Schema::create('seller_ledger_entries', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->string('bucket'); $t->string('direction'); $t->decimal('amount',40,20);
            $t->string('event_type'); $t->string('group_key'); $t->string('idempotency_key')->unique();
            $t->string('reference_type')->nullable(); $t->integer('reference_id')->nullable(); $t->string('reference_code')->nullable();
            $t->string('reporting_category')->nullable(); $t->json('metadata')->nullable(); $t->string('previous_hash')->nullable();
            $t->string('entry_hash'); $t->integer('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('seller_balance_deposits', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->decimal('amount',24,8); $t->string('currency_code')->default('EGP');
            $t->string('source'); $t->string('status'); $t->string('payment_request_id')->nullable();
            $t->string('payment_method')->nullable(); $t->string('transaction_reference')->nullable(); $t->json('metadata')->nullable();
            $t->json('offline_information')->nullable(); $t->json('payment_proof')->nullable(); $t->text('payment_note')->nullable();
            $t->text('review_note')->nullable(); $t->text('rejection_reason')->nullable(); $t->integer('reviewed_by_admin_id')->nullable();
            $t->timestamp('reviewed_at')->nullable(); $t->timestamp('paid_at')->nullable(); $t->timestamp('reversed_at')->nullable();
            $t->integer('reversed_by_admin_id')->nullable(); $t->string('idempotency_key')->unique(); $t->timestamps();
        });
        (require database_path('migrations/2026_11_06_000002_create_vendor_debt_ledger.php'))->up();
        DB::table('sellers')->insert(['id' => 7, 'email' => 'debt@example.test']);
        DB::table('seller_wallets')->insert(['seller_id' => 7, 'total_earning' => 1000]);
        app(SellerLedgerService::class)->summary(7);
    }

    private function debt(): VendorDebtService { return app(VendorDebtService::class); }
    private function charge(float|string $amount = 100, string $id = '1', string $kind = 'cancellation_penalty'): object
    {
        return $this->debt()->assess(7, $kind, 'fulfillment_request', $id, 'charge-'.$id, $amount,
            ['mode' => 'fixed', 'fixed_amount' => $amount, 'reason_code' => 'cancel_after_acceptance', 'admin_secret' => 'NEVER-VENDOR'], 9);
    }
    private function fund(float $amount, string $target = 'operating'): SellerBalanceDeposit
    {
        return app(SellerBalanceDepositService::class)->createDigital(Seller::findOrFail(7), $amount, 'EGP', 'test_gateway', $target);
    }
    private function paid(SellerBalanceDeposit $deposit): SellerBalanceDeposit
    {
        return app(SellerBalanceDepositService::class)->markPaid($deposit, ['reference' => 'verified-'.$deposit->id]);
    }
    private function operating(): float { return app(SellerLedgerService::class)->summary(7)['operating']; }

    public function test_partial_wallet_collection_creates_only_the_unpaid_debt(): void
    {
        $this->paid($this->fund(30));
        $charge = $this->charge();
        $this->assertEquals(30, $charge->wallet_collected);
        $this->assertEquals(70, $charge->outstanding);
        $this->assertEquals(0, $this->operating());
        $this->assertEquals(1000, app(SellerLedgerService::class)->summary(7)['available']);
    }

    public function test_smaller_then_larger_deposits_recover_debt_before_credit(): void
    {
        $this->charge();
        $small = $this->paid($this->fund(60));
        $this->assertEquals(40, $this->debt()->outstanding(7));
        $this->assertEquals(0, $this->operating());
        $this->assertEquals(60, $small->debt_allocation['debt_recovered']);
        $large = $this->paid($this->fund(80));
        $this->assertEquals(0, $this->debt()->outstanding(7));
        $this->assertEquals(40, $this->operating());
        $this->assertEquals(40, $large->debt_allocation['net_wallet_credit']);
        $this->assertEquals(1000, app(SellerLedgerService::class)->summary(7)['available']);
    }

    public function test_exact_deposit_clears_debt_without_spendable_credit(): void
    {
        $this->charge(); $deposit = $this->paid($this->fund(100));
        $this->assertEquals(0, $this->operating());
        $this->assertEquals(0, $this->debt()->outstanding(7));
        $this->assertEquals(100, $deposit->debt_allocation['debt_recovered']);
        $this->assertEquals(0, DB::table('seller_ledger_entries')->where('event_type','operating_deposit')->count());
    }

    public function test_unconfirmed_deposit_only_previews_without_recovering_debt(): void
    {
        $this->charge(); $deposit = $this->fund(120);
        $preview = $this->debt()->preview(7, 120);
        $this->assertEquals(20, $preview['net_wallet_credit']);
        $this->assertEquals(100, $this->debt()->outstanding(7));
        $this->assertNull($deposit->debt_allocation);
        $this->assertSame(0, DB::table('vendor_debt_repayments')->count());
    }

    public function test_approval_recalculates_after_preview_and_additional_charge(): void
    {
        $this->charge(50); $preview = $this->debt()->preview(7, 80);
        $this->assertEquals(30, $preview['net_wallet_credit']);
        $this->charge(50, '2'); $deposit = $this->paid($this->fund(80));
        $this->assertEquals(0, $deposit->debt_allocation['net_wallet_credit']);
        $this->assertEquals(20, $deposit->debt_allocation['remaining_debt']);
    }

    public function test_fifo_references_cover_both_slot_charges_and_penalties(): void
    {
        $first = $this->charge(80, '1', 'additional_slot'); $second = $this->charge(30, '2');
        $deposit = $this->paid($this->fund(90));
        $refs = $deposit->debt_allocation['references'];
        $this->assertSame($first->id, $refs[0]['charge_id']); $this->assertEquals(80, $refs[0]['amount']);
        $this->assertSame($second->id, $refs[1]['charge_id']); $this->assertEquals(10, $refs[1]['amount']);
        $this->assertEquals(20, $this->debt()->outstanding(7));
    }

    public function test_repeated_charge_and_callbacks_cannot_duplicate_any_financial_effect(): void
    {
        $this->paid($this->fund(30)); $one = $this->charge(); $two = $this->charge();
        $this->assertSame($one->id, $two->id);
        $deposit = $this->fund(100); $this->paid($deposit); $before = DB::table('vendor_debt_events')->count();
        $this->paid($deposit->fresh());
        $this->assertEquals(30, $this->operating());
        $this->assertSame($before, DB::table('vendor_debt_events')->count());
        $this->assertSame(1, DB::table('vendor_debt_charges')->count());
        $this->assertSame(2, DB::table('vendor_deposit_allocations')->count());
    }

    public function test_reversal_restores_debt_and_removes_only_the_original_net_credit(): void
    {
        $this->charge(60); $deposit = $this->paid($this->fund(100));
        $service = app(SellerBalanceDepositService::class);
        $service->reverse($deposit, 9, 'Test reversal');
        $service->reverse($deposit->fresh(), 9, 'Repeated reversal');
        $this->assertEquals(60, $this->debt()->outstanding(7));
        $this->assertEquals(0, $this->operating());
        $this->assertSame(1, DB::table('vendor_debt_events')->where('event_type','deposit_allocation_reversed')->count());
        $this->assertNotNull($deposit->fresh()->debt_allocation['reversed_at']);
    }

    public function test_spent_credit_blocks_reversal_atomically_without_negative_balance(): void
    {
        $this->charge(60); $deposit = $this->paid($this->fund(100)); $this->charge(40, '2', 'additional_slot');
        try { app(SellerBalanceDepositService::class)->reverse($deposit, 9, 'Spent funds'); $this->fail('Spent credit was reversed'); }
        catch (\DomainException) { $this->assertEquals(0, $this->debt()->outstanding(7)); }
        $this->assertSame('paid', $deposit->fresh()->status);
        $this->assertNull($deposit->fresh()->debt_allocation['reversed_at']);
        $this->assertEquals(0, $this->operating());
    }

    public function test_sales_and_restricted_credit_are_never_used_for_debt_recovery(): void
    {
        $this->charge(); $this->paid($this->fund(50, 'insurance'));
        $this->assertEquals(100, $this->debt()->outstanding(7));
        $summary = app(SellerLedgerService::class)->summary(7);
        $this->assertEquals(1000, $summary['available']);
        $this->assertEquals(50, $summary['order_insurance_credit']);
        $this->assertEquals(0, $summary['operating']);
    }

    public function test_financials_separate_assessment_collection_and_deposit_without_double_income(): void
    {
        $this->paid($this->fund(30)); $this->charge(); $this->paid($this->fund(100));
        $workspace = app(\App\Services\Admin\VendorDebtReadService::class)->workspace([]);
        $summary = $workspace['debtSummary'];
        $this->assertEquals(100, $summary['assessed']); $this->assertEquals(30, $summary['wallet_collected']);
        $this->assertEquals(70, $summary['deposit_recovered']); $this->assertEquals(0, $summary['outstanding']);
        $this->assertEquals(130, $summary['gross_deposits']); $this->assertEquals(60, $summary['net_wallet_credit']);
        $this->assertSame(1, DB::table('vendor_debt_events')->where('event_type','charge_assessed')->count());
        $financials = \Mockery::mock(\App\Services\Admin\FinancialsService::class);
        $financials->shouldReceive('baseCurrency')->andReturn('EGP');
        $this->app->instance(\App\Services\Admin\FinancialsService::class, $financials);
        $incoming = app(\App\Services\Admin\FinancialActivityReadService::class)->workspace([]);
        $this->assertEquals(130, $incoming['totals']['external']->sum('amount'));
        $this->assertSame(2, $incoming['records']->where('source', 'vendor_deposits')->count());
    }

    public function test_concurrent_same_or_different_deposit_approvals_are_serialized(): void
    {
        $this->charge();
        $a = $this->fund(80); $b = $this->fund(80);
        $folder = storage_path('framework/testing');
        if (!is_dir($folder)) mkdir($folder, 0777, true);
        $file = $folder.'/vendor-debt-'.bin2hex(random_bytes(8)).'.sqlite';
        DB::statement('VACUUM INTO ?', [$file]);
        try {
            $workers = array_map(fn ($id) => new \Symfony\Component\Process\Process([PHP_BINARY, base_path('tests/Support/vendor-debt-worker.php'), $file, (string)$id]), [$a->id, $a->id, $b->id]);
            foreach ($workers as $worker) $worker->start();
            foreach ($workers as $index=>$worker) { $worker->wait(); $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput()); $this->assertSame('paid:'.[$a->id,$a->id,$b->id][$index],trim($worker->getOutput())); }
            $pdo = new \PDO('sqlite:'.$file);
            $this->assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM vendor_deposit_allocations')->fetchColumn());
            $this->assertEquals(100, $pdo->query('SELECT SUM(amount) FROM vendor_debt_repayments')->fetchColumn());
            $this->assertEquals(60, $pdo->query('SELECT SUM(net_wallet_credit) FROM vendor_deposit_allocations')->fetchColumn());
            $this->assertEquals(0, $pdo->query('SELECT SUM(outstanding) FROM vendor_debt_charges')->fetchColumn());
            $pdo = null;
        } finally { $pdo = null; if (is_file($file)) @unlink($file); }
    }

    public function test_rejected_deposit_does_not_recover_debt(): void
    {
        $this->charge(); $deposit=$this->fund(100); $deposit->update(['source'=>'offline']);
        app(SellerBalanceDepositService::class)->reject($deposit, 1, 'not confirmed');
        $this->assertEquals(100, $this->debt()->outstanding(7));
        $this->assertSame(0, DB::table('vendor_deposit_allocations')->count());
    }

    public function test_completed_rollout_defaults_to_enabled_and_can_be_explicitly_disabled(): void
    {
        config(['vendor_order_eligibility.enabled'=>false]);
        $this->assertFalse(app(\App\Services\VendorFulfillmentEligibilityService::class)->available());
        $this->assertStringContainsString("env('VENDOR_ORDER_ELIGIBILITY_ENABLED', true)", file_get_contents(config_path('vendor_order_eligibility.php')));
    }

    public function test_rule_snapshot_is_immutable_and_private_administrative_fields_are_not_returned(): void
    {
        $charge = $this->charge();
        $this->assertStringNotContainsString('NEVER-VENDOR', json_encode($this->debt()->presentation(7)));
        try { $this->charge(200); $this->fail('Conflicting reassessment accepted'); } catch (\DomainException) {}
        $this->assertSame($charge->rule_snapshot, DB::table('vendor_debt_charges')->find($charge->id)->rule_snapshot);
        $this->assertEquals(100, $this->debt()->outstanding(7));
    }
}

