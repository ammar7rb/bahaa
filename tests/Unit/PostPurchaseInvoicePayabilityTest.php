<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\PostPurchaseInvoice;
use Tests\TestCase;

class PostPurchaseInvoicePayabilityTest extends TestCase
{
    public function test_second_invoice_is_payable_after_first_payment_proof_is_submitted(): void
    {
        $order = new Order(['payment_status' => 'unpaid']);
        $invoice = new PostPurchaseInvoice([
            'status' => PostPurchaseInvoice::STATUS_PENDING,
            'total_amount' => 612.8,
            'paid_amount' => 0,
        ]);
        $invoice->setRelation('order', $order);

        self::assertFalse($invoice->isPayable());

        $invoice->payment_due_at = \Carbon\Carbon::now()->addDays(2);
        self::assertTrue($invoice->isPayable());

        $invoice->status = PostPurchaseInvoice::STATUS_AWAITING_REVIEW;
        self::assertFalse($invoice->isPayable());
        $invoice->status = PostPurchaseInvoice::STATUS_CANCELLED;
        self::assertFalse($invoice->isPayable());
        $invoice->status = PostPurchaseInvoice::STATUS_PENDING;
        $invoice->payment_due_at = \Carbon\Carbon::now()->subMinute();
        self::assertFalse($invoice->isPayable());
    }
}
