<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('onboarding_journey', 24)->default('legacy');
            $table->string('canonical_phone', 11)->nullable()->unique();
            $table->string('initial_checkout_reference', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['onboarding_journey', 'canonical_phone', 'initial_checkout_reference']));
    }
};
