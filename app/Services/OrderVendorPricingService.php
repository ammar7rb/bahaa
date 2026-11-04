<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderVendorPricing;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Immutable procurement approval; customer payment and order lines are never repriced. */
class OrderVendorPricingService
{
    public function summary(Order $order, ?float $procurementOverride = null): array
    {
        $lines = [];
        $net = 0.0;
        $procurement = 0.0;
        $complete = true;
        foreach ($order->details as $line) {
            $original = $line->getRawOriginal('vendor_unit_price_snapshot');
            $complete = $complete && $original !== null && is_numeric($original) && (float) $original >= 0;
            $qty = (int) $line->qty;
            // Existing order summary stores product discount as a line total.
            $sale = (float) $line->price * $qty - (float) $line->discount;
            if ($line->tax_model === 'include') $sale -= (float) $line->tax;
            $net += $sale;
            $cost = $original === null ? null : (float) $original * $qty;
            $procurement += $cost ?? 0;
            $lines[] = ['line_id' => $line->id, 'product_id' => $line->product_id,
                'name' => data_get(json_decode($line->getRawOriginal('product_details') ?? '{}', true), 'name', '#'.$line->product_id),
                'qty' => $qty, 'selling_unit_price' => (float) $line->price,
                'vendor_unit_price' => $original === null ? null : (float) $original,
                'vendor_revision' => $line->getRawOriginal('vendor_content_revision'),
                'product_discount' => (float) $line->discount, 'tax' => (float) $line->tax,
                'sale_before_order_discount' => $sale, 'procurement' => $cost];
        }
        $discount = (float) ($order->discount_amount ?? 0) + (float) ($order->refer_and_earn_discount ?? 0);
        foreach ($lines as &$line) {
            $line['allocated_order_discount'] = $net > 0 ? $discount * $line['sale_before_order_discount'] / $net : 0;
            $line['sale'] = $line['sale_before_order_discount'] - $line['allocated_order_discount'];
        }
        unset($line);
        $originalProcurement = round($procurement, 8);
        $changed = $procurementOverride !== null && abs($originalProcurement - $procurementOverride) > 0.00000001;
        if ($changed) $procurement = $procurementOverride;
        return ['lines' => $lines, 'complete' => $complete && count($lines) > 0,
            'merchandise_sale' => round($net - $discount, 8),
            'procurement_amount' => round($procurement, 8), 'margin' => round($net - $discount - $procurement, 8),
            'previous_procurement_amount' => $changed ? $originalProcurement : null, 'action' => $changed ? 'admin_adjusted_vendor_amount' : 'use_vendor_price',
            'customer_order_amount' => (float) $order->order_amount, 'customer_shipping' => (float) $order->shipping_cost];
    }

    public function approve(Order $order, int $adminId, ?float $procurementOverride = null): OrderVendorPricing
    {
        if ($procurementOverride !== null && (!is_finite($procurementOverride) || $procurementOverride < 0)) throw new DomainException('مبلغ التاجر غير صحيح.');
        return DB::transaction(function () use ($order, $adminId, $procurementOverride): OrderVendorPricing {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($existing = OrderVendorPricing::where('order_id', $locked->id)->first()) {
                if ($procurementOverride !== null && abs((float) $existing->procurement_amount - $procurementOverride) > 0.00000001) {
                    throw new DomainException('تم اعتماد مبلغ التاجر سابقًا؛ يلزم إجراء تعديل مالي مستقل.');
                }
                return $existing;
            }
            if ($locked->seller_is !== 'seller' || app(OrderPaymentSummaryService::class)->forOrder($locked)['incomplete']) {
                throw new DomainException('يجب تأكيد دفع العميل قبل اعتماد سعر التاجر.');
            }
            if (!in_array($locked->commerce_flow_status, ['pending_admin_review', 'admin_assignment_in_progress'], true)) {
                throw new DomainException('اعتماد سعر التاجر متاح أثناء مراجعة الإدارة فقط.');
            }
            $summary = $this->summary($locked, $procurementOverride);
            if (!$summary['complete']) throw new DomainException('سعر التوريد الأصلي غير محفوظ لبعض السطور؛ لا يمكن استنتاجه من سعر المنتج الحالي.');
            $receipt = OrderVendorPricing::create(['order_id' => $locked->id, 'seller_id' => $locked->seller_id,
                'admin_id' => $adminId, 'procurement_amount' => $summary['procurement_amount'],
                'merchandise_sale' => $summary['merchandise_sale'], 'snapshot' => $summary, 'approved_at' => now()]);
            app(OrderFinancialAuditService::class)->record($locked, 'pricing_approved', 'order_vendor_pricings', $receipt->id, [], $adminId);
            return $receipt;
        });
    }
}
