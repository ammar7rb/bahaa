<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_rate_groups', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->index();
            $table->string('name', 150);
            $table->decimal('rate', 7, 3)->nullable();
            $table->boolean('enabled')->nullable();
            $table->foreignId('updated_by_admin_id')->nullable();
            $table->timestamps();
        });
        Schema::create('business_rate_group_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->foreignId('group_id')->constrained('business_rate_groups')->cascadeOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->timestamps();
            $table->unique(['kind', 'account_id']);
            $table->index(['group_id', 'account_id']);
        });
        Schema::create('customer_tax_overrides', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->primary();
            $table->boolean('enabled');
            $table->foreignId('updated_by_admin_id');
            $table->timestamps();
        });
        Schema::create('business_setting_audits', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40)->index();
            $table->string('subject_type', 30)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->foreignId('admin_id');
            $table->timestamp('created_at');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_tax_rule_source', 40)->nullable();
            $table->decimal('company_commission_rate_snapshot', 7, 3)->nullable();
            $table->string('company_commission_rule_source', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn([
            'customer_tax_rule_source', 'company_commission_rate_snapshot', 'company_commission_rule_source',
        ]));
        Schema::dropIfExists('business_setting_audits');
        Schema::dropIfExists('customer_tax_overrides');
        Schema::dropIfExists('business_rate_group_accounts');
        Schema::dropIfExists('business_rate_groups');
    }
};
