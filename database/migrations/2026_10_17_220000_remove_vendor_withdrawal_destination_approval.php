<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('vendor_withdraw_method_infos')) return;
        // Only destinations held by the retired approval gate are released.
        // Explicitly disabled/rejected destinations and withdrawal snapshots stay intact.
        DB::table('vendor_withdraw_method_infos')->where('approval_status', 'pending')->update([
            'approval_status' => 'approved', 'approval_reason' => null,
            'reviewed_at' => null, 'reviewed_by' => null, 'is_active' => true,
        ]);
    }

    public function down(): void
    {
        // Do not revoke vendor destinations or rewrite financial history on code rollback.
    }
};
