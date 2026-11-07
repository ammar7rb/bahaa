<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', fn (Blueprint $t) => $t->timestamp('account_deleted_at')->nullable()->index());
        Schema::create('customer_deletion_flows', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id')->index(); $t->string('token_hash', 64)->unique();
            $t->string('password_fingerprint', 64); $t->unsignedTinyInteger('stage')->default(1);
            $t->text('reason')->nullable(); $t->timestamp('expires_at'); $t->timestamp('consumed_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void { throw new RuntimeException('Deletion audit and retained financial identities must not be dropped.'); }
};
