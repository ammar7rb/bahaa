<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the small shared tables many unit tests read through helpers
 * (getWebConfig, currency and tax lookups) in the in-memory SQLite database.
 * Every method is a no-op when the table already exists, so it never clashes with
 * schemas a test builds itself. Test-only: nothing here touches application code.
 */
trait CreatesBaselineTables
{
    protected function ensureBusinessSettings(): void
    {
        if (! Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->id();
                $table->string('type')->nullable();
                $table->longText('value')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function ensureCurrencies(): void
    {
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('symbol')->nullable();
                $table->string('code')->nullable();
                $table->decimal('exchange_rate', 18, 6)->default(1);
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }
    }

    protected function ensureTaxSetups(): void
    {
        if (! Schema::hasTable('system_tax_setups')) {
            Schema::create('system_tax_setups', function (Blueprint $table) {
                $table->id();
                $table->string('tax_type', 100)->default('order_wise');
                $table->string('country_code', 20)->nullable();
                $table->string('tax_payer', 20)->nullable()->default('vendor');
                $table->text('tax_ids')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_included')->default(false);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('system_tax_setup_additional_data')) {
            Schema::create('system_tax_setup_additional_data', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('system_tax_setup_id')->nullable();
                $table->string('name')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }
}
