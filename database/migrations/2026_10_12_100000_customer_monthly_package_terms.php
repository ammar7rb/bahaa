<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_credit_offers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->decimal('price', 18, 3); $t->decimal('allowance', 18, 3);
            $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::table('customer_subscription_payments', function (Blueprint $t) {
            $t->unsignedBigInteger('package_id')->nullable()->index();
            $t->unsignedBigInteger('offer_id')->nullable()->index();
            $t->unsignedBigInteger('target_subscription_id')->nullable()->index();
            $t->string('purchase_kind', 20)->default('activation');
            $t->string('terms_name')->nullable(); $t->decimal('granted_allowance', 18, 3)->default(0);
        });
        Schema::table('customer_monthly_subscriptions', function (Blueprint $t) {
            $t->unsignedBigInteger('package_id')->nullable()->index(); $t->string('package_name')->nullable();
            $t->decimal('package_price', 18, 3)->default(0);
            $t->decimal('granted_allowance', 18, 3)->default(0);
            $t->decimal('additional_allowance', 18, 3)->default(0);
            $t->decimal('committed_allowance', 18, 3)->default(0);
        });
        Schema::create('customer_monthly_limit_entries', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id')->index();
            $t->unsignedBigInteger('subscription_id')->index();
            $t->unsignedBigInteger('order_id')->unique();
            $t->decimal('amount', 18, 3); $t->decimal('restored_amount', 18, 3)->default(0); $t->timestamps();
        });
    }

    public function down(): void
    {
        // Financial terms and usage must survive rollback; rollback is deliberately non-destructive.
        throw new RuntimeException('This migration retains financial history. Restore code without dropping package records.');
    }
};
