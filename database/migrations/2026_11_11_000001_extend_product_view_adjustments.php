<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_view_adjustment_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('product_count');
            $table->string('reason', 500);
            $table->timestamps();
        });
        Schema::table('product_view_adjustments', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->string('reason', 500)->nullable();
        });
        Schema::create('vendor_product_unpromoted_visits', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->primary();
            $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('visits')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_product_unpromoted_visits');
        Schema::table('product_view_adjustments', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn(['batch_id', 'reason']);
        });
        Schema::dropIfExists('product_view_adjustment_batches');
    }
};
