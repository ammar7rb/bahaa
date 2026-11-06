<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_advertising_slots', function (Blueprint $table) {
            $table->unsignedInteger('assignment_revision')->default(0);
            $table->string('reservation_token_hash', 64)->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('reservation_expires_at')->nullable()->index();
        });
        Schema::create('vendor_slot_creation_receipts', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seller_id'); $table->unsignedBigInteger('slot_id');
            $table->string('token_hash', 64); $table->unsignedBigInteger('product_id'); $table->timestamp('created_at');
            $table->unique(['seller_id', 'token_hash'], 'vendor_slot_creation_once');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('vendor_slot_creation_receipts');
        Schema::table('vendor_advertising_slots', fn (Blueprint $table) => $table->dropColumn(['assignment_revision', 'reservation_token_hash', 'reserved_at', 'reservation_expires_at']));
    }
};
