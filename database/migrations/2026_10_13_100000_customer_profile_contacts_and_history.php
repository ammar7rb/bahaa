<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_contact_phones', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id')->index(); $t->string('phone', 20); $t->timestamps();
            $t->unique(['customer_id', 'phone']);
        });
        Schema::create('customer_profile_changes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_id')->index(); $t->string('actor_type', 20);
            $t->unsignedBigInteger('actor_id')->nullable(); $t->string('subject_type', 30); $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('action', 40); $t->json('changes'); $t->timestamp('created_at');
        });
        Schema::table('shipping_addresses', function (Blueprint $t) {
            $t->string('floor', 100)->nullable(); $t->string('unit_number', 100)->nullable(); $t->string('company_details', 500)->nullable();
        });
    }
    public function down(): void
    {
        throw new RuntimeException('Customer contact and audit history must be preserved; rollback requires an explicit data preservation plan.');
    }
};
