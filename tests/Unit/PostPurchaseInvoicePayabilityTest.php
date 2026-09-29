<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\PostPurchaseInvoice;
use PHPUnit\Framework\TestCase;

class PostPurchaseInvoicePayabilityTest extends TestCase
{
    public function test_second_invoice_waits_for_first_payment_approval(): void
    {
        $order = new Order(['payment_status' => 'unpaid']);
        $invoice = new PostPurchaseInvoice([
            'status' => PostPurchaseInvoice::STATUS_PENDING,
            'total_amount' => 612.8,
            'paid_amount' => 0,
        ]);
        $invoice->setRelation('order', $order);

        self::assertFalse($invoice->isPayable());

        $order->payment_status = 'paid';
        self::assertTrue($invoice->isPayable());

        $invoice->status = PostPurchaseInvoice::STATUS_AWAITING_REVIEW;
        self::assertFalse($invoice->isPayable());
    }
}
