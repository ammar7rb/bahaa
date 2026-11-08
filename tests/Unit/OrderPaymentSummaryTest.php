<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderEditHistory;
use App\Models\PostPurchaseInvoice;
use App\Services\OrderPaymentSummaryService;
use PHPUnit\Framework\TestCase;

class OrderPaymentSummaryTest extends TestCase
{
    public function test_paid_order_keeps_retired_invoice_as_history_without_another_payment_gate(): void
    {
        $order = new Order(['payment_status'=>'paid', 'commerce_flow_version'=>'post_purchase_v3']);
        $order->setRelation('postPurchaseInvoice', new PostPurchaseInvoice(['status'=>'pending', 'total_amount'=>150, 'paid_amount'=>0]));
        $summary = (new OrderPaymentSummaryService())->forOrder($order);
        $this->assertFalse($summary['incomplete']);
        $this->assertSame('payment_stage_paid', $summary['lines'][0]['status']);
        $this->assertCount(1, $summary['lines']);
    }

    public function test_order_payment_and_review_must_both_be_complete(): void
    {
        $order = new Order(); $order->payment_status='paid'; $order->customer_initial_payment_review_status='pending';
        $service = new OrderPaymentSummaryService();
        $this->assertTrue($service->forOrder($order)['incomplete']);
        $order->customer_initial_payment_review_status='approved';
        $this->assertFalse($service->forOrder($order)['incomplete']);
        $order->payment_status='unpaid';
        $this->assertTrue($service->forOrder($order)['incomplete']);
    }

    public function test_unpaid_edit_balance_is_still_a_payment_due(): void
    {
        $order = new Order(['payment_status'=>'paid', 'edited_status'=>1]);
        $order->setRelation('latestEditHistory', new OrderEditHistory(['order_due_payment_status'=>'unpaid', 'order_due_amount'=>25]));
        $summary = (new OrderPaymentSummaryService())->forOrder($order);
        $this->assertTrue($summary['incomplete']);
        $this->assertEquals(25, $summary['edit_due_amount']);
        $this->assertSame('payment_stage_unpaid', $summary['lines'][1]['status']);
    }
}
