<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BusinessRatesMigrationTest extends TestCase
{
    public function test_rate_rules_and_order_snapshots_are_created(): void
    {
        Schema::create('orders', fn (Blueprint $table) => $table->id());
        $migration = require base_path('database/migrations/2026_11_10_000001_create_business_rate_rules.php');
        try {
            $migration->up();
            foreach (['business_rate_groups', 'business_rate_group_accounts', 'customer_tax_overrides', 'business_setting_audits'] as $table) {
                $this->assertTrue(Schema::hasTable($table));
            }
            foreach (['customer_tax_rule_source', 'company_commission_rate_snapshot', 'company_commission_rule_source'] as $column) {
                $this->assertTrue(Schema::hasColumn('orders', $column));
            }
        } finally {
            $migration->down();
            Schema::dropIfExists('orders');
        }
    }
}
