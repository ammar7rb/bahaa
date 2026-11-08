<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasIndex('vendor_fulfillment_requests', 'vendor_fulfillment_portion_unique')) {
            Schema::table('vendor_fulfillment_requests', fn (Blueprint $table) => $table->dropUnique('vendor_fulfillment_portion_unique'));
        }
        if (!Schema::hasIndex('vendor_fulfillment_requests', 'vendor_fulfillment_order_portion_index')) {
            Schema::table('vendor_fulfillment_requests', fn (Blueprint $table) => $table->index(['order_id', 'seller_id', 'portion'], 'vendor_fulfillment_order_portion_index'));
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Individual fulfillment receipts must be preserved.');
    }
};
