<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\PostPurchaseInvoice;
use App\Services\OrderPaymentSummaryService;
use PHPUnit\Framework\TestCase;

class OrderPaymentSummaryTest extends TestCase
{
    public function test_purchase_paid_does_not_hide_unpaid_insurance_and_tax(): void
    {
        $summary = $this->summary(['status' => 'pending', 'insurance_amount' => 160000, 'tax_amount' => 313600, 'total_amount' => 473600, 'paid_amount' => 0]);
        $this->assertTrue($summary['incomplete']);
        $this->assertSame('payment_stage_paid', $summary['lines'][0]['status']);
        $this->assertSame('payment_stage_unpaid', $summary['lines'][1]['status']);
        $this->assertSame(160000.0, $summary['lines'][1]['amount']);
        $this->assertSame(473600.0, $summary['lines'][4]['amount']);
    }

    public function test_submitted_proof_is_shown_as_under_review_not_as_paid(): void
    {
        $summary = $this->summary(['status' => 'awaiting_review', 'insurance_amount' => 100, 'total_amount' => 100, 'paid_amount' => 0]);
        $this->assertTrue($summary['incomplete']);
        $this->assertSame('payment_stage_under_review', $summary['lines'][1]['status']);
    }

    public function test_insurance_balance_payment_does_not_mark_remaining_tax_paid(): void
    {
        $summary = $this->summary(['status' => 'pending', 'insurance_amount' => 100, 'tax_amount' => 50, 'total_amount' => 150, 'paid_amount' => 100, 'metadata' => ['insurance_balance_paid' => 100]]);
        $this->assertSame('payment_stage_paid', $summary['lines'][1]['status']);
        $this->assertSame('payment_stage_unpaid', $summary['lines'][2]['status']);
        $this->assertSame(50.0, $summary['lines'][4]['amount']);
        $this->assertTrue($summary['incomplete']);
    }

    public function test_fully_paid_second_invoice_clears_warning(): void
    {
        $summary = $this->summary(['status' => 'paid', 'insurance_amount' => 100, 'tax_amount' => 50, 'total_amount' => 150, 'paid_amount' => 150]);
        $this->assertFalse($summary['incomplete']);
        $this->assertSame('payment_stage_paid', $summary['lines'][1]['status']);
        $this->assertCount(4, $summary['lines']);
    }

    public function test_second_invoice_not_yet_calculated_is_not_presented_as_complete(): void
    {
        $order = new Order(['payment_status' => 'paid', 'commerce_flow_version' => 'post_purchase_v3']);
        $order->setRelation('postPurchaseInvoice', null);
        $summary = (new OrderPaymentSummaryService)->forOrder($order);
        $this->assertTrue($summary['incomplete']);
        $this->assertSame('second_invoice_calculated_after_purchase_payment', $summary['lines'][1]['status']);
    }

    private function summary(array $invoice): array
    {
        $order = new Order(['payment_status' => 'paid', 'commerce_flow_version' => 'post_purchase_v3']);
        $order->setRelation('postPurchaseInvoice', new PostPurchaseInvoice(array_replace(['tax_amount' => 0, 'insurance_snapshot' => ['enabled' => true]], $invoice)));

        return (new OrderPaymentSummaryService)->forOrder($order);
    }
}
