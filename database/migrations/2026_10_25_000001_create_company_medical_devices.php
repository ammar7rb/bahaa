<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_medical_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('specifications')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(10);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_published', 'sort_order']);
        });
        Schema::create('medical_device_inquiries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('support_ticket_id')->nullable()->unique();
            $table->string('device_name');
            $table->string('status')->default('new');
            $table->timestamps();
            $table->unique(['customer_id', 'device_id']);
            $table->foreign('device_id')->references('id')->on('company_medical_devices')->restrictOnDelete();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_device_inquiries');
        Schema::dropIfExists('company_medical_devices');
    }
};
