<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_initial_payment_review_status', 20)->nullable()->index();
            $table->unsignedBigInteger('customer_initial_payment_reviewed_by')->nullable();
            $table->timestamp('customer_initial_payment_reviewed_at')->nullable();
            $table->text('customer_initial_payment_review_note')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Payment review history must be retained.');
    }
};
