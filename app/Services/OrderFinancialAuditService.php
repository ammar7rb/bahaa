<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderVendorPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Internal audit only; receipts are not an additional revenue or wallet source. */
class OrderFinancialAuditService
{
    public function holdForReview(Order $order, string $reason): void
    {
        if (!Schema::hasTable('order_vendor_pricings') || !OrderVendorPricing::where('order_id', $order->id)->exists()) return;
        if ($settlement = \App\Models\SellerSettlement::where('order_id', $order->id)->first()) {
            app(SellerSettlementService::class)->dispute($settlement, 'Administrative review: '.$reason);
        }
        $this->record($order, 'review_required_'.$reason, 'orders', $order->id, ['reason' => $reason], auth('admin')->id());
    }

    public function record(Order $order, string $event, string $source, int $sourceId, array $data = [], ?int $adminId = null): void
    {
        if (!Schema::hasTable('order_financial_events') || !Schema::hasTable('order_vendor_pricings')) return;
        $pricing = OrderVendorPricing::where('order_id', $order->id)->first();
        if (!$pricing) return; // Prospective, explicitly approved orders only. No historical backfill.
        DB::table('order_financial_events')->insertOrIgnore([
            'order_id' => $order->id, 'seller_id' => $order->seller_id,
            'event_key' => hash('sha256', $event.':'.$source.':'.$sourceId.(!empty($data['operation_key']) ? ':'.$data['operation_key'] : '')),
            'event_type' => $event, 'source_type' => $source, 'source_id' => $sourceId,
            'admin_id' => $adminId, 'created_at' => now(),
            'snapshot' => json_encode($data + [
                'pricing_id' => $pricing->id, 'customer_amount' => $pricing->snapshot['customer_order_amount'],
                'merchandise_sale' => $pricing->merchandise_sale, 'procurement' => $pricing->procurement_amount,
                'merchandise_margin' => round((float)$pricing->merchandise_sale - (float)$pricing->procurement_amount, 8),
                'customer_shipping' => $pricing->snapshot['customer_shipping'],
                'vendor_shipping' => $order->shipping_seller_entitlement,
                'shipping_cost' => $order->shipping_seller_cost,
                'order_status' => $order->order_status,
            ], JSON_THROW_ON_ERROR),
        ]);
    }
}
