<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductViewCountService
{
    public function totals(int $productId): array
    {
        return $this->totalsForProducts([$productId])[$productId];
    }

    public function totalsForProducts(array $productIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        if ($ids === []) return [];
        $metrics = []; $unpromoted = []; $adjustments = [];
        $hasMetrics = Schema::hasTable('seller_package_product_metrics');
        $hasUnpromoted = Schema::hasTable('vendor_product_unpromoted_visits');
        $hasAdjustments = Schema::hasTable('product_view_adjustments');
        foreach (array_chunk($ids, 500) as $chunk) {
            if ($hasMetrics) $metrics += DB::table('seller_package_product_metrics')->whereIn('product_id', $chunk)
                ->groupBy('product_id')->selectRaw('product_id, SUM(visits) as total')->pluck('total', 'product_id')->all();
            if ($hasUnpromoted) $unpromoted += DB::table('vendor_product_unpromoted_visits')
                ->whereIn('product_id', $chunk)->pluck('visits', 'product_id')->all();
            if ($hasAdjustments) $adjustments += DB::table('product_view_adjustments')->whereIn('product_id', $chunk)
                ->groupBy('product_id')->selectRaw('product_id, SUM(amount) as total')->pluck('total', 'product_id')->all();
        }
        $totals = [];
        foreach ($ids as $id) {
            $actual = (int) ($metrics[$id] ?? 0) + (int) ($unpromoted[$id] ?? 0);
            $added = (int) ($adjustments[$id] ?? 0);
            $totals[$id] = ['actual' => $actual, 'added' => $added, 'total' => $actual + $added];
        }
        return $totals;
    }

}
