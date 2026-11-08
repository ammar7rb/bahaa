<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_cancellation_settlements', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_key', 100)->unique();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('order_work_item_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable()->index();
            $table->string('canceled_by', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('reason');
            $table->timestamp('canceled_at');
            $table->decimal('gross_amount', 24, 3)->default(0);
            $table->decimal('fee_amount', 24, 3)->default(0);
            $table->decimal('discount_amount', 24, 3)->default(0);
            $table->decimal('refund_amount', 24, 3)->default(0);
            $table->decimal('seller_debited_amount', 24, 3)->default(0);
            $table->decimal('seller_debt_amount', 24, 3)->default(0);
            $table->string('financial_reference', 191)->nullable();
            $table->timestamp('credit_expires_at')->nullable();
            $table->timestamp('credit_expired_at')->nullable();
            $table->decimal('credit_expired_amount', 24, 3)->default(0);
            $table->unsignedBigInteger('reviewed_by_admin_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Cancellation financial receipts must be preserved.');
    }
};
