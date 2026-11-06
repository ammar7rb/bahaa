<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\VendorProductWorkspaceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareVendorProductWorkspaces extends Command
{
    protected $signature = 'vendor:prepare-product-workspaces';
    protected $description = 'Capture the permanent legacy product set and private content baseline once; never modify orders or balances.';

    public function handle(VendorProductWorkspaceService $service): int
    {
        DB::transaction(function () use ($service) {
            if (DB::table('vendor_product_workspace_rollouts')->where('id', 1)->exists()) {
                $this->info('Baseline already exists; no new exemptions created.');
                return;
            }
            $cutoff = (int) DB::table('products')->max('id');
            Product::withoutGlobalScopes()->where('added_by', 'seller')->where('id', '<=', $cutoff)
                ->orderBy('id')->chunkById(100, function ($products) use ($service) {
                    foreach ($products as $product) $service->capture($product, true);
                });
            DB::table('vendor_product_workspace_rollouts')->insert([
                'id' => 1, 'baseline_at' => now(), 'product_cutoff_id' => $cutoff,
            ]);
        });
        $this->info('Private baseline captured; existing product records, orders and financial data remain unchanged.');
        return self::SUCCESS;
    }
}
