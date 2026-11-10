<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendAdminNotificationBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $batchId) {}

    public function handle(): void
    {
        $batch = DB::table('admin_notification_batches')->find($this->batchId);
        if (!$batch) return;
        DB::table('admin_notification_batches')->where('id', $batch->id)->update(['status' => 'processing', 'updated_at' => now()]);
        DB::table('admin_notification_recipients')->where('batch_id', $batch->id)->where('status', 'pending')
            ->orderBy('id')->limit(100)->get()->each(function ($recipient) use ($batch) {
                    try {
                        DB::transaction(function () use ($batch, $recipient) {
                            $row = DB::table('admin_notification_recipients')->where('id', $recipient->id)->lockForUpdate()->first();
                            if (!$row || $row->status !== 'pending') return;
                            $notification = Notification::create([
                                'sent_by' => 'admin', 'sent_to' => $batch->audience === 'vendor' ? 'seller' : 'customer',
                                'seller_id' => $batch->audience === 'vendor' ? $row->account_id : null,
                                'customer_id' => $batch->audience === 'customer' ? $row->account_id : null,
                                'title' => $batch->title, 'description' => $batch->body,
                                'image' => '', 'notification_count' => 1, 'status' => 1,
                            ]);
                            DB::table('admin_notification_recipients')->where('id', $row->id)->update([
                                'status' => 'delivered', 'notification_id' => $notification->id, 'updated_at' => now(),
                            ]);
                        }, 3);
                    } catch (\Throwable $error) {
                        DB::table('admin_notification_recipients')->where('id', $recipient->id)
                            ->where('status', 'pending')->update(['status' => 'failed',
                                'error_code' => class_basename($error), 'updated_at' => now()]);
                    }
            });
        $pending = DB::table('admin_notification_recipients')->where('batch_id', $batch->id)->where('status', 'pending')->exists();
        $failed = DB::table('admin_notification_recipients')->where('batch_id', $batch->id)->where('status', 'failed')->exists();
        DB::table('admin_notification_batches')->where('id', $batch->id)->update([
            'status' => $pending ? 'processing' : ($failed ? 'partial_failure' : 'complete'), 'updated_at' => now(),
        ]);
    }
}
