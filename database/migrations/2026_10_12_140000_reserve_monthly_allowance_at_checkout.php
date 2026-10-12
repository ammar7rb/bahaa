<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('customer_checkout_operations', function (Blueprint $t) { $t->unsignedBigInteger('monthly_subscription_id')->nullable()->index(); $t->decimal('reserved_limit_amount',18,3)->default(0); $t->decimal('converted_limit_amount',18,3)->default(0); }); }
    public function down(): void { throw new RuntimeException('Retain checkout allowance reservations.'); }
};
