<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_advertising_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('seller_package_subscription_id')->index();
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['seller_package_subscription_id', 'position'], 'vendor_ad_slot_cycle_position');
            $table->unique(['seller_package_subscription_id', 'product_id'], 'vendor_ad_slot_cycle_product');
        });
        Schema::create('vendor_advertising_slot_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('slot_id')->index();
            $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('previous_product_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_advertising_slot_history');
        Schema::dropIfExists('vendor_advertising_slots');
    }
};
