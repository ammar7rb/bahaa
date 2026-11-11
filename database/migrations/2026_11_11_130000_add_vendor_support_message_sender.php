<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vendor_support_messages') && !Schema::hasColumn('vendor_support_messages', 'sender_seller_id')) {
            Schema::table('vendor_support_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('sender_seller_id')->nullable()->after('admin_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vendor_support_messages', 'sender_seller_id')) {
            Schema::table('vendor_support_messages', function (Blueprint $table) {
                $table->dropColumn('sender_seller_id');
            });
        }
    }
};
