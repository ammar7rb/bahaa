<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_account_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->uuid('request_key')->unique();
            $table->string('operation', 50);
            $table->string('signature', 64);
            $table->string('source', 50)->default('admin');
            $table->text('reason');
            $table->string('support_reference', 191)->nullable();
            $table->string('financial_reference', 191)->nullable();
            $table->decimal('amount', 24, 3)->default(0);
            $table->json('details')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_account_debts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('created_by_admin_id');
            $table->uuid('request_key')->unique();
            $table->decimal('amount', 24, 3);
            $table->decimal('paid_amount', 24, 3)->default(0);
            $table->text('reason');
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('archived_by_admin_id')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_debt_repayments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('debt_id')->index();
            $table->string('funding_reference', 191);
            $table->decimal('amount', 24, 3);
            $table->decimal('debt_before', 24, 3);
            $table->decimal('debt_after', 24, 3);
            $table->timestamp('created_at');
            $table->unique(['debt_id', 'funding_reference']);
        });
    }

    public function down(): void
    {
        // Financial history must be exported/reconciled before an explicit destructive rollback.
        if (\Illuminate\Support\Facades\DB::table('customer_account_actions')->exists()
            || \Illuminate\Support\Facades\DB::table('customer_account_debts')->exists()) {
            throw new RuntimeException('Customer financial history exists; automatic schema rollback is prohibited.');
        }
        Schema::dropIfExists('customer_debt_repayments');
        Schema::dropIfExists('customer_account_debts');
        Schema::dropIfExists('customer_account_actions');
    }
};
