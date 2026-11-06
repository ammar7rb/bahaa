<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_debt_charges', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seller_id')->index();
            $table->string('kind', 32); $table->string('source_type', 40); $table->string('source_id', 64);
            $table->char('idempotency_key', 64)->unique(); $table->char('fingerprint', 64);
            $table->decimal('amount', 24, 8); $table->decimal('wallet_collected', 24, 8);
            $table->decimal('debt_assessed', 24, 8); $table->decimal('outstanding', 24, 8);
            $table->json('rule_snapshot'); $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('assessed_at'); $table->timestamps();
            $table->unique(['seller_id', 'kind', 'source_type', 'source_id'], 'vendor_charge_source_unique');
        });
        Schema::create('vendor_deposit_allocations', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('deposit_id')->unique(); $table->unsignedBigInteger('seller_id')->index();
            $table->decimal('gross_amount', 24, 8); $table->decimal('debt_recovered', 24, 8);
            $table->decimal('net_wallet_credit', 24, 8); $table->decimal('remaining_debt', 24, 8);
            $table->timestamp('confirmed_at'); $table->timestamp('reversed_at')->nullable(); $table->timestamps();
        });
        Schema::create('vendor_debt_repayments', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seller_id')->index(); $table->unsignedBigInteger('charge_id')->index();
            $table->unsignedBigInteger('deposit_id')->nullable()->index(); $table->string('collection_type', 24);
            $table->decimal('amount', 24, 8); $table->char('event_key', 64)->unique();
            $table->timestamp('reversed_at')->nullable(); $table->timestamps();
        });
        Schema::create('vendor_debt_events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('charge_id')->nullable()->index(); $table->unsignedBigInteger('deposit_id')->nullable()->index();
            $table->string('event_type', 40); $table->decimal('amount', 24, 8); $table->char('event_key', 64)->unique();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve assessed charges, recoveries and financial history; use a forward repair.');
    }
};
