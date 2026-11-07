<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('order_admin_purchase_refunds', function (Blueprint $table) {
            if (!Schema::hasColumn('order_admin_purchase_refunds', 'refund_method')) $table->string('refund_method', 40)->nullable();
            if (!Schema::hasColumn('order_admin_purchase_refunds', 'admin_note')) $table->text('admin_note')->nullable();
        });
    }
    public function down(): void {
        Schema::table('order_admin_purchase_refunds', function (Blueprint $table) {
            if (Schema::hasColumn('order_admin_purchase_refunds', 'refund_method')) $table->dropColumn('refund_method');
            if (Schema::hasColumn('order_admin_purchase_refunds', 'admin_note')) $table->dropColumn('admin_note');
        });
    }
};
