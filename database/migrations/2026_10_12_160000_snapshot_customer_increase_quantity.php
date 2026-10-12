<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_subscription_payments', function (Blueprint $table) {
            $table->unsignedTinyInteger('offer_quantity')->default(1);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Purchased increase quantities are financial history and must be retained.');
    }
};
