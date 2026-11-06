<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('account_activation_documents', 'deleted_at')) {
            Schema::table('account_activation_documents', fn (Blueprint $table) => $table->softDeletes());
        }
        if (!Schema::hasTable('activation_conversation_reads')) Schema::create('activation_conversation_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_activation_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('admin_id')->index();
            $table->unsignedBigInteger('last_message_id')->default(0);
            $table->timestamps();
            $table->unique(['account_activation_case_id', 'admin_id'], 'activation_read_account_admin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_conversation_reads');
        Schema::table('account_activation_documents', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
