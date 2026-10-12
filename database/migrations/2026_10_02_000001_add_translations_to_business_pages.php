<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('business_pages', function (Blueprint $table) {
            $table->json('translations')->nullable();
        });
        \Illuminate\Support\Facades\Cache::forget('cache_for_business_page_list');
    }

    public function down(): void
    {
        Schema::table('business_pages', function (Blueprint $table) {
            $table->dropColumn('translations');
        });
    }
};
