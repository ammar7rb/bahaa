<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_transfer_references', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('method_id'); $t->char('reference_hash', 64);
            $t->string('reference', 191); $t->string('source_type', 40); $t->unsignedBigInteger('source_id');
            $t->string('status', 20)->default('reserved'); $t->timestamps();
            $t->unique(['method_id','reference_hash'], 'vendor_transfer_method_reference');
            $t->unique(['source_type','source_id'], 'vendor_transfer_source');
        });
        Schema::create('vendor_funding_intents', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id')->index(); $t->unsignedBigInteger('deposit_id')->unique();
            $t->string('kind', 20); $t->string('status', 32)->index(); $t->json('snapshot');
            $t->decimal('purchase_amount', 18, 2); $t->string('currency', 3)->default('EGP');
            $t->unsignedBigInteger('package_id')->nullable(); $t->unsignedBigInteger('extra_purchase_id')->nullable();
            $t->unsignedBigInteger('subscription_id')->nullable(); $t->text('message')->nullable();
            $t->timestamp('completed_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('vendor_funding_intents'); Schema::dropIfExists('vendor_transfer_references');
    }
};
