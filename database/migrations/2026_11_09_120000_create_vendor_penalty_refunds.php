<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_penalty_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('charge_id')->index();
            $table->unsignedBigInteger('repayment_id')->unique();
            $table->unsignedBigInteger('seller_id')->index();
            $table->decimal('amount', 24, 8);
            $table->unsignedBigInteger('admin_id');
            $table->text('reason');
            $table->timestamps();
        });
    }
    public function down(): void { throw new RuntimeException('Preserve penalty refund receipts.'); }
};
