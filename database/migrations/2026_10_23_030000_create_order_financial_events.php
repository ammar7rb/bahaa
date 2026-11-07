<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_financial_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('seller_id')->index();
            $table->char('event_key', 64)->unique();
            $table->string('event_type', 80);
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->json('snapshot');
            $table->timestamp('created_at');
        });
    }
    public function down(): void
    {
        throw new RuntimeException('Financial audit receipts must be retained. Roll back application sources only.');
    }
};
