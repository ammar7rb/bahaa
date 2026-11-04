<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->string('source_bucket')->default('available');
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->unsignedBigInteger('processed_by_admin_id')->nullable();
            $table->timestamp('payout_due_at')->nullable()->index();
            $table->string('payout_reference', 191)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->dropUnique(['payout_reference']);
            $table->dropIndex(['payout_due_at']);
            $table->dropColumn(['source_bucket', 'reviewed_at', 'reviewed_by_admin_id', 'processed_by_admin_id', 'payout_due_at', 'payout_reference']);
        });
    }
};
