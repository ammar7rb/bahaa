<?php
namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Utils\OrderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerCancellationStockService
{
    public function restore(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $details = $order->details()->where('is_stock_decreased', 1)->get();
            $ids = $details->pluck('product_id')->filter()->unique()->sort()->values();
            // Different cancelled orders can contain the same product. Lock in
            // a stable order before invoking the existing stock-return logic.
            $found = Product::withoutGlobalScopes()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('id');
            $missing = $details->whereNotIn('product_id', $found)->pluck('id');
            if ($missing->isNotEmpty()) {
                DB::table('order_details')->whereIn('id', $missing)->update([
                    'is_stock_decreased' => 0, 'delivery_status' => 'canceled', 'updated_at' => now(),
                ]);
                Log::warning('Canceled order contains deleted products; no stock to restore', ['order_id' => $order->id]);
            }
            $order->unsetRelation('details');
            OrderManager::getStockUpdateOnOrderStatusChange($order, 'canceled');
        }, 3);
    }
}
