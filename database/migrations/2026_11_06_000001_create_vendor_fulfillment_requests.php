<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_fulfillment_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_id')->index();
            $table->string('portion', 24);
            $table->string('status', 24);
            $table->decimal('vendor_amount', 24, 8);
            $table->decimal('shipping_amount', 24, 8)->default(0);
            $table->timestamp('forwarded_at');
            $table->timestamp('pending_due_at')->nullable();
            $table->timestamp('pending_at')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'seller_id', 'portion'], 'vendor_fulfillment_portion_unique');
            $table->index(['status', 'pending_due_at']);
        });
        Schema::create('vendor_fulfillment_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->index();
            $table->unsignedBigInteger('order_detail_id')->unique();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('advertising_slot_id')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('vendor_fulfillment_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->string('event_type', 32);
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->timestamp('created_at');
            $table->unique(['request_id', 'event_type']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve forwarding, eligibility and notification history; use a forward repair.');
    }
};
