<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_advertising_rollouts', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('seller_cutoff_id');
            $table->timestamp('starts_at');
            $table->timestamp('grace_ends_at');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('vendor_advertising_rollouts');
    }
};
