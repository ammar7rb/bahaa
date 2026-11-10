<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerTaxRuleService
{
    public function effective(?int $customerId): array
    {
        $global = (bool) getWebConfig(name: 'customer_second_invoice_tax_enabled');
        if (!$customerId || !Schema::hasTable('customer_tax_overrides')) {
            return ['enabled' => $global, 'source' => 'global'];
        }
        $individual = DB::table('customer_tax_overrides')->where('customer_id', $customerId)->first();
        if ($individual) return ['enabled' => (bool) $individual->enabled, 'source' => 'customer'];
        $group = DB::table('business_rate_group_accounts as membership')
            ->join('business_rate_groups as groups', 'groups.id', '=', 'membership.group_id')
            ->where('membership.kind', 'tax')->where('membership.account_id', $customerId)
            ->where('groups.kind', 'tax')->first(['groups.enabled', 'groups.id']);
        return $group
            ? ['enabled' => (bool) $group->enabled, 'source' => 'group:'.$group->id]
            : ['enabled' => $global, 'source' => 'global'];
    }
}
