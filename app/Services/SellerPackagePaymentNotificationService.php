<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Seller;
use App\Models\SellerPackageSubscription;
use App\Traits\PushNotificationTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SellerPackagePaymentNotificationService
{
    use PushNotificationTrait;

    public function notifyPaid(SellerPackageSubscription $subscription): void
    {
        if (!Schema::hasTable('notifications') || !Schema::hasColumn('notifications', 'seller_id')) return;

        $sellerId = (int) $subscription->seller_id;
        $title = 'قبول دفع الباقة';
        $description = 'تم قبول دفع باقة '.$subscription->package_name.'.';
        Notification::query()->create([
            'sent_by' => 'admin', 'sent_to' => 'seller', 'seller_id' => $sellerId,
            'title' => $title, 'description' => $description, 'image' => '',
            'notification_count' => 1, 'status' => 1,
        ]);

        DB::afterCommit(function () use ($sellerId, $title, $description) {
            try {
                $token = Seller::find($sellerId)?->cm_firebase_token;
                if ($token) $this->sendPushNotificationToDevice($token, [
                    'title' => $title, 'description' => $description,
                    'image' => '', 'type' => 'notification',
                ]);
            } catch (\Throwable $error) {
                Log::warning('Seller package payment push failed', [
                    'seller_id' => $sellerId, 'error_type' => get_class($error),
                ]);
            }
        });
    }
}
