<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('customer_checkout_operations') && !Schema::hasColumn('customer_checkout_operations', 'origin_snapshot')) {
            Schema::table('customer_checkout_operations', fn (Blueprint $table) => $table->json('origin_snapshot')->nullable());
        }
        // Historical account/package states cannot be reconstructed reliably. Leave them unknown.
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve checkout origin records when rolling back application code.');
    }
};
