<?php

namespace Tests\Unit;

use App\Models\Seller;
use App\Services\CustomerTaxRuleService;
use App\Services\SellerCommissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BusinessRateRulePrecedenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('business_settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->text('value'); $t->timestamps(); });
        Schema::create('sellers', function (Blueprint $t) { $t->id(); $t->decimal('sales_commission_percentage', 7, 3)->nullable(); $t->timestamps(); });
        Schema::create('business_rate_groups', function (Blueprint $t) { $t->id(); $t->string('kind'); $t->string('name'); $t->decimal('rate', 7, 3)->nullable(); $t->boolean('enabled')->nullable(); $t->timestamps(); });
        Schema::create('business_rate_group_accounts', function (Blueprint $t) { $t->id(); $t->string('kind'); $t->unsignedBigInteger('group_id'); $t->unsignedBigInteger('account_id'); $t->timestamps(); });
        Schema::create('customer_tax_overrides', function (Blueprint $t) { $t->unsignedBigInteger('customer_id')->primary(); $t->boolean('enabled'); $t->timestamps(); });
        DB::table('business_settings')->insert([['type' => 'sales_commission', 'value' => '12'], ['type' => 'customer_second_invoice_tax_enabled', 'value' => '1']]);
        DB::table('sellers')->insert(['id' => 5]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        foreach (['customer_tax_overrides', 'business_rate_group_accounts', 'business_rate_groups', 'sellers', 'business_settings'] as $table) Schema::dropIfExists($table);
        Cache::flush();
        parent::tearDown();
    }

    public function test_tax_customer_then_group_then_global(): void
    {
        $service = app(CustomerTaxRuleService::class);
        $this->assertSame(['enabled' => true, 'source' => 'global'], $service->effective(5));
        $id = DB::table('business_rate_groups')->insertGetId(['kind' => 'tax', 'name' => 'Exempt', 'enabled' => false]);
        DB::table('business_rate_group_accounts')->insert(['kind' => 'tax', 'group_id' => $id, 'account_id' => 5]);
        $this->assertSame(['enabled' => false, 'source' => 'group:'.$id], $service->effective(5));
        DB::table('customer_tax_overrides')->insert(['customer_id' => 5, 'enabled' => true]);
        $this->assertSame(['enabled' => true, 'source' => 'customer'], $service->effective(5));
    }

    public function test_commission_vendor_then_group_then_global(): void
    {
        $service = app(SellerCommissionService::class);
        $this->assertSame(12.0, $service->getSummary(Seller::findOrFail(5))['effective_rate']);
        $id = DB::table('business_rate_groups')->insertGetId(['kind' => 'commission', 'name' => 'Group', 'rate' => 7]);
        DB::table('business_rate_group_accounts')->insert(['kind' => 'commission', 'group_id' => $id, 'account_id' => 5]);
        $this->assertSame(7.0, $service->getSummary(Seller::findOrFail(5))['effective_rate']);
        DB::table('sellers')->where('id', 5)->update(['sales_commission_percentage' => 3]);
        $this->assertSame(3.0, $service->getSummary(Seller::findOrFail(5))['effective_rate']);
    }
}
