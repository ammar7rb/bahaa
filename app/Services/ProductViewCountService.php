<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductViewCountService
{
    public function totals(int $productId): array
    {
        $actual = Schema::hasTable('seller_package_product_metrics')
            ? (int) DB::table('seller_package_product_metrics')->where('product_id', $productId)->sum('visits') : 0;
        $added = Schema::hasTable('product_view_adjustments')
            ? (int) DB::table('product_view_adjustments')->where('product_id', $productId)->sum('amount') : 0;
        return ['actual' => $actual, 'added' => $added, 'total' => $actual + $added];
    }

    public function add(int $productId, int $adminId, int $amount): void
    {
        if ($amount < 1 || $amount > 1000000) {
            throw new \InvalidArgumentException('Invalid view adjustment.');
        }
        DB::table('product_view_adjustments')->insert([
            'product_id' => $productId, 'admin_id' => $adminId, 'amount' => $amount,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
