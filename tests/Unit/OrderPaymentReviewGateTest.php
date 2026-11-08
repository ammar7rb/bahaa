<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderPaymentSummaryService;
use PHPUnit\Framework\TestCase;

class OrderPaymentReviewGateTest extends TestCase
{
    public function test_paid_order_with_unapproved_proof_remains_incomplete(): void
    {
        $order = new Order(); $order->payment_status='paid'; $order->customer_initial_payment_review_status='pending';
        $this->assertTrue((new OrderPaymentSummaryService())->forOrder($order)['incomplete']);
        $order->customer_initial_payment_review_status='approved';
        $this->assertFalse((new OrderPaymentSummaryService())->forOrder($order)['incomplete']);
    }
}
