<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_settings')) {
            return;
        }

        DB::table('business_settings')
            ->where('type', 'company_email')
            ->whereRaw('LOWER(value) = ?', ['copy@6amtech.com'])
            ->update(['value' => 'ammar.rady@sharkawyshop.com']);
    }

    public function down(): void
    {
        // Restoring an obsolete developer contact would expose it again.
    }
};
