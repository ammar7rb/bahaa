<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('customer_monthly_limit_restorations', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('order_id'); $t->string('reference',191); $t->decimal('amount',18,3); $t->timestamps(); $t->unique(['order_id','reference']); }); }
    public function down(): void { throw new RuntimeException('Retain allowance restoration history.'); }
};
