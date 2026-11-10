<?php

namespace Tests\Unit;

use App\Jobs\SendAdminNotificationBatch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminNotificationBatchTest extends TestCase
{
    public function test_retried_batch_delivers_one_inbox_notification_per_selected_account(): void
    {
        Schema::create('notifications', function (Blueprint $t) {
            $t->id(); $t->string('sent_by'); $t->string('sent_to'); $t->unsignedBigInteger('seller_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable(); $t->string('title'); $t->text('description');
            $t->string('image')->nullable(); $t->integer('notification_count'); $t->boolean('status'); $t->timestamps();
        });
        Schema::create('storages', function (Blueprint $t) {
            $t->id(); $t->string('data_type'); $t->unsignedBigInteger('data_id'); $t->string('key');
            $t->string('value'); $t->timestamps();
        });
        $migration = require base_path('database/migrations/2026_11_11_000002_create_admin_notification_batches.php');
        $migration->up();
        try {
            $id = DB::table('admin_notification_batches')->insertGetId([
                'request_key' => '7ce6e1e6-3d1a-409d-92e4-791dcb170775', 'admin_id' => 1,
                'audience' => 'customer', 'scope' => 'selected', 'title' => 'خبر', 'body' => 'النص',
                'recipient_count' => 2, 'status' => 'queued', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('admin_notification_recipients')->insert([
                ['batch_id' => $id, 'account_id' => 11, 'status' => 'pending'],
                ['batch_id' => $id, 'account_id' => 12, 'status' => 'pending'],
            ]);
            $job = new SendAdminNotificationBatch($id);
            $job->handle(); $job->handle();
            $this->assertSame(2, DB::table('notifications')->count());
            $this->assertEqualsCanonicalizing([11, 12], DB::table('notifications')->pluck('customer_id')->all());
            $this->assertSame(2, DB::table('admin_notification_recipients')->where('status', 'delivered')->count());
            $this->assertSame('complete', DB::table('admin_notification_batches')->find($id)->status);
        } finally {
            $migration->down(); Schema::dropIfExists('storages'); Schema::dropIfExists('notifications');
        }
    }
}
