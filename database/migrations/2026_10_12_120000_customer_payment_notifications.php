<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('notifications', function (Blueprint $t) { $t->unsignedBigInteger('customer_id')->nullable()->index(); $t->string('customer_event_key',191)->nullable()->unique(); }); }
    public function down(): void { throw new RuntimeException('Customer payment notifications retain their historical audience.'); }
};
