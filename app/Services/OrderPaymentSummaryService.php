<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PostPurchaseInvoice;

class OrderPaymentSummaryService
{
    public function forOrder(Order $order): array
    {
        $lines = [[
            'label' => 'purchase_and_shipping_payment',
            'status' => $order->payment_status === 'paid' ? 'payment_stage_paid' : 'payment_stage_unpaid',
            'tone' => $order->payment_status === 'paid' ? 'success' : 'danger',
            'amount' => null,
        ]];
        $invoice = $order->postPurchaseInvoice;
        $incomplete = false;

        if ($invoice) {
            $remaining = max(0, round($invoice->total_amount - $invoice->paid_amount, 4));
            $paid = $invoice->status === PostPurchaseInvoice::STATUS_PAID && $remaining < 0.0001;
            $status = $paid ? 'payment_stage_paid' : match ($invoice->status) {
                PostPurchaseInvoice::STATUS_AWAITING_REVIEW => 'payment_stage_under_review',
                PostPurchaseInvoice::STATUS_EXPIRED => 'payment_stage_expired',
                PostPurchaseInvoice::STATUS_CANCELLED => 'payment_stage_cancelled',
                default => 'payment_stage_unpaid',
            };
            $tone = $paid ? 'success' : ($invoice->status === PostPurchaseInvoice::STATUS_AWAITING_REVIEW ? 'warning' : 'danger');
            if ($invoice->insurance_amount > 0 || data_get($invoice->insurance_snapshot, 'enabled')) {
                $insurancePaid = $paid || ($invoice->insurance_amount > 0
                    && min($invoice->paid_amount, (float) data_get($invoice->metadata, 'insurance_balance_paid', 0)) >= $invoice->insurance_amount);
                $lines[] = ['label' => 'customer_order_insurance_payment', 'status' => $invoice->insurance_amount > 0 ? ($insurancePaid ? 'payment_stage_paid' : $status) : 'payment_stage_not_required', 'tone' => $invoice->insurance_amount > 0 ? ($insurancePaid ? 'success' : $tone) : 'muted', 'amount' => $invoice->insurance_amount];
            }
            if ($invoice->tax_amount > 0) {
                $lines[] = ['label' => 'second_invoice_tax_payment', 'status' => $status, 'tone' => $tone, 'amount' => $invoice->tax_amount];
            }
            $lines[] = ['label' => 'second_invoice_payment_total', 'status' => $invoice->total_amount > 0 ? $status : 'payment_stage_not_required', 'tone' => $invoice->total_amount > 0 ? $tone : 'muted', 'amount' => $invoice->total_amount];
            $incomplete = $invoice->total_amount > 0 && ! $paid;
            if ($incomplete) {
                $lines[] = ['label' => 'second_invoice_remaining_payment', 'status' => null, 'tone' => $tone, 'amount' => $remaining];
            }
        } elseif ($order->commerce_flow_version === PostPurchaseInvoiceService::CONTRACT_VERSION) {
            $lines[] = ['label' => 'second_invoice_payment_total', 'status' => 'second_invoice_calculated_after_purchase_payment', 'tone' => 'warning', 'amount' => null];
            $incomplete = true;
        } elseif ($order->insurance && $order->insurance->amount > 0) {
            $paid = $order->insurance->payment_status === 'paid';
            $lines[] = ['label' => 'customer_order_insurance_payment', 'status' => $paid ? 'payment_stage_paid' : 'payment_stage_unpaid', 'tone' => $paid ? 'success' : 'danger', 'amount' => $order->insurance->amount];
            $incomplete = ! $paid;
        }

        return ['lines' => $lines, 'incomplete' => $incomplete];
    }
}
