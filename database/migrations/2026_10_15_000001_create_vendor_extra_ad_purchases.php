<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_extra_ad_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->decimal('unit_price', 14, 2)->nullable();
            $t->unsignedInteger('max_slots')->default(100);
            $t->boolean('enabled')->default(false);
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
        });
        Schema::create('vendor_extra_ad_purchases', function (Blueprint $t) {
            $t->id();
            $t->string('admin_reference', 40)->unique();
            $t->unsignedBigInteger('seller_id')->index();
            $t->unsignedBigInteger('seller_package_subscription_id')->index();
            $t->uuid('request_key');
            $t->unsignedInteger('quantity');
            $t->decimal('unit_price', 14, 2);
            $t->decimal('total_price', 14, 2);
            $t->string('currency', 3)->default('EGP');
            $t->string('payment_method', 32);
            $t->string('status', 32)->index();
            $t->json('terms');
            $t->json('payment_details')->nullable();
            $t->string('payment_reference', 191)->nullable()->unique();
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->text('review_note')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->unique(['seller_id', 'request_key'], 'extra_ad_seller_request');
        });
        Schema::table('vendor_advertising_slots', function (Blueprint $t) {
            $t->unsignedBigInteger('extra_ad_purchase_id')->nullable()->index();
        });
    }
    public function down(): void
    {
        Schema::table('vendor_advertising_slots', function (Blueprint $t) {
            $t->dropIndex(['extra_ad_purchase_id']);
            $t->dropColumn('extra_ad_purchase_id');
        });
        Schema::dropIfExists('vendor_extra_ad_purchases');
        Schema::dropIfExists('vendor_extra_ad_settings');
    }
};
