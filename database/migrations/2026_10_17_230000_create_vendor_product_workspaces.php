<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_product_workspaces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->unique();
            $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('private_reference')->unique();
            $table->json('seller_content');
            $table->json('editorial_content')->nullable();
            $table->json('approved_seller_content')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('editorial_revision')->nullable();
            $table->unsignedInteger('approved_revision')->nullable();
            $table->boolean('legacy_exempt')->default(false)->index();
            $table->boolean('publication_suspended')->default(false);
            $table->string('baseline_source', 32)->default('seller_submission');
            $table->string('review_state', 32)->default('submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('vendor_product_workspace_rollouts', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->timestamp('baseline_at');
            $table->unsignedBigInteger('product_cutoff_id');
        });
        Schema::table('order_details', function (Blueprint $table) {
            $table->decimal('vendor_unit_price_snapshot', 24, 8)->nullable();
            $table->unsignedInteger('vendor_content_revision')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve vendor originals, permanent exemptions and order price snapshots; use a forward repair.');
    }
};
