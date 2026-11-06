<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->timestamp('admin_read_at')->nullable();
            $table->unsignedBigInteger('admin_read_message_id')->nullable();
        });
        Schema::table('contacts', fn (Blueprint $table) => $table->string('sender_type', 16)->nullable());
    }
    public function down(): void
    {
        Schema::table('support_tickets', fn (Blueprint $table) => $table->dropColumn(['admin_read_at', 'admin_read_message_id']));
        Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn('sender_type'));
    }
};
