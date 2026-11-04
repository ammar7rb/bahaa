<?php

\Illuminate\Support\Facades\Schedule::call(function () {
    \App\Models\Notification::expiredWalletDeposit()->select('id')->chunkById(500, function ($notifications) {
        $ids = $notifications->modelKeys();
        \Illuminate\Support\Facades\DB::transaction(function () use ($ids) {
            \App\Models\NotificationSeen::whereIn('notification_id', $ids)->delete();
            \App\Models\Notification::expiredWalletDeposit()->whereKey($ids)->delete();
        });
    });
})->name('purge-expired-wallet-deposit-notifications')->daily()->withoutOverlapping();

\Illuminate\Support\Facades\Schedule::call(function () {
    if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'package_auto_renew')) {
        app(\App\Services\CustomerPackageAutoRenewalService::class)->run();
    }
})->name('renew-expired-customer-packages')->everyFiveMinutes()->withoutOverlapping();
