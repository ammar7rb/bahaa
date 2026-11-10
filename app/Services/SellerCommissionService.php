<?php

namespace App\Services;

use App\Models\Seller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SellerCommissionService
{
    public function getSummary(?Seller $seller): array
    {
        $defaultRate = (float) (getWebConfig(name: 'sales_commission') ?? 0);
        $hasCustomRate = $seller?->sales_commission_percentage !== null;
        $customRate = $hasCustomRate ? (float) $seller->sales_commission_percentage : null;
        $group = !$hasCustomRate && $seller && Schema::hasTable('business_rate_group_accounts')
            ? DB::table('business_rate_group_accounts as membership')
                ->join('business_rate_groups as groups', 'groups.id', '=', 'membership.group_id')
                ->where('membership.kind', 'commission')->where('membership.account_id', $seller->id)
                ->where('groups.kind', 'commission')->first(['groups.id', 'groups.rate']) : null;
        $effectiveRate = $hasCustomRate ? $customRate : ($group ? (float) $group->rate : $defaultRate);

        return [
            'default_rate' => $defaultRate,
            'custom_enabled' => $hasCustomRate,
            'custom_rate' => $customRate,
            'effective_rate' => $effectiveRate,
            'source' => $hasCustomRate ? 'vendor_override' : ($group ? 'group:'.$group->id : 'system_default'),
            'exempt' => $effectiveRate == 0.0,
        ];
    }

    public function calculate(string $sellerType, int $sellerId, float $orderTotal): float
    {
        if ($sellerType !== 'seller' || $orderTotal <= 0) {
            return 0;
        }

        $summary = $this->getSummary(Seller::find($sellerId));

        return round(($orderTotal / 100) * $summary['effective_rate'], 2);
    }

    public function updateOverride(Seller $seller, bool $customEnabled, ?float $rate): Seller
    {
        if (auth('admin')->check()) {
            abort_unless(\App\Utils\Helpers::module_permission_check('commission_manage'), 403);
        }
        $before = $seller->sales_commission_percentage;
        $seller->update([
            'sales_commission_percentage' => $customEnabled ? $rate : null,
        ]);
        if (auth('admin')->check() && Schema::hasTable('business_setting_audits')) {
            DB::table('business_setting_audits')->insert(['kind' => 'commission', 'subject_type' => 'vendor',
                'subject_id' => $seller->id, 'before' => json_encode(['rate' => $before]),
                'after' => json_encode(['rate' => $customEnabled ? $rate : null]),
                'admin_id' => auth('admin')->id(), 'created_at' => now()]);
        }

        return $seller->fresh();
    }
}
