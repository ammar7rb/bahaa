<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_support_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('created_by_admin_id');
            $table->string('subject', 191);
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });
        Schema::create('vendor_support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_support_thread_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('admin_id');
            $table->text('body');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('vendor_support_messages');
        Schema::dropIfExists('vendor_support_threads');
    }
};
