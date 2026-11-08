<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('medical_device_report_purchases', function (Blueprint $table): void {
            if (!Schema::hasColumn('medical_device_report_purchases', 'cart_removed_at')) $table->timestamp('cart_removed_at')->nullable();
            if (!Schema::hasColumn('medical_device_report_purchases', 'report_hidden_at')) $table->timestamp('report_hidden_at')->nullable();
        });
        Schema::table('support_ticket_convs', function (Blueprint $table): void {
            if (!Schema::hasColumn('support_ticket_convs', 'customer_hidden_at')) $table->timestamp('customer_hidden_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('medical_device_report_purchases', function (Blueprint $table): void {
            if (Schema::hasColumn('medical_device_report_purchases', 'cart_removed_at')) $table->dropColumn('cart_removed_at');
            if (Schema::hasColumn('medical_device_report_purchases', 'report_hidden_at')) $table->dropColumn('report_hidden_at');
        });
        Schema::table('support_ticket_convs', function (Blueprint $table): void {
            if (Schema::hasColumn('support_ticket_convs', 'customer_hidden_at')) $table->dropColumn('customer_hidden_at');
        });
    }
};
