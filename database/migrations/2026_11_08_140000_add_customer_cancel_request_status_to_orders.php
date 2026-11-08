<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'customer_cancel_request_status')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('customer_cancel_request_status', 32)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'customer_cancel_request_status')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('customer_cancel_request_status');
            });
        }
    }
};
