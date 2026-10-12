<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\SellerSettlement;
use App\Models\SellerWallet;
use Illuminate\Support\Facades\DB;

/** Preserve existing commission/tax/shipping calculations, defer their entitlement. */
class SellerDeliveredEarningService
{
    public function record(Order $order, callable $legacyCalculation): mixed
    {
        if ($order->seller_is !== 'seller') return $legacyCalculation();
        return DB::transaction(function () use ($order, $legacyCalculation) {
            $ledger = app(SellerLedgerService::class);
            $ledger->lockSellerFinancialState((int) $order->seller_id);
            $ledger->initializeFromLegacy((int) $order->seller_id);
            if (SellerSettlement::where('order_id', $order->id)->exists()
                || OrderTransaction::where('order_id', $order->id)->where('status', 'disburse')->exists()) return true;
            $before = (float) SellerWallet::where('seller_id', $order->seller_id)->value('total_earning');
            $result = $ledger->withoutWalletSync($legacyCalculation);
            $after = (float) SellerWallet::where('seller_id', $order->seller_id)->value('total_earning');
            $earning = round($after - $before, 12);
            if ($earning > 0) {
                $ledger->post((int) $order->seller_id, 'seller_delivered_sales', 'delivered-sales:'.$order->id,
                    [['bucket' => SellerLedgerService::SALES_TOTAL, 'direction' => 'credit', 'amount' => $earning]], Order::class, $order->id, initializeLegacy: false);
                app(SellerSettlementService::class)->holdDeliveredOrder($order, $earning, $order->sales_settlement_due_at);
            }
            return $result;
        });
    }
}
