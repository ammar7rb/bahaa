<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('medical_device_report_purchases', function (Blueprint $table): void {
            $table->timestamp('confirmed_at')->nullable()->after('amount');
            $table->unsignedBigInteger('confirmed_by_admin_id')->nullable()->after('confirmed_at');
            $table->timestamp('report_sent_at')->nullable()->after('confirmed_by_admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('medical_device_report_purchases', function (Blueprint $table): void {
            $table->dropColumn(['confirmed_at', 'confirmed_by_admin_id', 'report_sent_at']);
        });
    }
};
