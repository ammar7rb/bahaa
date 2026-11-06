<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_vendor_pricings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('admin_id');
            $table->decimal('procurement_amount', 24, 8);
            $table->decimal('merchandise_sale', 24, 8);
            $table->json('snapshot');
            $table->timestamp('approved_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Pricing audit records must be preserved; roll back source only.');
    }
};
