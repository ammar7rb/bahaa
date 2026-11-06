<?php

namespace App\Services;

use App\Models\Order;

class OrderPaymentSummaryService
{
    public function forOrder(Order $order): array
    {
        // Only the sales order is payable here. Legacy invoices and deposits
        // remain financial history and must never change its payment state.
        $paid = $order->payment_status === 'paid';
        $edit = $order->edited_status == 1 ? $order->latestEditHistory : null;
        $editUnpaid = $edit && $edit->order_due_payment_status === 'unpaid' && (float) $edit->order_due_amount > 0;
        $lines = [[
            'label' => 'payment_status',
            'status' => $paid ? 'payment_stage_paid' : 'payment_stage_unpaid',
            'tone' => $paid ? 'success' : 'warning',
            'amount' => null,
        ]];
        if ($editUnpaid) {
            $lines[] = ['label' => 'due', 'status' => 'payment_stage_unpaid', 'tone' => 'warning', 'amount' => (float) $edit->order_due_amount];
        }
        return ['lines' => $lines, 'incomplete' => !$paid || $editUnpaid,
            'edit_due_amount' => $editUnpaid ? (float) $edit->order_due_amount : 0];
    }
}
