<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_reassignment_transfers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('work_item_id')->index();
            $table->unsignedBigInteger('order_detail_id')->index();
            $table->unsignedBigInteger('source_order_id')->index();
            $table->unsignedBigInteger('target_order_id')->unique();
            $table->unsignedBigInteger('source_seller_id');
            $table->unsignedBigInteger('target_seller_id');
            $table->unsignedBigInteger('target_product_id');
            $table->unsignedBigInteger('admin_id');
            $table->decimal('customer_amount_transferred', 24, 8);
            $table->decimal('shipping_amount_transferred', 24, 8);
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Order reassignment financial receipts must be preserved.');
    }
};
