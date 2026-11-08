<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('vendor_support_threads', 'order_id')) {
            Schema::table('vendor_support_threads', fn (Blueprint $table) => $table->unsignedBigInteger('order_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Order communication links must be preserved.');
    }
};
