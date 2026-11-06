<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_checkout_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('cart_fingerprint', 64);
            $table->boolean('first_exception')->default(false);
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->json('order_ids')->nullable();
            $table->timestamps();
        });
        Schema::table('users', fn (Blueprint $table) => $table->uuid('first_checkout_operation_id')->nullable()->unique());
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('customer_checkout_operation_id')->nullable()->index();
            $table->timestamp('customer_eligible_at')->nullable();
            $table->string('customer_fulfillment_status')->nullable();
            $table->json('customer_pending_reasons')->nullable();
        });
    }

    public function down(): void
    {
        // Financial operations must survive application rollback.
        throw new RuntimeException('Restore application code without deleting customer purchase eligibility records.');
    }
};
