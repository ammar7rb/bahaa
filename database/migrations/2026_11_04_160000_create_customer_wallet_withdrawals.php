<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wallet_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->uuid('request_key');
            $table->decimal('amount', 24, 3);
            $table->decimal('fee_percent', 5, 2);
            $table->decimal('fee_amount', 24, 3);
            $table->decimal('net_amount', 24, 3);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('reference', 191)->nullable()->unique();
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_withdrawals');
    }
};
