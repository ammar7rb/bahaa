<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('company_medical_devices', function (Blueprint $table): void {
            $table->decimal('price', 24, 3)->nullable();
            $table->decimal('report_price', 24, 3)->nullable();
            $table->string('device_condition', 16)->nullable();
            $table->string('origin_country', 2)->nullable();
        });
        Schema::create('medical_device_report_purchases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('inquiry_id')->unique();
            $table->unsignedBigInteger('wallet_transaction_id')->unique();
            $table->decimal('amount', 24, 3);
            $table->timestamp('purchase_requested_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'device_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Medical report payments must be preserved.');
    }
};
