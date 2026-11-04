<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_product_submission_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('slot_id')->nullable()->index();
            $table->unsignedInteger('revision');
            $table->json('seller_content');
            $table->timestamp('recorded_at');
            $table->unique(['workspace_id', 'revision'], 'vendor_submission_revision_once');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve vendor submissions and slot mappings; use a forward repair.');
    }
};
