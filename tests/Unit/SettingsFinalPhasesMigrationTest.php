<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingsFinalPhasesMigrationTest extends TestCase
{
    public function test_view_adjustment_and_notification_batches_schema(): void
    {
        Schema::create('product_view_adjustments', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('product_id'); $table->unsignedBigInteger('admin_id');
            $table->unsignedInteger('amount'); $table->timestamps();
        });
        $views = require base_path('database/migrations/2026_11_11_000001_extend_product_view_adjustments.php');
        $notifications = require base_path('database/migrations/2026_11_11_000002_create_admin_notification_batches.php');
        try {
            $views->up(); $notifications->up();
            $this->assertTrue(Schema::hasColumn('product_view_adjustments', 'reason'));
            $this->assertTrue(Schema::hasColumn('product_view_adjustments', 'batch_id'));
            $this->assertTrue(Schema::hasTable('vendor_product_unpromoted_visits'));
            $this->assertTrue(Schema::hasTable('product_view_adjustment_batches'));
            $this->assertTrue(Schema::hasTable('admin_notification_batches'));
            $this->assertTrue(Schema::hasTable('admin_notification_recipients'));
        } finally {
            $notifications->down(); $views->down(); Schema::dropIfExists('product_view_adjustments');
        }
    }
}
