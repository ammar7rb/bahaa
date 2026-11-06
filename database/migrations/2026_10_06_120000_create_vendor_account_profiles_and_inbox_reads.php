<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_account_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->unique();
            $table->json('phone_numbers')->nullable();
            $table->json('store_addresses')->nullable();
            $table->text('commercial_registration')->nullable();
            $table->text('tax_card')->nullable();
            $table->string('commercial_document')->nullable();
            $table->string('tax_document')->nullable();
            $table->timestamps();
        });
        Schema::create('vendor_inbox_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('kind', 30);
            $table->unsignedBigInteger('record_id');
            $table->timestamps();
            $table->unique(['seller_id', 'kind', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_inbox_reads');
        Schema::dropIfExists('vendor_account_profiles');
    }
};
