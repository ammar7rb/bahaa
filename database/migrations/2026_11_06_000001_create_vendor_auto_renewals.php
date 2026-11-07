<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_auto_renewals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->unique();
            $table->unsignedBigInteger('subscription_id');
            $table->decimal('approved_price', 21, 12);
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('disabled_by_admin_id')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->index(['enabled', 'subscription_id']);
        });
        if (Schema::hasTable('business_settings') && !DB::table('business_settings')->where('type', 'seller_sales_cash_transfer')->exists()) {
            DB::table('business_settings')->insert(['type' => 'seller_sales_cash_transfer',
                'value' => json_encode(['enabled' => true, 'type' => 'percent', 'value' => 3]), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void { Schema::dropIfExists('vendor_auto_renewals'); }
};
