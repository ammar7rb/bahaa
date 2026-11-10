<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_notification_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->unsignedBigInteger('admin_id');
            $table->string('audience', 20);
            $table->string('scope', 20);
            $table->string('title', 150);
            $table->text('body');
            $table->unsignedInteger('recipient_count');
            $table->string('status', 20)->default('queued');
            $table->timestamps();
        });
        Schema::create('admin_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('admin_notification_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('error_code', 80)->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notification_recipients');
        Schema::dropIfExists('admin_notification_batches');
    }
};
