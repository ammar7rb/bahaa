<?php

namespace App\Console\Commands;

use App\Jobs\SendAdminNotificationBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProcessAdminNotificationBatches extends Command
{
    protected $signature = 'admin-notifications:process';
    protected $description = 'Deliver confirmed administrator notifications in bounded batches';

    public function handle(): int
    {
        if (!Schema::hasTable('admin_notification_batches')) return self::SUCCESS;

        DB::table('admin_notification_batches')->whereIn('status', ['queued', 'processing'])
            ->orderBy('id')->limit(10)->pluck('id')->each(function ($id) {
                (new SendAdminNotificationBatch((int) $id))->handle();
            });

        return self::SUCCESS;
    }
}
