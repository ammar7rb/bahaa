<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('customer_checkout_operations', function (Blueprint $table) {
            $table->json('cart_snapshot')->nullable();
        });
    }
    public function down(): void {
        throw new RuntimeException('Preserve submitted customer checkout snapshots during application rollback.');
    }
};
