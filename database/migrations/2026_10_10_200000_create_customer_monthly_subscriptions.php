<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('monthly_subscription_prices', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->decimal('customer_price', 18, 3)->nullable();
            $table->decimal('vendor_price', 18, 3)->nullable();
            $table->string('currency_code', 3);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('open_customer_id')->nullable()->unique();
            $table->uuid('request_key');
            $table->decimal('quoted_amount', 18, 3);
            $table->decimal('wallet_debit_amount', 24, 3);
            $table->string('currency_code', 3);
            $table->string('status', 24)->default('draft')->index();
            $table->string('payment_method', 32)->nullable();
            $table->unsignedBigInteger('offline_payment_method_id')->nullable();
            $table->string('method_name')->nullable();
            $table->json('method_fields')->nullable();
            $table->json('method_information')->nullable();
            $table->string('payment_reference', 191)->nullable()->unique();
            $table->string('payment_proof')->nullable();
            $table->text('payment_note')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'request_key']);
        });
        Schema::create('customer_monthly_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('payment_id')->unique();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
        Schema::create('customer_financial_archives', function (Blueprint $table) {
            $table->id();
            $table->string('source_table');
            $table->string('source_id');
            $table->json('snapshot');
            $table->string('reason');
            $table->timestamp('archived_at');
            $table->unique(['source_table','source_id']);
        });
        Schema::create('customer_legacy_payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('amount', 24, 3);
            $table->string('currency_code', 3);
            $table->json('snapshot');
            $table->timestamp('received_at');
        });
    }

    public function down(): void
    {
        // Financial history must survive a code rollback. Use an explicit,
        // reconciled recovery procedure instead of dropping payment records.
        throw new RuntimeException('Financial migration cannot be rolled back destructively.');
    }
};
