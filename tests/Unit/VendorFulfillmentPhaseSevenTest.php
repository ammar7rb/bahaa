<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\VendorFulfillmentReportController;
use App\Models\Order;
use App\Models\Seller;
use App\Services\Admin\VendorFulfillmentReportService;
use App\Services\SellerBalanceDepositService;
use App\Services\VendorDebtService;
use App\Services\VendorFulfillmentEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\VendorFulfillmentFixture;
use Tests\TestCase;

class VendorFulfillmentPhaseSevenTest extends TestCase
{
    use VendorFulfillmentFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFulfillmentFixture();
    }

    private function reports(): VendorFulfillmentReportService
    {
        return app(VendorFulfillmentReportService::class);
    }

    private function fund(int $amount): \App\Models\SellerBalanceDeposit
    {
        $service = app(SellerBalanceDepositService::class);

        return $service->markPaid($service->createDigital(Seller::find(7), $amount, 'EGP', 'test'));
    }

    private function charge(): object
    {
        return app(VendorDebtService::class)->assess(7, 'cancellation_penalty', 'fulfillment_request', '1', 'phase7-charge', 100, ['mode' => 'fixed', 'fixed_amount' => 100]);
    }

    public function test_filters_use_forwarding_time_and_preserve_partial_access_classification(): void
    {
        $row = app(VendorFulfillmentEligibilityService::class)->forward(Order::find(1))->first();
        DB::table('vendor_fulfillment_requests')->insert(['seller_id' => 7, 'order_id' => 1, 'portion' => 'advertising', 'status' => 'available', 'vendor_amount' => 30, 'shipping_amount' => 0, 'forwarded_at' => now()]);
        $this->assertSame([$row->id], $this->reports()->requests(['restriction' => 'partial'])->pluck('r.id')->all());
        $this->assertSame(2, $this->reports()->requests(['from' => today()->toDateString()])->count());
        $this->assertSame(0, $this->reports()->requests(['to' => today()->subDay()->toDateString()])->count());
        $this->assertSame(0, $this->reports()->requests(['seller_id' => 8])->count());
        $this->assertSame(1, $this->reports()->requests(['portion' => 'outside_slot', 'status' => 'locked', 'order_id' => 1])->count());
        $row->update(['status' => 'canceled']);
        $this->assertSame(1, $this->reports()->requests(['restriction' => 'partial'])->count());
        $row->update(['unlocked_at' => now()]);
        $this->assertSame(0, $this->reports()->requests(['restriction' => 'restricted'])->count());
    }

    public function test_reconciliation_matches_wallet_debt_and_deposits_without_double_counting(): void
    {
        $this->fund(30);
        $this->charge();
        $deposit = $this->fund(100);
        app(SellerBalanceDepositService::class)->markPaid($deposit);
        $data = $this->reports()->reconciliation(['seller_id' => 7, 'from' => '2099-01-01']);
        $this->assertSame(1, $data['charges_checked']);
        $this->assertSame(2, $data['deposits_checked']);
        $this->assertSame(0, $data['charge_issues']);
        $this->assertSame(0, $data['deposit_issues']);
        $charge = $this->reports()->charges([])->first();
        $this->assertEquals(100, $charge->amount);
        $this->assertEquals(30, $charge->actual_wallet_collected);
        $this->assertEquals(70, $charge->deposit_recovered);
        $this->assertEquals(0, $charge->outstanding);
        $this->assertSame(0, $this->reports()->reconciliation(['seller_id' => 8])['charges_checked']);
    }

    public function test_reversed_deposit_is_excluded_and_restored_debt_still_reconciles(): void
    {
        \Illuminate\Support\Facades\Schema::table('seller_balance_deposits', fn ($t) => $t->unsignedBigInteger('reversed_by_admin_id')->nullable());
        $this->charge();
        $deposit = $this->fund(60);
        app(SellerBalanceDepositService::class)->reverse($deposit, 1, 'isolated test');
        $report = $this->reports()->reconciliation([]);
        $this->assertSame(0, $report['deposits_checked']);
        $this->assertSame(0, $report['charge_issues']);
        $this->assertEquals(100, $this->reports()->charges([])->first()->outstanding);
    }

    public function test_read_only_reconciliation_detects_differences_without_repairing_balances(): void
    {
        $this->charge();
        $this->fund(60);
        DB::table('vendor_debt_charges')->update(['outstanding' => 41]);
        DB::table('vendor_deposit_allocations')->update(['net_wallet_credit' => 1]);
        $before = DB::table('vendor_debt_events')->count();
        $report = $this->reports()->reconciliation([]);
        $this->assertSame(1, $report['charge_issues']);
        $this->assertSame(1, $report['deposit_issues']);
        $this->assertEquals(41, DB::table('vendor_debt_charges')->value('outstanding'));
        $this->assertSame($before, DB::table('vendor_debt_events')->count());
    }

    public function test_exports_keep_assessment_collection_and_net_deposit_separate_and_scope_vendor(): void
    {
        $this->charge();
        $this->fund(60);
        $charges = iterator_to_array($this->reports()->exportRows('charges', ['seller_id' => 7]));
        $deposits = iterator_to_array($this->reports()->exportRows('deposits', ['seller_id' => 7]));
        $this->assertCount(2, $charges);
        $this->assertEquals(100, $charges[1][5]);
        $this->assertEquals(60, $charges[1][7]);
        $this->assertEquals(40, $charges[1][8]);
        $this->assertEquals(60, $deposits[1][2]);
        $this->assertEquals(60, $deposits[1][3]);
        $this->assertEquals(0, $deposits[1][4]);
        $references = json_decode($deposits[1][9], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('cancellation_penalty', $references[0]['kind']);
        $this->assertEquals(60, $references[0]['amount']);
        $this->assertCount(1, iterator_to_array($this->reports()->exportRows('charges', ['seller_id' => 8])));
    }

    public function test_csv_export_neutralizes_formula_payloads_and_never_includes_customer_editorial_content(): void
    {
        $row = app(VendorFulfillmentEligibilityService::class)->forward(Order::find(1))->first();
        $row->update(['decision_reason' => "\t=HYPERLINK(\"https://invalid.test\")"]);
        $controller = app(VendorFulfillmentReportController::class);
        $response = $controller->export(Request::create('/', 'GET', ['dataset' => 'requests']), $this->reports());
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString("'\t=HYPERLINK", $csv);
        $this->assertStringNotContainsString('SECRET-EDITORIAL', $csv);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        foreach (['=1+1', ' +SUM(1)', '-2+3', '@SUM(1)', "\n=3"] as $attack) {
            $this->assertSame("'".$attack, $controller::csvCell($attack));
        }
        $this->assertSame('100.00', $controller::csvCell('100.00'));
    }

    public function test_report_and_export_are_admin_reports_only_get_routes_with_export_rate_limit(): void
    {
        foreach (['index', 'export'] as $action) {
            $route = app('router')->getRoutes()->getByName('admin.report.vendor-fulfillment.'.$action);
            $this->assertNotNull($route);
            $this->assertContains('admin', $route->gatherMiddleware());
            $this->assertContains('module:reports', $route->gatherMiddleware());
            $this->assertSame(['GET', 'HEAD'], $route->methods());
            if ($action === 'export') {
                $this->assertContains('throttle:10,1', $route->gatherMiddleware());
            }
        }
    }

    public function test_invalid_filter_values_and_reverse_date_ranges_are_rejected(): void
    {
        $controller = app(VendorFulfillmentReportController::class);
        foreach ([['dataset' => 'sql'], ['from' => '2026-11-06', 'to' => '2026-11-05'], ['seller_id' => '7 OR 1=1']] as $input) {
            try {
                $controller->export(Request::create('/', 'GET', $input), $this->reports());
                $this->fail('Invalid report input accepted.');
            } catch (\Illuminate\Validation\ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_fixed_ten_pound_rule_deducts_partial_wallet_once_and_recovers_remainder_from_next_deposit(): void
    {
        $rule = app(\App\Services\VendorCancellationPenaltyService::class);
        $rule->save(['enabled' => true, 'mode' => 'fixed', 'fixed_amount' => '10.00', 'percentage' => 0], 1);
        $quote = $rule->quote(140);
        $this->assertSame('10.00', $quote['assessed_amount']);
        $this->fund(4);
        $charge = app(VendorDebtService::class)->assess(7, 'cancellation_penalty', 'fulfillment_request', '10', 'ten-pound-example', $quote['assessed_amount'], $quote);
        $this->assertEquals(4, $charge->wallet_collected);
        $this->assertEquals(6,$charge->debt_assessed);
        $deposit = $this->fund(8);
        $this->assertEquals(6,$deposit->debt_allocation['debt_recovered']);
        $this->assertEquals(2,$deposit->debt_allocation['net_wallet_credit']);
        $this->assertSame(0,$this->reports()->reconciliation([])['charge_issues']);
    }
}
