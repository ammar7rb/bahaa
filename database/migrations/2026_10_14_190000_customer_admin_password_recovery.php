<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users',function(Blueprint $t){
            $t->boolean('must_change_password')->default(false);
            $t->timestamp('temporary_password_expires_at')->nullable();
            $t->unsignedInteger('password_revision')->default(0);
        });
        Schema::create('customer_password_recovery_requests',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('customer_id')->nullable()->index();$t->unsignedBigInteger('contact_id')->nullable()->index();$t->string('identity',191);
            $t->string('status')->default('pending');$t->unsignedBigInteger('verified_by')->nullable();
            $t->text('verification_note')->nullable();$t->timestamp('verified_at')->nullable();
            $t->string('delivery_channel')->nullable();$t->timestamp('issued_at')->nullable();$t->timestamp('completed_at')->nullable();$t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('customer_password_recovery_requests');
        Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['must_change_password','temporary_password_expires_at','password_revision']));
    }
};
