<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('product_display_orders', function (Blueprint $table) {
            $table->string('group_key', 100)->primary();
            $table->json('product_ids');
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('product_display_orders'); }
};
