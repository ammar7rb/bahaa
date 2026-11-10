<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credit_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('support_ticket_id')->nullable()->index();
            $table->string('product_code', 64);
            $table->decimal('requested_product_price', 40, 20);
            $table->text('customer_message')->nullable();
            $table->string('status', 24)->default('submitted')->index();
            $table->string('active_key', 100)->nullable()->unique();
            $table->text('admin_response')->nullable();
            $table->unsignedBigInteger('responded_by_admin_id')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_requests');
    }
};
