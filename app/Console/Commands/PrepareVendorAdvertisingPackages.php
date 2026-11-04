<?php

namespace App\Console\Commands;

use App\Models\SellerPackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareVendorAdvertisingPackages extends Command
{
    protected $signature = 'vendor:prepare-advertising-packages {--apply : Create missing approved packages; never overwrite existing admin edits}';

    protected $description = 'Preview or create the three approved final-price vendor advertising packages';

    public function handle(): int
    {
        $plans = config('vendor_advertising.packages');
        $this->table(['Name', 'Slots', 'Final EGP', 'Days'], array_map(fn ($plan) => [$plan['name'], $plan['product_limit'], $plan['package_price'], 30], $plans));
        if (! $this->option('apply')) {
            $this->info('Preview only. No database changes.');
            return self::SUCCESS;
        }
        DB::transaction(function () use ($plans) {
            if (! DB::table('vendor_advertising_rollouts')->where('id', 1)->exists()) {
                DB::table('vendor_advertising_rollouts')->insert([
                    'id' => 1, 'seller_cutoff_id' => (int) DB::table('sellers')->max('id'),
                    'starts_at' => now(), 'grace_ends_at' => app(\App\Services\VendorAdvertisingCyclePolicy::class)->transitionDeadline(now())->setTimezone(config('app.timezone')),
                ]);
            }
            foreach ($plans as $plan) {
                SellerPackage::firstOrCreate(['slug' => $plan['slug']], $plan + [
                    'description' => 'مساحات إعلانية لمدة 30 يومًا. السعر نهائي، وتغيير المنتج لا يغير نهاية الدورة.',
                    'product_duration_days' => 30, 'package_validity_days' => 30, 'duration_unit' => 'days', 'duration_value' => 30,
                    'search_promotion_limit' => 0, 'search_promotion_duration_days' => 0,
                    'homepage_promotion_limit' => 0, 'homepage_promotion_duration_days' => 0,
                    'coupon_limit' => 0, 'status' => true, 'cancellation_effect' => 'end_of_period',
                ]);
            }
        });
        $this->info('Missing packages created; existing packages and financial history preserved.');
        return self::SUCCESS;
    }
}
