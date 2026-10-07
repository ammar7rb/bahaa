<?php

namespace Tests\Unit;

use App\Http\Controllers\Vendor\VendorFinanceWorkspaceController;
use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerOrderInsurance;
use App\Models\SellerSettlement;
use App\Services\VendorFinancialWorkspaceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class VendorFinancialWorkspaceTest extends VendorAccountAndInboxTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('seller_settlements', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('order_id');
            $t->decimal('amount', 24, 3); $t->string('status'); $t->timestamp('due_at')->nullable(); $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('seller_settlements');
        parent::tearDown();
    }

    public function test_sales_summaries_exclude_other_vendors_and_separate_settled_amounts(): void
    {
        foreach ([[8, 'held', 30], [8, 'held', 20], [8, 'released', 100], [99, 'held', 900]] as [$seller, $status, $amount]) {
            $order = Order::create(['seller_id' => $seller, 'seller_is' => 'seller']);
            SellerSettlement::create(['seller_id' => $seller, 'order_id' => $order->id, 'amount' => $amount, 'status' => $status, 'due_at' => now()->addDay()]);
        }
        $data = app(VendorFinancialWorkspaceService::class)->invoices(8);
        $this->assertSame(50.0, $data['summary']['total_receivables']);
        $this->assertSame(50.0, $data['summary']['next_due_amount']);
        $this->assertSame(100.0, $data['summary']['settled_total']);
        $this->assertCount(3, $data['records']);
    }

    public function test_locked_invoice_projection_never_contains_full_number_or_receivable_details(): void
    {
        $order = Order::create(['id' => 987654, 'seller_id' => 8, 'seller_is' => 'seller', 'order_amount' => 1000]);
        SellerOrderInsurance::create(['seller_id' => 8, 'order_id' => $order->id, 'status' => 'pending_payment', 'payment_status' => 'unpaid', 'amount' => 100]);
        SellerSettlement::create(['seller_id' => 8, 'order_id' => $order->id, 'amount' => 900, 'status' => 'held', 'due_at' => now()->addDay()]);
        $data = app(VendorFinancialWorkspaceService::class)->invoices(8);
        $this->assertSame(0.0, $data['summary']['total_receivables']);
        $row = $data['records']->first();
        $this->assertTrue($row['locked']);
        $this->assertSame('654', $row['order_number_suffix']);
        $this->assertArrayNotHasKey('invoice_number', $row);
        $this->assertArrayNotHasKey('receivable', $row);
        $this->assertArrayNotHasKey('due_at', $row);
        $this->assertStringNotContainsString('987654', json_encode($row));
        $request = Request::create('/api/v3/seller/finance/invoices/'.$order->id.'/document');
        $request->attributes->set('seller', Seller::findOrFail(8));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new VendorFinanceWorkspaceController())->invoiceDocument($request, $order->id);
    }
}
